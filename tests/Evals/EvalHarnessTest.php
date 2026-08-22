<?php

use App\Ai\Agents\SearchQueryTranslator;
use App\Enums\ServiceRequestStatus;
use App\Evals\EvalActors;
use App\Evals\EvalCase;
use App\Evals\EvalExpectations;
use App\Evals\EvalRunner;
use App\Evals\GoldenDataset;
use App\Models\Company;
use App\Models\ServiceRequest;
use App\Search\SearchAttempt;
use App\Search\SearchSchema;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->travelTo('2026-08-22 09:00:00');
});

/**
 * @return array<string, mixed>
 */
function evalOpenRequestsTranslation(): array
{
    return EvalExpectations::translation(
        entity: 'service_requests',
        filters: [['field' => 'status', 'operator' => '=', 'value' => 'open']],
        interpretation: 'Open service requests',
    );
}

function fakeGoldenTranslations(): void
{
    $cases = GoldenDataset::cases();

    usort(
        $cases,
        fn (EvalCase $left, EvalCase $right): int => mb_strlen($right->question) <=> mb_strlen($left->question),
    );

    SearchQueryTranslator::fake(function (string $prompt) use ($cases): array {
        foreach ($cases as $case) {
            if (! str_contains($prompt, 'Question: '.$case->question)) {
                continue;
            }

            $translation = $case->resolvedTranslation();

            if ($translation === null) {
                throw new RuntimeException("Golden case [{$case->id}] is missing a recorded translation.");
            }

            return $translation;
        }

        throw new RuntimeException('No fake translation matched the prompt.');
    })->preventStrayPrompts();
}

it('scores a case as passed when the result set matches', function () {
    $this->seed(DemoDataSeeder::class);
    SearchQueryTranslator::fake([evalOpenRequestsTranslation()])->preventStrayPrompts();

    $expected = ServiceRequest::query()
        ->where('company_id', EvalExpectations::acmeId())
        ->where('status', ServiceRequestStatus::Open)
        ->count();

    $report = app(EvalRunner::class)->run([
        new EvalCase(
            id: 'open-requests-match',
            question: 'show me open service requests',
            actor: 'acme_agent',
            expect: [
                'entity' => 'service_requests',
                'assert' => fn (SearchAttempt $attempt): bool => $attempt->result?->total === $expected,
            ],
            tags: ['simple'],
        ),
    ]);

    expect($report->cases)->toHaveCount(1)
        ->and($report->cases[0]->passed())->toBeTrue()
        ->and($report->accuracy())->toBe(1.0);
});

it('scores a case as failed when totals differ', function () {
    $this->seed(DemoDataSeeder::class);
    SearchQueryTranslator::fake([evalOpenRequestsTranslation()])->preventStrayPrompts();

    $report = app(EvalRunner::class)->run([
        new EvalCase(
            id: 'open-requests-mismatch',
            question: 'show me open service requests',
            actor: 'acme_agent',
            expect: [
                'entity' => 'service_requests',
                'assert' => fn (SearchAttempt $attempt): bool => $attempt->result?->total === -1,
            ],
            tags: ['simple'],
        ),
    ]);

    expect($report->cases)->toHaveCount(1)
        ->and($report->cases[0]->passed())->toBeFalse()
        ->and($report->accuracy())->toBe(0.0);
});

it('reports consistency across multiple runs', function () {
    $this->seed(DemoDataSeeder::class);
    SearchQueryTranslator::fake([
        evalOpenRequestsTranslation(),
        evalOpenRequestsTranslation(),
        evalOpenRequestsTranslation(),
    ])->preventStrayPrompts();

    $consistent = app(EvalRunner::class)->run([
        new EvalCase(
            id: 'open-requests-consistent',
            question: 'show me open service requests',
            actor: 'acme_agent',
            expect: [
                'assert' => fn (SearchAttempt $attempt): bool => $attempt->showsResults(),
            ],
            tags: ['simple'],
        ),
    ], runs: 3);

    expect($consistent->runsPerCase)->toBe(3)
        ->and($consistent->consistency())->toBe(1.0)
        ->and($consistent->accuracy())->toBe(1.0);

    $iteration = 0;

    SearchQueryTranslator::fake([
        evalOpenRequestsTranslation(),
        evalOpenRequestsTranslation(),
        evalOpenRequestsTranslation(),
    ])->preventStrayPrompts();

    $inconsistent = app(EvalRunner::class)->run([
        new EvalCase(
            id: 'open-requests-inconsistent',
            question: 'show me open service requests',
            actor: 'acme_agent',
            expect: [
                'assert' => function () use (&$iteration): bool {
                    $iteration++;

                    return $iteration === 1;
                },
            ],
            tags: ['simple'],
        ),
    ], runs: 3);

    expect($inconsistent->consistency())->toBe(0.0)
        ->and($inconsistent->accuracy())->toBe(1 / 3)
        ->and($inconsistent->cases[0]->passCount())->toBe(1);
});

it('every golden case has a unique id, a valid actor, and at least one tag', function () {
    $cases = GoldenDataset::cases();
    $ids = array_map(fn (EvalCase $case): string => $case->id, $cases);

    expect($cases)->toHaveCount(34)
        ->and($ids)->toHaveCount(count(array_unique($ids)));

    foreach ($cases as $case) {
        expect($case->id)->not->toBeEmpty()
            ->and(EvalActors::isValid($case->actor))->toBeTrue()
            ->and($case->tags)->not->toBeEmpty()
            ->and($case->question)->not->toBeEmpty();
    }
});

it('every golden case references only entities present in SearchSchema', function () {
    $allowed = SearchSchema::entityNames();

    foreach (GoldenDataset::cases() as $case) {
        if (isset($case->expect['entity'])) {
            expect($allowed)->toContain($case->expect['entity']);
        }

        $translation = $case->resolvedTranslation();

        if (is_array($translation) && isset($translation['entity'])) {
            expect($allowed)->toContain($translation['entity']);
        }
    }
});

it('scores every golden case as passed when the recorded translation is used', function () {
    $this->seed(DemoDataSeeder::class);
    fakeGoldenTranslations();

    $report = app(EvalRunner::class)->run(GoldenDataset::cases());
    $failed = array_values(array_map(
        fn ($case): string => $case->id.': '.($case->runs[0]->message ?? 'failed'),
        array_filter($report->cases, fn ($case): bool => ! $case->passed()),
    ));

    expect($failed)->toBeEmpty()
        ->and($report->accuracy())->toBe(1.0);
});

it('prints a summary table and writes a json report', function () {
    $this->seed(DemoDataSeeder::class);
    Storage::fake('local');
    fakeGoldenTranslations();

    $this->artisan('eval:search', ['--filter' => 'open-requests'])
        ->expectsOutputToContain('Natural-language search eval')
        ->expectsOutputToContain('Accuracy')
        ->expectsOutputToContain('Wrote storage/app/evals/')
        ->assertSuccessful();

    $files = Storage::disk('local')->files('evals');

    expect($files)->toHaveCount(1);

    $payload = json_decode((string) Storage::disk('local')->get($files[0]), true);

    expect($payload['accuracy'])->toBe(1)
        ->and($payload['cases'])->toHaveCount(1)
        ->and($payload['cases'][0]['id'])->toBe('open-requests')
        ->and($payload['cases'][0]['passed'])->toBeTrue();
});

it('fails when demo data has not been seeded', function () {
    $this->artisan('eval:search')
        ->expectsOutputToContain('No demo data found')
        ->assertFailed();

    expect(Company::query()->where('name', 'Acme Services')->exists())->toBeFalse();
});
