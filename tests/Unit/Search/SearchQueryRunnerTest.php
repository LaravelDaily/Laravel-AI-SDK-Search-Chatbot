<?php

use App\Enums\ServiceRequestStatus;
use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Search\Aggregate;
use App\Search\Filter;
use App\Search\SearchQuery;
use App\Search\SearchQueryRunner;

beforeEach(function () {
    $this->runner = new SearchQueryRunner;

    $this->acme = Company::factory()->create(['name' => 'Acme Services']);
    $this->globex = Company::factory()->create(['name' => 'Globex Ltd']);

    $this->acmeAgent = User::factory()->agent()->create([
        'company_id' => $this->acme->id,
        'email' => 'agent@acme.test',
        'name' => 'Acme Agent',
    ]);
    $this->acmeAdmin = User::factory()->admin()->create([
        'company_id' => $this->acme->id,
        'email' => 'admin@acme.test',
        'name' => 'Acme Admin',
    ]);
    $this->acmeViewer = User::factory()->viewer()->create([
        'company_id' => $this->acme->id,
        'email' => 'viewer@acme.test',
        'name' => 'Acme Viewer',
    ]);
});

it('never returns rows from another tenant', function () {
    $acmeCustomer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.acme@example.com',
    ]);
    $globexCustomer = Customer::factory()->create([
        'company_id' => $this->globex->id,
        'name' => 'John Smith',
        'email' => 'john.globex@example.com',
    ]);

    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $acmeCustomer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Acme heater repair',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->globex->id,
        'customer_id' => $globexCustomer->id,
        'assigned_user_id' => User::factory()->create([
            'company_id' => $this->globex->id,
            'email' => 'agent@globex.test',
        ])->id,
        'title' => 'Globex heater repair',
    ]);

    $result = $this->runner->run(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'company_id', operator: '=', value: $this->globex->id)],
        ),
        $this->acmeAgent,
    );

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows->pluck('company_id')->unique()->all())->toBe([$this->acme->id])
        ->and($result->rows->first()->title)->toBe('Acme heater repair');
});

it('excludes soft deleted rows even when the filter set is empty', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Jane Doe',
        'email' => 'jane.doe@example.com',
    ]);

    $visibleRequest = ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Visible boiler leak',
    ]);
    $deletedRequest = ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Deleted furnace failure',
    ]);
    $deletedRequest->delete();

    $result = $this->runner->run(
        new SearchQuery(entity: 'service_requests'),
        $this->acmeAgent,
    );

    expect($result->rows)->toHaveCount(1)
        ->and($result->total)->toBe(1)
        ->and($result->rows->first()->id)->toBe($visibleRequest->id)
        ->and($result->rows->pluck('id'))->not->toContain($deletedRequest->id);
});

it('clamps limit to 200', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Limit Customer',
        'email' => 'limit@example.com',
    ]);

    ServiceRequest::factory()
        ->count(3)
        ->create([
            'company_id' => $this->acme->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $this->acmeAgent->id,
        ]);

    $result = $this->runner->run(
        new SearchQuery(entity: 'service_requests', limit: 500),
        $this->acmeAgent,
    );

    expect($result->appliedQuery->limit)->toBe(200)
        ->and($result->sql)->toMatch('/limit\s+200/i')
        ->and($result->rows)->toHaveCount(3);
});

it('omits tax_id for non-admin actors and includes it for admins', function () {
    Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'PII Customer',
        'email' => 'pii@example.com',
        'tax_id' => '98-7654321',
    ]);

    $viewerResult = $this->runner->run(
        new SearchQuery(entity: 'customers'),
        $this->acmeViewer,
    );
    $adminResult = $this->runner->run(
        new SearchQuery(entity: 'customers'),
        $this->acmeAdmin,
    );

    $viewerCustomer = $viewerResult->rows->first();
    $adminCustomer = $adminResult->rows->first();

    expect($viewerCustomer->getAttributes())->not->toHaveKey('tax_id')
        ->and($viewerCustomer->toArray())->not->toHaveKey('tax_id')
        ->and($adminCustomer->getAttributes())->toHaveKey('tax_id')
        ->and($adminCustomer->toArray()['tax_id'])->toBe('98-7654321')
        ->and($this->acmeViewer->role)->toBe(UserRole::Viewer)
        ->and($this->acmeAdmin->role)->toBe(UserRole::Admin);
});

it('applies date between filters inclusive of boundaries', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Boundary Customer',
        'email' => 'boundary@example.com',
    ]);

    $startBoundary = ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Opened on start boundary',
        'opened_at' => '2025-01-01 00:00:00',
    ]);
    $endBoundary = ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Opened on end boundary',
        'opened_at' => '2025-12-31 15:30:00',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Opened before range',
        'opened_at' => '2024-12-31 23:59:59',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'title' => 'Opened after range',
        'opened_at' => '2026-01-01 00:00:00',
    ]);

    $result = $this->runner->run(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'opened_at', operator: 'between', value: ['2025-01-01', '2025-12-31'])],
        ),
        $this->acmeAgent,
    );

    expect($result->rows->pluck('id')->sort()->values()->all())
        ->toBe(collect([$startBoundary->id, $endBoundary->id])->sort()->values()->all())
        ->and($result->total)->toBe(2);
});

it('returns aggregate counts matching a hand-written query', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Aggregate Customer',
        'email' => 'aggregate@example.com',
    ]);

    ServiceRequest::factory()->count(3)->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'status' => ServiceRequestStatus::Open,
    ]);
    ServiceRequest::factory()->count(2)->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->acmeAgent->id,
        'status' => ServiceRequestStatus::Resolved,
    ]);

    $result = $this->runner->run(
        new SearchQuery(
            entity: 'service_requests',
            filters: [new Filter(field: 'status', operator: '=', value: 'open')],
            aggregate: new Aggregate(function: 'count', field: 'id'),
        ),
        $this->acmeAgent,
    );

    $expected = ServiceRequest::query()
        ->where('company_id', $this->acmeAgent->company_id)
        ->where('status', ServiceRequestStatus::Open)
        ->count();

    expect($result->total)->toBe($expected)
        ->and($result->total)->toBe(3)
        ->and($result->rows->first()->aggregate)->toBe(3);
});

it('flags truncated when more rows exist than the limit', function () {
    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Truncation Customer',
        'email' => 'truncation@example.com',
    ]);

    ServiceRequest::factory()
        ->count(5)
        ->create([
            'company_id' => $this->acme->id,
            'customer_id' => $customer->id,
            'assigned_user_id' => $this->acmeAgent->id,
        ]);

    $result = $this->runner->run(
        new SearchQuery(entity: 'service_requests', limit: 3),
        $this->acmeAgent,
    );

    expect($result->truncated)->toBeTrue()
        ->and($result->total)->toBe(5)
        ->and($result->rows)->toHaveCount(3);
});
