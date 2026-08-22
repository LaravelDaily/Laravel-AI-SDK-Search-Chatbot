<?php

use App\Models\Company;
use App\Search\Naive\FailureShowcase;
use Database\Seeders\DemoDataSeeder;

it('prints a before and after table for each failure mode', function () {
    $this->travelTo('2026-08-22 09:00:00');
    $this->seed(DemoDataSeeder::class);

    $rows = array_map(
        fn ($comparison): array => [$comparison->mode, $comparison->naive, $comparison->guarded],
        app(FailureShowcase::class)->comparisons(),
    );

    $this->artisan('demo:failures')
        ->expectsOutputToContain('Naive vs guarded search')
        ->expectsTable(['Failure mode', 'Naive', 'Guarded'], $rows)
        ->assertSuccessful();
});

it('fails when demo data has not been seeded', function () {
    $this->artisan('demo:failures')
        ->expectsOutputToContain('No demo data found')
        ->assertFailed();

    expect(Company::query()->where('name', 'Acme Services')->exists())->toBeFalse();
});
