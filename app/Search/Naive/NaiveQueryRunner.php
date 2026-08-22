<?php

namespace App\Search\Naive;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Search\Filter;
use App\Search\SearchQuery;
use App\Search\SearchResult;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use PDOException;
use RuntimeException;

/**
 * Deliberately unsafe runner for the failure showcase. Do not use outside
 * tests/Feature/FailureModes or the demo:failures command.
 */
final class NaiveQueryRunner
{
    public function run(SearchQuery $query, User $actor): SearchResult
    {
        unset($actor);

        $builder = $this->newQuery($query->entity);
        $this->applyFilters($builder, $query);

        if ($query->aggregate !== null) {
            $sql = $builder->toBase()->toRawSql();
            $count = $builder->count();

            return new SearchResult(
                rows: collect([(object) ['aggregate' => $count]]),
                total: $count,
                truncated: false,
                appliedQuery: $query,
                sql: $sql,
            );
        }

        $sql = $builder->toBase()->toRawSql();
        $rows = $builder->get();
        $this->exposeTaxIds($rows);

        return new SearchResult(
            rows: $rows,
            total: $rows->count(),
            truncated: false,
            appliedQuery: $query,
            sql: $sql,
        );
    }

    /**
     * @return Builder<Customer>|Builder<Invoice>|Builder<ServiceRequest>
     */
    private function newQuery(string $entity): Builder
    {
        return match ($entity) {
            'service_requests' => ServiceRequest::withTrashed(),
            'customers' => Customer::withTrashed(),
            'invoices' => Invoice::query(),
            default => throw new InvalidArgumentException("Unknown entity '{$entity}'."),
        };
    }

    /**
     * @param  Builder<Customer>|Builder<Invoice>|Builder<ServiceRequest>  $builder
     */
    private function applyFilters(Builder $builder, SearchQuery $query): void
    {
        foreach ($query->filters as $filter) {
            if ($filter->field === 'customer_name') {
                $customers = Customer::withTrashed();
                $this->applyColumnFilter($customers->getQuery(), $customers->getModel()->getTable(), 'name', $filter);
                $builder->whereIn('customer_id', $customers->select('id'));

                continue;
            }

            $this->applyColumnFilter($builder->getQuery(), $builder->getModel()->getTable(), $filter->field, $filter);
        }
    }

    private function applyColumnFilter(QueryBuilder $builder, string $table, string $column, Filter $filter): void
    {
        $this->assertColumnExists($builder, $table, $column);

        match ($filter->operator) {
            'contains' => $builder->where($column, 'like', '%'.(string) $filter->value.'%'),
            'between' => $this->applyBetween($builder, $column, $filter->value),
            'in' => $builder->whereIn($column, is_array($filter->value) ? array_values($filter->value) : []),
            default => $builder->where($column, $filter->operator, $filter->value),
        };
    }

    private function assertColumnExists(QueryBuilder $builder, string $table, string $column): void
    {
        $sql = "select {$column} from {$table} limit 1";
        $connection = $builder->getConnection();

        if (! $connection instanceof Connection) {
            throw new RuntimeException('Naive runner requires a concrete database connection.');
        }

        try {
            $connection->getPdo()->query($sql);
        } catch (PDOException $exception) {
            throw new QueryException($connection->getName(), $sql, [], $exception);
        }
    }

    private function applyBetween(QueryBuilder $builder, string $column, mixed $value): void
    {
        if (! is_array($value) || count($value) !== 2) {
            return;
        }

        $bounds = array_values($value);

        $builder->whereBetween($column, [
            $this->bound($bounds[0], start: true),
            $this->bound($bounds[1], start: false),
        ]);
    }

    private function bound(mixed $value, bool $start): mixed
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $value;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        if (! $date instanceof CarbonImmutable) {
            return $value;
        }

        return $start ? $date->startOfDay() : $date->endOfDay();
    }

    /**
     * @param  Collection<int, mixed>  $rows
     */
    private function exposeTaxIds(Collection $rows): void
    {
        $rows->each(function (mixed $row): void {
            if (! $row instanceof Model) {
                return;
            }

            $row->makeVisible('tax_id');

            if ($row->relationLoaded('customer') && $row->getRelation('customer') instanceof Customer) {
                $row->getRelation('customer')->makeVisible('tax_id');
            }
        });
    }
}
