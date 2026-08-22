<?php

namespace App\Search;

use App\Models\Customer;
use App\Models\User;

final class TenantGuard
{
    public function wouldAccessOtherTenant(SearchQuery $query, User $actor): bool
    {
        if ($actor->company_id === null) {
            return true;
        }

        foreach ($query->filters as $filter) {
            if ($filter->field === 'company_id' && $this->containsForeignId($filter->value, $actor->company_id)) {
                return true;
            }

            if ($this->isCustomerIdentityFilter($query, $filter) && $this->referencesForeignCustomer($filter, $actor)) {
                return true;
            }
        }

        return false;
    }

    private function isCustomerIdentityFilter(SearchQuery $query, Filter $filter): bool
    {
        return $filter->field === 'customer_id'
            || ($query->entity === 'customers' && $filter->field === 'id');
    }

    private function referencesForeignCustomer(Filter $filter, User $actor): bool
    {
        $ids = $this->integerIds($filter->value);

        if ($ids === []) {
            return false;
        }

        return Customer::query()
            ->withTrashed()
            ->whereIn('id', $ids)
            ->where('company_id', '!=', $actor->company_id)
            ->exists();
    }

    private function containsForeignId(mixed $value, int $companyId): bool
    {
        foreach ($this->integerIds($value) as $id) {
            if ($id !== $companyId) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function integerIds(mixed $value): array
    {
        $values = is_array($value) ? array_values($value) : [$value];
        $ids = [];

        foreach ($values as $item) {
            if (is_int($item) || (is_string($item) && preg_match('/^-?\d+$/', $item) === 1)) {
                $ids[] = (int) $item;
            }
        }

        return $ids;
    }
}
