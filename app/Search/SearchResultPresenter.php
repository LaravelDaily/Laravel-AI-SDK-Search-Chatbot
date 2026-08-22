<?php

namespace App\Search;

use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

final class SearchResultPresenter
{
    /**
     * @return array{
     *     headings: list<string>,
     *     rows: list<list<string>>,
     *     total: int,
     *     truncated: bool
     * }
     */
    public static function present(SearchResult $result, User $actor): array
    {
        if ($result->appliedQuery->aggregate !== null) {
            return self::withMeta($result, self::presentAggregate($result));
        }

        $table = match ($result->appliedQuery->entity) {
            'customers' => self::presentCustomers($result, $actor),
            'invoices' => self::presentInvoices($result),
            default => self::presentServiceRequests($result),
        };

        return self::withMeta($result, $table);
    }

    public static function formatFilterValue(mixed $value): string
    {
        if (is_array($value)) {
            return implode(' – ', array_map(
                static fn (mixed $item): string => self::display($item),
                array_values($value),
            ));
        }

        return self::display($value);
    }

    /**
     * @param  array{headings: list<string>, rows: list<list<string>>}  $table
     * @return array{headings: list<string>, rows: list<list<string>>, total: int, truncated: bool}
     */
    private static function withMeta(SearchResult $result, array $table): array
    {
        return [
            ...$table,
            'total' => $result->total,
            'truncated' => $result->truncated,
        ];
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string>>}
     */
    private static function presentServiceRequests(SearchResult $result): array
    {
        return [
            'headings' => ['ID', 'Title', 'Status', 'Priority', 'Customer', 'Opened'],
            'rows' => $result->rows->map(fn (mixed $row): array => [
                self::display(self::value($row, 'id')),
                self::display(self::value($row, 'title')),
                self::display(self::value($row, 'status')),
                self::display(self::value($row, 'priority')),
                self::display(self::value($row, 'customer.name')),
                self::display(self::value($row, 'opened_at')),
            ])->all(),
        ];
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string>>}
     */
    private static function presentCustomers(SearchResult $result, User $actor): array
    {
        $headings = ['ID', 'Name', 'Email', 'Status'];
        $includeTaxId = $actor->isAdmin();

        if ($includeTaxId) {
            $headings[] = 'Tax ID';
        }

        return [
            'headings' => $headings,
            'rows' => $result->rows->map(function (mixed $row) use ($includeTaxId): array {
                $cells = [
                    self::display(self::value($row, 'id')),
                    self::display(self::value($row, 'name')),
                    self::display(self::value($row, 'email')),
                    self::display(self::value($row, 'status')),
                ];

                if ($includeTaxId) {
                    $cells[] = self::display(self::value($row, 'tax_id'));
                }

                return $cells;
            })->all(),
        ];
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string>>}
     */
    private static function presentInvoices(SearchResult $result): array
    {
        return [
            'headings' => ['ID', 'Customer', 'Total', 'Issued', 'Paid'],
            'rows' => $result->rows->map(fn (mixed $row): array => [
                self::display(self::value($row, 'id')),
                self::display(self::value($row, 'customer.name')),
                self::moneyFromCents(self::value($row, 'total_cents')),
                self::display(self::value($row, 'issued_at')),
                self::display(self::value($row, 'paid_at')),
            ])->all(),
        ];
    }

    /**
     * @return array{headings: list<string>, rows: list<list<string>>}
     */
    private static function presentAggregate(SearchResult $result): array
    {
        $aggregate = $result->appliedQuery->aggregate;

        if ($aggregate === null) {
            return ['headings' => [], 'rows' => []];
        }

        $aggregateHeading = strtoupper($aggregate->function).'('.$aggregate->field.')';

        if ($aggregate->groupBy === null) {
            return [
                'headings' => [$aggregateHeading],
                'rows' => $result->rows->map(fn (mixed $row): array => [
                    self::display(self::value($row, 'aggregate')),
                ])->all(),
            ];
        }

        return [
            'headings' => [str($aggregate->groupBy)->headline()->toString(), $aggregateHeading],
            'rows' => $result->rows->map(fn (mixed $row): array => [
                self::display(self::value($row, $aggregate->groupBy)),
                self::display(self::value($row, 'aggregate')),
            ])->all(),
        ];
    }

    private static function value(mixed $row, string $key): mixed
    {
        if ($row instanceof Model) {
            return data_get($row, $key);
        }

        if (is_array($row)) {
            return data_get($row, $key);
        }

        if (is_object($row)) {
            return data_get($row, $key);
        }

        return null;
    }

    private static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDayDateTimeString();
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return (string) json_encode($value);
    }

    private static function moneyFromCents(mixed $cents): string
    {
        if (! is_numeric($cents)) {
            return '—';
        }

        return '$'.number_format(((int) $cents) / 100, 2);
    }
}
