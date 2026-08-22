<?php

namespace App\Search;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class EntityResolver
{
    public function resolve(SearchQuery $query, User $actor): EntityResolution
    {
        $nameFilters = array_values(array_filter(
            $query->filters,
            fn (Filter $filter): bool => $this->isNameFilter($query->entity, $filter),
        ));

        if ($nameFilters === [] || $actor->company_id === null) {
            return new EntityResolution(EntityResolutionOutcome::Unchanged, $query);
        }

        $remaining = array_values(array_filter(
            $query->filters,
            fn (Filter $filter): bool => ! $this->isNameFilter($query->entity, $filter),
        ));

        $idField = $query->entity === 'customers' ? 'id' : 'customer_id';

        foreach ($nameFilters as $filter) {
            $name = is_scalar($filter->value) ? (string) $filter->value : '';
            $candidates = $this->findCustomers($actor, $filter);

            if ($candidates->isEmpty()) {
                return new EntityResolution(
                    outcome: EntityResolutionOutcome::NotFound,
                    query: $query,
                    message: "no customer matching '{$name}'",
                );
            }

            if ($candidates->count() > 1) {
                return new EntityResolution(
                    outcome: EntityResolutionOutcome::Ambiguous,
                    query: $query,
                    message: "Multiple customers match '{$name}'.",
                    candidates: $this->candidatePayloads($candidates),
                );
            }

            $remaining[] = new Filter($idField, Operator::Equals->value, $candidates->first()->id);
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

    /**
     * @return Collection<int, Customer>
     */
    private function findCustomers(User $actor, Filter $filter): Collection
    {
        $name = is_scalar($filter->value) ? (string) $filter->value : '';

        return Customer::query()
            ->where('company_id', $actor->company_id)
            ->when(
                $filter->operator === Operator::Contains->value,
                fn (Builder $builder) => $builder->where('name', 'like', '%'.addcslashes($name, '%_\\').'%'),
                fn (Builder $builder) => $builder->where('name', $name),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Customer>  $customers
     * @return list<array{id: int, name: string, email: string|null}>
     */
    private function candidatePayloads(Collection $customers): array
    {
        $payloads = [];

        foreach ($customers as $customer) {
            $payloads[] = [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
            ];
        }

        return $payloads;
    }
}
