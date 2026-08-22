<?php

namespace App\Search\Naive;

use App\Models\Customer;
use App\Models\User;
use App\Search\EntityResolution;
use App\Search\EntityResolutionOutcome;
use App\Search\Filter;
use App\Search\Operator;
use App\Search\SearchQuery;

/**
 * Deliberately unsafe resolver for the failure showcase. Do not use outside
 * tests/Feature/FailureModes or the demo:failures command.
 */
final class NaiveEntityResolver
{
    public function resolve(SearchQuery $query, User $actor): EntityResolution
    {
        unset($actor);

        $nameFilters = array_values(array_filter(
            $query->filters,
            fn (Filter $filter): bool => $this->isNameFilter($query->entity, $filter),
        ));

        if ($nameFilters === []) {
            return new EntityResolution(EntityResolutionOutcome::Unchanged, $query);
        }

        $remaining = array_values(array_filter(
            $query->filters,
            fn (Filter $filter): bool => ! $this->isNameFilter($query->entity, $filter),
        ));

        $idField = $query->entity === 'customers' ? 'id' : 'customer_id';

        foreach ($nameFilters as $filter) {
            $name = is_scalar($filter->value) ? (string) $filter->value : '';
            $first = $this->firstMatch($filter);

            if ($first === null) {
                return new EntityResolution(
                    outcome: EntityResolutionOutcome::NotFound,
                    query: $query,
                    message: "no customer matching '{$name}'",
                );
            }

            $remaining[] = new Filter($idField, Operator::Equals->value, $first->id);
        }

        return new EntityResolution(
            outcome: EntityResolutionOutcome::Resolved,
            query: $query->withFilters($remaining),
        );
    }

    private function isNameFilter(string $entity, Filter $filter): bool
    {
        return match ($entity) {
            'customers' => $filter->field === 'name',
            default => $filter->field === 'customer_name',
        };
    }

    private function firstMatch(Filter $filter): ?Customer
    {
        $name = is_scalar($filter->value) ? (string) $filter->value : '';

        return Customer::query()
            ->withTrashed()
            ->when(
                $filter->operator === Operator::Contains->value,
                fn ($builder) => $builder->where('name', 'like', '%'.$name.'%'),
                fn ($builder) => $builder->where('name', $name),
            )
            ->orderBy('id')
            ->first();
    }
}
