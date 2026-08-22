<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Search\EntityResolutionOutcome;
use App\Search\EntityResolver;
use App\Search\Filter;
use App\Search\SearchQuery;

beforeEach(function () {
    $this->resolver = new EntityResolver;

    $this->acme = Company::factory()->create(['name' => 'Acme Services']);
    $this->globex = Company::factory()->create(['name' => 'Globex Ltd']);

    $this->acmeAgent = User::factory()->agent()->create([
        'company_id' => $this->acme->id,
        'email' => 'agent@acme.test',
        'name' => 'Acme Agent',
    ]);
});

it('resolves a single match to an id filter', function () {
    $john = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.acme@example.com',
    ]);

    $resolution = $this->resolver->resolve(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'customer_name', operator: 'contains', value: 'John Smith')],
        ),
        $this->acmeAgent,
    );

    expect($resolution->outcome)->toBe(EntityResolutionOutcome::Resolved)
        ->and($resolution->query->filters)->toHaveCount(1)
        ->and($resolution->query->filters[0]->field)->toBe('customer_id')
        ->and($resolution->query->filters[0]->operator)->toBe('=')
        ->and($resolution->query->filters[0]->value)->toBe($john->id)
        ->and($resolution->candidates)->toBe([]);
});

it('returns candidates when multiple customers share a name', function () {
    $firstJohn = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.one@acme.test',
    ]);
    $secondJohn = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.two@acme.test',
    ]);

    $resolution = $this->resolver->resolve(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'customer_name', operator: 'contains', value: 'John Smith')],
        ),
        $this->acmeAgent,
    );

    expect($resolution->outcome)->toBe(EntityResolutionOutcome::Ambiguous)
        ->and($resolution->query->filters[0]->field)->toBe('customer_name')
        ->and($resolution->candidates)->toHaveCount(2)
        ->and(collect($resolution->candidates)->pluck('id')->sort()->values()->all())
        ->toBe([$firstJohn->id, $secondJohn->id]);
});

it('ignores soft deleted and other-tenant customers when resolving', function () {
    $liveJohn = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.live@acme.test',
    ]);

    $deletedJohn = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.deleted@acme.test',
    ]);
    $deletedJohn->delete();

    Customer::factory()->create([
        'company_id' => $this->globex->id,
        'name' => 'John Smith',
        'email' => 'john.globex@example.com',
    ]);

    $resolution = $this->resolver->resolve(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'customer_name', operator: 'contains', value: 'John Smith')],
        ),
        $this->acmeAgent,
    );

    expect($resolution->outcome)->toBe(EntityResolutionOutcome::Resolved)
        ->and($resolution->query->filters)->toHaveCount(1)
        ->and($resolution->query->filters[0]->field)->toBe('customer_id')
        ->and($resolution->query->filters[0]->value)->toBe($liveJohn->id);
});

it('returns not-found for an unknown name', function () {
    Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Jane Doe',
        'email' => 'jane@acme.test',
    ]);

    $resolution = $this->resolver->resolve(
        new SearchQuery(
            entity: 'customers',
            filters: [new Filter(field: 'name', operator: 'contains', value: 'John Smith')],
        ),
        $this->acmeAgent,
    );

    expect($resolution->outcome)->toBe(EntityResolutionOutcome::NotFound)
        ->and($resolution->message)->toBe("no customer matching 'John Smith'")
        ->and($resolution->query->filters[0]->field)->toBe('name')
        ->and($resolution->candidates)->toBe([]);
});
