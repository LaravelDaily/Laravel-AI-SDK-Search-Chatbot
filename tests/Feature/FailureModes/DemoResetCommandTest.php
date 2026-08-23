<?php

use App\Models\AiSearchLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;

it('reseeds the database to the deterministic demo state', function () {
    $this->freezeTime();
    $this->seed(DemoDataSeeder::class);

    $customerNames = Customer::query()->withTrashed()->orderBy('name')->orderBy('id')->pluck('name')->all();
    $requestCount = ServiceRequest::query()->withTrashed()->count();
    $acme = Company::query()->where('name', 'Acme Services')->firstOrFail();
    $agent = User::query()->where('email', 'acme-agent@example.com')->firstOrFail();

    Customer::factory()->create([
        'company_id' => $acme->id,
        'name' => 'Temporary Extra Customer',
    ]);
    User::factory()->create([
        'company_id' => $acme->id,
        'email' => 'extra-take@example.com',
    ]);
    AiSearchLog::factory()->create([
        'user_id' => $agent->id,
        'company_id' => $acme->id,
    ]);

    $this->artisan('demo:reset')
        ->expectsOutputToContain('Demo data reset to the known seeded state.')
        ->expectsOutputToContain('acme-agent@example.com')
        ->assertSuccessful();

    expect(Customer::query()->withTrashed()->where('name', 'Temporary Extra Customer')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'extra-take@example.com')->exists())->toBeFalse()
        ->and(AiSearchLog::query()->count())->toBe(0)
        ->and(User::query()->where('email', 'acme-agent@example.com')->exists())->toBeTrue()
        ->and(Company::query()->orderBy('name')->pluck('name')->all())->toBe(['Acme Services', 'Globex Ltd'])
        ->and(Customer::query()->withTrashed()->orderBy('name')->orderBy('id')->pluck('name')->all())->toBe($customerNames)
        ->and(ServiceRequest::query()->withTrashed()->count())->toBe($requestCount);
});

it('seeds demo data when the database is empty', function () {
    $this->artisan('demo:reset')
        ->expectsOutputToContain('Demo data reset to the known seeded state.')
        ->assertSuccessful();

    expect(Company::query()->where('name', 'Acme Services')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'acme-agent@example.com')->exists())->toBeTrue();
});

it('refuses to run in production', function () {
    $this->app['env'] = 'production';

    $this->artisan('demo:reset')
        ->expectsOutputToContain('cannot run in production')
        ->assertFailed();

    expect(Company::query()->where('name', 'Acme Services')->exists())->toBeFalse();
});
