<?php

namespace App\Search;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

final class SearchQueryRunner
{
    public function run(SearchQuery $query, User $actor): SearchResult
    {
        $limit = max(1, min($query->limit, SearchSchema::MAX_LIMIT));
        $appliedQuery = $query->withLimit($limit);
        $entity = SearchSchema::entity($query->entity);

        if ($entity === null || $actor->company_id === null) {
            return new SearchResult(
                rows: collect(),
                total: 0,
                truncated: false,
                appliedQuery: $appliedQuery,
                sql: '',
            );
        }

        $builder = $this->newScopedQuery($entity, $actor);
        $this->applyFilters($builder, $entity, $query, $actor);

        if ($query->aggregate !== null) {
            return $this->runAggregate($builder, $entity, $query, $appliedQuery, $limit);
        }

        $this->applySort($builder, $entity, $query);

        $total = (clone $builder)->count();
        $columns = $this->selectableColumns($entity, $actor);

        $builder->select($this->qualifyColumns($builder, $columns));
        $this->eagerLoad($builder, $entity, $actor);
        $builder->limit($limit);

        $sql = $builder->toBase()->toRawSql();
        $rows = $builder->get();
        $this->revealTaxIdIfAuthorized($rows, $actor, $entity->name);

        return new SearchResult(
            rows: $rows,
            total: $total,
            truncated: $total > $limit,
            appliedQuery: $appliedQuery,
            sql: $sql,
        );
    }

    /**
     * @return Builder<Model>
     */
    private function newScopedQuery(EntitySchema $entity, User $actor): Builder
    {
        $model = $entity->model;
        $builder = $model::query();
        $builder->where($builder->getModel()->qualifyColumn($entity->tenantColumn), $actor->company_id);

        return $builder;
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyFilters(Builder $builder, EntitySchema $entity, SearchQuery $query, User $actor): void
    {
        foreach ($query->filters as $filter) {
            $field = $entity->field($filter->field);

            if ($field === null || $field->denied || $field->column() === $entity->tenantColumn) {
                continue;
            }

            $operator = Operator::tryFrom($filter->operator);

            if ($operator === null || ! $field->allows($operator)) {
                continue;
            }

            if ($field->isRelation()) {
                $this->applyRelationFilter($builder, $field, $filter, $operator, $actor);

                continue;
            }

            $this->applyColumnFilter(
                $builder,
                $builder->getModel()->qualifyColumn($field->column()),
                $field,
                $filter,
                $operator,
            );
        }
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyRelationFilter(
        Builder $builder,
        FieldSchema $field,
        Filter $filter,
        Operator $operator,
        User $actor,
    ): void {
        $relatedModel = $field->relationModel;
        $relatedColumn = $field->relationColumn;
        $foreignKey = $field->relationForeignKey;

        if ($relatedModel === null || $relatedColumn === null || $foreignKey === null) {
            return;
        }

        $subquery = $relatedModel::query()->select('id');
        $subquery->where($subquery->getModel()->qualifyColumn('company_id'), $actor->company_id);
        $this->applyColumnFilter(
            $subquery,
            $subquery->getModel()->qualifyColumn($relatedColumn),
            $field,
            $filter,
            $operator,
        );

        $builder->whereIn($builder->getModel()->qualifyColumn($foreignKey), $subquery);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyColumnFilter(
        Builder $builder,
        string $column,
        FieldSchema $field,
        Filter $filter,
        Operator $operator,
    ): void {
        match ($operator) {
            Operator::Equals => $this->applyEquals($builder, $column, $field, $filter->value),
            Operator::NotEquals => $this->applyNotEquals($builder, $column, $field, $filter->value),
            Operator::GreaterThan => $builder->where($column, '>', $this->comparisonValue($field, $filter->value, 'gt')),
            Operator::LessThan => $builder->where($column, '<', $this->comparisonValue($field, $filter->value, 'lt')),
            Operator::GreaterThanOrEqual => $builder->where($column, '>=', $this->comparisonValue($field, $filter->value, 'gte')),
            Operator::LessThanOrEqual => $builder->where($column, '<=', $this->comparisonValue($field, $filter->value, 'lte')),
            Operator::In => $builder->whereIn($column, $this->listValues($field, $filter->value)),
            Operator::Contains => $builder->whereLike($column, '%'.(string) $filter->value.'%'),
            Operator::Between => $this->applyBetween($builder, $column, $field, $filter->value),
        };
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyEquals(Builder $builder, string $column, FieldSchema $field, mixed $value): void
    {
        if ($this->isDateOnly($field, $value)) {
            $date = $this->parseDate($value);

            if ($date === null) {
                return;
            }

            $builder->whereBetween($column, [$date->startOfDay(), $date->endOfDay()]);

            return;
        }

        $builder->where($column, $this->scalarValue($field, $value));
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyNotEquals(Builder $builder, string $column, FieldSchema $field, mixed $value): void
    {
        if ($this->isDateOnly($field, $value)) {
            $date = $this->parseDate($value);

            if ($date === null) {
                return;
            }

            $builder->where(function (Builder $nested) use ($column, $date): void {
                $nested->where($column, '<', $date->startOfDay())
                    ->orWhere($column, '>', $date->endOfDay());
            });

            return;
        }

        $builder->where($column, '!=', $this->scalarValue($field, $value));
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyBetween(Builder $builder, string $column, FieldSchema $field, mixed $value): void
    {
        if (! is_array($value) || count($value) !== 2) {
            return;
        }

        $bounds = array_values($value);
        $start = $this->comparisonValue($field, $bounds[0], 'gte');
        $end = $this->comparisonValue($field, $bounds[1], 'lte');

        if ($start === null || $end === null) {
            return;
        }

        $builder->whereBetween($column, [$start, $end]);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applySort(Builder $builder, EntitySchema $entity, SearchQuery $query): void
    {
        $sort = $query->sort ?? $entity->defaultSort;

        if (! $entity->allowsSort($sort)) {
            $sort = $entity->defaultSort;
        }

        $direction = strtolower($query->direction) === 'asc' ? 'asc' : 'desc';
        $builder->orderBy($builder->getModel()->qualifyColumn($sort), $direction);
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function runAggregate(
        Builder $builder,
        EntitySchema $entity,
        SearchQuery $query,
        SearchQuery $appliedQuery,
        int $limit,
    ): SearchResult {
        $aggregate = $query->aggregate;

        if ($aggregate === null) {
            return new SearchResult(
                rows: collect(),
                total: 0,
                truncated: false,
                appliedQuery: $appliedQuery,
                sql: $builder->toBase()->toRawSql(),
            );
        }

        $function = strtolower($aggregate->function);
        $field = $entity->field($aggregate->field);

        if ($field === null || $field->denied) {
            return new SearchResult(
                rows: collect(),
                total: 0,
                truncated: false,
                appliedQuery: $appliedQuery,
                sql: $builder->toBase()->toRawSql(),
            );
        }

        if ($aggregate->groupBy !== null) {
            return $this->runGroupedAggregate($builder, $entity, $aggregate, $function, $field, $appliedQuery, $limit);
        }

        $aggregateBuilder = clone $builder;
        $this->applyAggregateSelect($aggregateBuilder, $function, $field);

        $sql = $aggregateBuilder->toBase()->toRawSql();
        $value = $aggregateBuilder->value('aggregate');
        $numericValue = is_numeric($value) ? $value + 0 : 0;

        return new SearchResult(
            rows: collect([(object) ['aggregate' => $numericValue]]),
            total: $function === 'count' ? (int) $numericValue : 1,
            truncated: false,
            appliedQuery: $appliedQuery,
            sql: $sql,
        );
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function runGroupedAggregate(
        Builder $builder,
        EntitySchema $entity,
        Aggregate $aggregate,
        string $function,
        FieldSchema $field,
        SearchQuery $appliedQuery,
        int $limit,
    ): SearchResult {
        $groupBy = $entity->field($aggregate->groupBy ?? '');

        if ($groupBy === null || $groupBy->denied || $groupBy->isRelation()) {
            return new SearchResult(
                rows: collect(),
                total: 0,
                truncated: false,
                appliedQuery: $appliedQuery,
                sql: $builder->toBase()->toRawSql(),
            );
        }

        $groupColumn = $builder->getModel()->qualifyColumn($groupBy->column());

        $builder->select($groupColumn);
        $this->applyAggregateSelect($builder, $function, $field);
        $builder->groupBy($groupColumn);

        $total = (clone $builder)->toBase()->getCountForPagination();
        $builder->limit($limit);

        return new SearchResult(
            rows: collect($builder->toBase()->get()),
            total: $total,
            truncated: $total > $limit,
            appliedQuery: $appliedQuery,
            sql: $builder->toBase()->toRawSql(),
        );
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function applyAggregateSelect(Builder $builder, string $function, FieldSchema $field): void
    {
        if ($function === 'sum' && $field->column() === 'total_cents') {
            $builder->selectRaw('sum(total_cents) as aggregate');

            return;
        }

        if ($function === 'avg' && $field->column() === 'total_cents') {
            $builder->selectRaw('avg(total_cents) as aggregate');

            return;
        }

        $builder->selectRaw('count(*) as aggregate');
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function eagerLoad(Builder $builder, EntitySchema $entity, User $actor): void
    {
        $relations = [];

        foreach ($entity->eagerLoad as $relation) {
            if ($relation === 'customer') {
                $customerColumns = $this->customerColumns($actor);
                $relations['customer'] = function (Relation $relationBuilder) use ($customerColumns): void {
                    $relationBuilder->select($customerColumns);
                };

                continue;
            }

            if ($relation === 'assignedUser') {
                $relations['assignedUser'] = function (Relation $relationBuilder): void {
                    $relationBuilder->select(['id', 'name', 'email']);
                };

                continue;
            }

            if ($relation === 'serviceRequest') {
                $relations['serviceRequest'] = function (Relation $relationBuilder): void {
                    $relationBuilder->select([
                        'id',
                        'company_id',
                        'customer_id',
                        'title',
                        'status',
                        'opened_at',
                    ]);
                };

                continue;
            }

            $relations[] = $relation;
        }

        if ($relations !== []) {
            $builder->with($relations);
        }
    }

    /**
     * @return list<string>
     */
    private function selectableColumns(EntitySchema $entity, User $actor): array
    {
        $columns = $entity->select;

        if ($entity->name === 'customers' && $actor->role === UserRole::Admin) {
            $columns[] = 'tax_id';
        }

        return array_values(array_unique($columns));
    }

    /**
     * @return list<string>
     */
    private function customerColumns(User $actor): array
    {
        $columns = ['id', 'company_id', 'name', 'email', 'phone', 'status', 'created_at', 'updated_at'];

        if ($actor->role === UserRole::Admin) {
            $columns[] = 'tax_id';
        }

        return $columns;
    }

    /**
     * @param  Builder<Model>  $builder
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function qualifyColumns(Builder $builder, array $columns): array
    {
        return array_map(
            fn (string $column): string => $builder->getModel()->qualifyColumn($column),
            $columns,
        );
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    private function revealTaxIdIfAuthorized(Collection $rows, User $actor, string $entity): void
    {
        if ($actor->role !== UserRole::Admin) {
            return;
        }

        if ($entity === 'customers') {
            $rows->each(function (mixed $row): void {
                if ($row instanceof Model) {
                    $row->makeVisible('tax_id');
                }
            });

            return;
        }

        $rows->each(function (mixed $row): void {
            if (! $row instanceof Model || ! $row->relationLoaded('customer')) {
                return;
            }

            $customer = $row->getRelation('customer');

            if ($customer instanceof Customer) {
                $customer->makeVisible('tax_id');
            }
        });
    }

    private function isDateOnly(FieldSchema $field, mixed $value): bool
    {
        return in_array($field->type, [FieldType::Date, FieldType::DateTime], true)
            && is_string($value)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    private function comparisonValue(FieldSchema $field, mixed $value, string $bound): mixed
    {
        if (! in_array($field->type, [FieldType::Date, FieldType::DateTime], true)) {
            return $this->scalarValue($field, $value);
        }

        $date = $this->parseDate($value);

        if ($date === null) {
            return null;
        }

        if (! $this->isDateOnly($field, $value)) {
            return $date;
        }

        return match ($bound) {
            'gt' => $date->endOfDay(),
            'gte', 'lt' => $date->startOfDay(),
            'lte' => $date->endOfDay(),
            default => $date,
        };
    }

    private function scalarValue(FieldSchema $field, mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return match ($field->type) {
            FieldType::Integer => is_numeric($value) ? (int) $value : $value,
            FieldType::Boolean => (bool) $value,
            FieldType::Date, FieldType::DateTime => $this->parseDate($value) ?? $value,
            default => $value,
        };
    }

    /**
     * @return list<mixed>
     */
    private function listValues(FieldSchema $field, mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_map(fn (mixed $item): mixed => $this->scalarValue($field, $item), array_values($value));
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if ($value instanceof CarbonImmutable) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        foreach (['Y-m-d', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if ($parsed instanceof CarbonImmutable && $parsed->format($format) === $value) {
                return $parsed;
            }
        }

        return null;
    }
}
