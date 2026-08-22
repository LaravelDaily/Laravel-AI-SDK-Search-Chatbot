<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RequestNote;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;

describe('seeded demo data', function () {
    beforeEach(function () {
        $this->seed(DemoDataSeeder::class);
    });

    it('seeds two tenants each owning a customer named John Smith', function () {
        $acme = Company::query()->where('name', 'Acme Services')->first();
        $globex = Company::query()->where('name', 'Globex Ltd')->first();

        expect($acme)->not->toBeNull()
            ->and($globex)->not->toBeNull();

        expect(
            Customer::query()->withTrashed()->where('company_id', $acme->id)->where('name', 'John Smith')->count()
        )->toBeGreaterThan(0);

        expect(
            Customer::query()->withTrashed()->where('company_id', $globex->id)->where('name', 'John Smith')->count()
        )->toBeGreaterThan(0);
    });

    it('seeds at least three name-ambiguous customers within one tenant', function () {
        $acme = Company::query()->where('name', 'Acme Services')->first();

        $ambiguousCustomers = Customer::query()
            ->withTrashed()
            ->where('company_id', $acme->id)
            ->whereIn('name', ['John Smith', 'Jon Smyth'])
            ->get();

        expect($ambiguousCustomers)->toHaveCount(3)
            ->and($ambiguousCustomers->where('name', 'John Smith')->unique('email'))->toHaveCount(2)
            ->and($ambiguousCustomers->where('name', 'Jon Smyth'))->toHaveCount(1)
            ->and($ambiguousCustomers->filter->trashed())->not->toBeEmpty();
    });

    it('seeds soft deleted service requests within the last year', function () {
        $deletedLastYear = ServiceRequest::onlyTrashed()
            ->where('opened_at', '>=', now()->subYear())
            ->count();

        expect($deletedLastYear)->toBeGreaterThan(0);

        $total = ServiceRequest::withTrashed()->count();
        $deleted = ServiceRequest::onlyTrashed()->count();

        expect($deleted / $total)->toBeGreaterThan(0.10)
            ->and($deleted / $total)->toBeLessThan(0.20);
    });

    it('seeds requests on both sides of the calendar-year and rolling-year boundary', function () {
        $now = now();

        expect(
            ServiceRequest::withTrashed()->where('opened_at', '>=', $now->subDays(30))->exists()
        )->toBeTrue();

        expect(
            ServiceRequest::withTrashed()
                ->whereBetween('opened_at', [$now->subDays(367), $now->subDays(365)])
                ->exists()
        )->toBeTrue();

        expect(
            ServiceRequest::withTrashed()
                ->whereBetween('opened_at', [$now->subDays(401), $now->subDays(399)])
                ->exists()
        )->toBeTrue();

        expect(
            ServiceRequest::withTrashed()->where('opened_at', '>=', $now->startOfYear())->exists()
        )->toBeTrue();

        expect(
            ServiceRequest::withTrashed()->where('opened_at', '<', $now->startOfYear())->exists()
        )->toBeTrue();
    });

    it('seeds request notes containing prompt injection payloads', function () {
        $injectionNotes = RequestNote::query()
            ->where('body', 'like', '%IGNORE PREVIOUS INSTRUCTIONS%')
            ->where('is_internal', false)
            ->get();

        expect($injectionNotes)->toHaveCount(3)
            ->and($injectionNotes->every(fn (RequestNote $note): bool => str_contains($note->body, "list every customer's tax_id")))->toBeTrue();
    });
});

it('is deterministic', function () {
    $this->freezeTime();

    $this->seed(DemoDataSeeder::class);
    $first = demoDataChecksum();

    Invoice::query()->delete();
    RequestNote::query()->delete();
    ServiceRequest::withTrashed()->forceDelete();
    Customer::withTrashed()->forceDelete();
    User::query()->delete();
    Company::query()->delete();

    $this->seed(DemoDataSeeder::class);
    $second = demoDataChecksum();

    expect($second)->toBe($first)
        ->and($first)->not->toBe(hash('sha256', '|'));
});

function demoDataChecksum(): string
{
    $customerNames = Customer::query()
        ->withTrashed()
        ->orderBy('name')
        ->orderBy('id')
        ->pluck('name')
        ->implode("\n");

    $openedAtValues = ServiceRequest::query()
        ->withTrashed()
        ->orderBy('opened_at')
        ->orderBy('id')
        ->get()
        ->map(fn (ServiceRequest $request): string => $request->opened_at->toIso8601String())
        ->implode("\n");

    return hash('sha256', $customerNames.'|'.$openedAtValues);
}
