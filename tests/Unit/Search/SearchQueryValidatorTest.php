<?php

use App\Search\Aggregate;
use App\Search\Filter;
use App\Search\InvalidSearchQuery;
use App\Search\SearchQuery;
use App\Search\SearchQueryValidator;

beforeEach(function () {
    $this->validator = new SearchQueryValidator;
});

it('rejects unknown entity / unknown field / denied field', function (SearchQuery $query, string $expected) {
    expect(fn () => $this->validator->validate($query))
        ->toThrow(InvalidSearchQuery::class, $expected);
})->with([
    'unknown entity' => [
        new SearchQuery(entity: 'widgets'),
        "Unknown entity 'widgets'. Allowed entities: service_requests, customers, invoices.",
    ],
    'unknown field' => [
        new SearchQuery(
            entity: 'customers',
            filters: [new Filter(field: 'is_vip', operator: '=', value: true)],
        ),
        "Unknown field 'is_vip' on entity 'customers'.",
    ],
    'denied field tax_id' => [
        new SearchQuery(
            entity: 'customers',
            filters: [new Filter(field: 'tax_id', operator: '=', value: '12-3456789')],
        ),
        "Field 'tax_id' is not allowed.",
    ],
]);

it('rejects contains on an integer field, > on an enum field', function (SearchQuery $query, string $expected) {
    expect(fn () => $this->validator->validate($query))
        ->toThrow(InvalidSearchQuery::class, $expected);
})->with([
    'contains on integer' => [
        new SearchQuery(
            entity: 'invoices',
            filters: [new Filter(field: 'total_cents', operator: 'contains', value: '100')],
        ),
        "Operator 'contains' is not allowed for integer field 'total_cents'.",
    ],
    'greater than on enum' => [
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'status', operator: '>', value: 'open')],
        ),
        "Operator '>' is not allowed for enum field 'status'.",
    ],
]);

it('rejects limit above 200 and negative limit', function (int $limit, string $expected) {
    $query = new SearchQuery(entity: 'service_requests', limit: $limit);

    expect(fn () => $this->validator->validate($query))
        ->toThrow(InvalidSearchQuery::class, $expected);
})->with([
    'above max' => [201, 'Limit cannot exceed 200.'],
    'negative' => [-1, 'Limit must be at least 1.'],
    'zero' => [0, 'Limit must be at least 1.'],
]);

it('rejects malformed dates', function () {
    $query = new SearchQuery(
        entity: 'service_requests',
        filters: [new Filter(field: 'opened_at', operator: '=', value: 'not-a-date')],
    );

    expect(fn () => $this->validator->validate($query))
        ->toThrow(InvalidSearchQuery::class, "Invalid date value 'not-a-date' for field 'opened_at'.");
});

it('rejects unknown sort keys', function () {
    $query = new SearchQuery(
        entity: 'service_requests',
        sort: 'legacy_flag2',
    );

    expect(fn () => $this->validator->validate($query))
        ->toThrow(InvalidSearchQuery::class, "Unknown sort key 'legacy_flag2' on entity 'service_requests'.");
});

it('accepts a canonical valid query', function () {
    $query = new SearchQuery(
        entity: 'service_requests',
        filters: [
            new Filter(field: 'status', operator: '=', value: 'open'),
            new Filter(field: 'priority', operator: 'in', value: ['high', 'urgent']),
            new Filter(field: 'title', operator: 'contains', value: 'heater'),
            new Filter(field: 'opened_at', operator: 'between', value: ['2025-01-01', '2025-12-31']),
            new Filter(field: 'customer_name', operator: 'contains', value: 'John Smith'),
        ],
        sort: 'opened_at',
        direction: 'desc',
        limit: 50,
    );

    expect($this->validator->validate($query))->toBe($query);
});

it('accepts a valid aggregate count query', function () {
    $query = new SearchQuery(
        entity: 'service_requests',
        filters: [new Filter(field: 'status', operator: '=', value: 'open')],
        aggregate: new Aggregate(function: 'count', field: 'id'),
    );

    expect($this->validator->validate($query))->toBe($query);
});
