<?php

use App\Ai\Agents\SearchQueryTranslator;
use App\Ai\TranslationFailed;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Search\EntityResolutionOutcome;
use App\Search\EntityResolver;
use App\Search\Naive\FailureShowcase;
use App\Search\Naive\NaiveEntityResolver;
use App\Search\Naive\NaiveQueryRunner;
use App\Search\SearchQueryRunner;
use App\Search\SearchSchema;
use App\Search\SearchService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Database\QueryException;

beforeEach(function () {
    $this->travelTo('2026-08-22 09:00:00');
    $this->seed(DemoDataSeeder::class);

    $this->showcase = app(FailureShowcase::class);
    $this->naiveRunner = app(NaiveQueryRunner::class);
    $this->realRunner = app(SearchQueryRunner::class);
    $this->naiveResolver = app(NaiveEntityResolver::class);
    $this->realResolver = app(EntityResolver::class);
    $this->acmeAgent = $this->showcase->acmeAgent();
    $this->acmeViewer = $this->showcase->acmeViewer();
});

it('soft deletes — naive count is higher than real count for requests last year', function () {
    $query = FailureShowcase::lastYearQuery();

    $naive = $this->naiveRunner->run($query, $this->acmeAgent);
    $real = $this->realRunner->run($query, $this->acmeAgent);
    $handwritten = $this->showcase->handwrittenLastYearCount($this->acmeAgent);

    expect($naive->total)->toBeGreaterThan($real->total)
        ->and($real->total)->toBe($handwritten)
        ->and($handwritten)->toBe(
            ServiceRequest::query()
                ->where('company_id', $this->acmeAgent->company_id)
                ->whereNull('deleted_at')
                ->whereBetween('opened_at', [now()->subYear()->startOfDay(), now()->endOfDay()])
                ->count()
        );
});

it('tenant leak — naive returns Globex John Smith rows for an Acme user; real returns zero', function () {
    $globexJohn = $this->showcase->globexJohnSmith();
    $query = FailureShowcase::globexCustomerQuery($globexJohn->id);

    $naive = $this->naiveRunner->run($query, $this->acmeAgent);
    $real = $this->realRunner->run($query, $this->acmeAgent);

    expect($naive->total)->toBe(1)
        ->and($naive->rows->first())->toBeInstanceOf(Customer::class)
        ->and($naive->rows->first()->id)->toBe($globexJohn->id)
        ->and($naive->rows->first()->company_id)->not->toBe($this->acmeAgent->company_id)
        ->and($real->total)->toBe(0)
        ->and($real->rows)->toHaveCount(0);
});

it('PII exposure — naive result rows contain tax_id; real rows do not for a viewer', function () {
    $query = FailureShowcase::customersQuery();

    $naive = $this->naiveRunner->run($query, $this->acmeViewer);
    $real = $this->realRunner->run($query, $this->acmeViewer);

    $naiveCustomer = $naive->rows->first();
    $realCustomer = $real->rows->first();

    expect($this->acmeViewer->role)->toBe(UserRole::Viewer)
        ->and($naiveCustomer->getAttributes())->toHaveKey('tax_id')
        ->and($naiveCustomer->toArray())->toHaveKey('tax_id')
        ->and($realCustomer->getAttributes())->not->toHaveKey('tax_id')
        ->and($realCustomer->toArray())->not->toHaveKey('tax_id');
});

it('ambiguous entity — naive silently picks the first John Smith; real returns candidates', function () {
    $ambiguous = Customer::query()
        ->withTrashed()
        ->where('company_id', $this->acmeAgent->company_id)
        ->whereIn('name', ['John Smith', 'Jon Smyth'])
        ->orderBy('id')
        ->get();

    expect($ambiguous)->toHaveCount(3);

    $query = FailureShowcase::johnSmithQuery();
    $naive = $this->naiveResolver->resolve($query, $this->acmeAgent);
    $real = $this->realResolver->resolve($query, $this->acmeAgent);

    $liveJohns = $ambiguous->where('name', 'John Smith')->whereNull('deleted_at')->values();

    expect($naive->outcome)->toBe(EntityResolutionOutcome::Resolved)
        ->and($naive->query->filters)->toHaveCount(1)
        ->and($naive->query->filters[0]->field)->toBe('customer_id')
        ->and($naive->query->filters[0]->value)->toBe($ambiguous->first()->id)
        ->and($real->outcome)->toBe(EntityResolutionOutcome::Ambiguous)
        ->and($real->candidates)->toHaveCount($liveJohns->count())
        ->and($real->candidates)->toHaveCount(2)
        ->and(collect($real->candidates)->pluck('id')->sort()->values()->all())
        ->toBe($liveJohns->pluck('id')->sort()->values()->all());
});

it('rolling vs calendar year — the two interpretations return different counts and state which was used', function () {
    SearchQueryTranslator::fake([
        FailureShowcase::rollingYearTranslatorOutput(),
        FailureShowcase::calendarYearTranslatorOutput(),
    ])->preventStrayPrompts();

    $service = app(SearchService::class);

    $rolling = $service->search("show me last year's requests", $this->acmeAgent);
    $calendar = $service->search('show me requests in calendar year 2026', $this->acmeAgent);

    expect($rolling->interpretation)->toContain('rolling 12 months')
        ->and($calendar->interpretation)->toContain('calendar year 2026')
        ->and($rolling->result)->not->toBeNull()
        ->and($calendar->result)->not->toBeNull()
        ->and($rolling->result->total)->not->toBe($calendar->result->total)
        ->and($rolling->result->total)->toBe(
            $this->realRunner->run(FailureShowcase::rollingYearQuery(), $this->acmeAgent)->total
        )
        ->and($calendar->result->total)->toBe(
            $this->realRunner->run(FailureShowcase::calendarYearQuery(), $this->acmeAgent)->total
        );
});

it('hallucinated field — naive throws a raw SQL error; real returns a clean validation failure and one repair attempt', function () {
    SearchQueryTranslator::fake([
        FailureShowcase::hallucinatedVipTranslatorOutput(),
        FailureShowcase::hallucinatedVipTranslatorOutput(),
    ])->preventStrayPrompts();

    expect(fn () => $this->naiveRunner->run(FailureShowcase::hallucinatedVipQuery(), $this->acmeAgent))
        ->toThrow(QueryException::class, 'is_vip');

    $real = $this->showcase->translateHallucinatedField($this->acmeAgent);

    expect($real)->toBeInstanceOf(TranslationFailed::class)
        ->and($real->reason)->toContain("Unknown field 'is_vip'")
        ->and($real->attempts)->toBe(2);

    SearchQueryTranslator::assertPrompted(fn ($prompt) => $prompt->contains('unknown field'));
    SearchQueryTranslator::assertPromptedTimes(2);
});

it('unbounded result set — naive returns everything; real caps at 200 and sets truncated', function () {
    $this->showcase->ensureUnboundedDataset($this->acmeAgent);

    $query = FailureShowcase::unboundedQuery();
    $naive = $this->naiveRunner->run($query, $this->acmeAgent);
    $real = $this->realRunner->run($query, $this->acmeAgent);

    expect($naive->total)->toBeGreaterThan(SearchSchema::MAX_LIMIT)
        ->and($naive->rows)->toHaveCount($naive->total)
        ->and($naive->truncated)->toBeFalse()
        ->and($real->appliedQuery->limit)->toBe(SearchSchema::MAX_LIMIT)
        ->and($real->rows)->toHaveCount(SearchSchema::MAX_LIMIT)
        ->and($real->truncated)->toBeTrue()
        ->and($real->total)->toBeGreaterThan(SearchSchema::MAX_LIMIT);
});
