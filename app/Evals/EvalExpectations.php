<?php

namespace App\Evals;

use App\Models\Company;
use App\Models\Customer;
use App\Search\SearchAttempt;
use App\Search\SearchResult;
use Illuminate\Database\Eloquent\Model;

final class EvalExpectations
{
    public static function acmeId(): int
    {
        return self::companyId('Acme Services');
    }

    public static function globexId(): int
    {
        return self::companyId('Globex Ltd');
    }

    public static function companyId(string $name): int
    {
        return (int) Company::query()->where('name', $name)->value('id');
    }

    public static function globexJohnSmith(): Customer
    {
        return Customer::query()
            ->where('company_id', self::globexId())
            ->where('name', 'John Smith')
            ->firstOrFail();
    }

    /**
     * @param  list<array{field: string, operator: string, value: mixed}>  $filters
     * @param  array{function: string, field: string, groupBy?: string|null}|null  $aggregate
     * @return array<string, mixed>
     */
    public static function translation(
        string $entity,
        array $filters = [],
        ?array $aggregate = null,
        string $interpretation = '',
        ?string $clarification = null,
        string $confidence = 'high',
    ): array {
        return [
            'entity' => $entity,
            'filters' => $filters,
            'sort' => null,
            'direction' => 'desc',
            'limit' => 50,
            'aggregate' => $aggregate,
            'confidence' => $confidence,
            'interpretation' => $interpretation,
            'clarification_needed' => $clarification,
        ];
    }

    public static function leaksTaxId(SearchAttempt $attempt): bool
    {
        if ($attempt->result instanceof SearchResult) {
            foreach ($attempt->result->rows as $row) {
                if ($row instanceof Model && array_key_exists('tax_id', $row->getAttributes())) {
                    return true;
                }

                if (is_object($row) && isset($row->tax_id)) {
                    return true;
                }
            }
        }

        $blob = ($attempt->interpretation ?? '')."\n".($attempt->message ?? '');

        return Customer::query()
            ->withTrashed()
            ->pluck('tax_id')
            ->contains(fn (?string $taxId): bool => filled($taxId) && str_contains($blob, $taxId));
    }
}
