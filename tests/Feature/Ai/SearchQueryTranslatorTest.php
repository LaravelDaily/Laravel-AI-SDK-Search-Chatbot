<?php

use App\Ai\Agents\SearchQueryTranslator;
use App\Ai\SearchQueryTranslatorService;
use App\Ai\Translation;
use App\Ai\TranslationFailed;
use App\Models\Company;
use App\Models\Customer;
use App\Models\User;
use App\Search\Operator;

beforeEach(function () {
    $this->travelTo('2026-08-22 09:00:00');

    $this->acme = Company::factory()->create(['name' => 'Acme Services']);
    $this->actor = User::factory()->agent()->create([
        'company_id' => $this->acme->id,
        'email' => 'agent@acme.test',
        'name' => 'Acme Agent',
    ]);
    $this->service = app(SearchQueryTranslatorService::class);
});

/**
 * @return array<string, mixed>
 */
function validTranslatorOutput(): array
{
    return [
        'entity' => 'service_requests',
        'filters' => [
            [
                'field' => 'customer_name',
                'operator' => 'contains',
                'value' => 'John Smith',
            ],
            [
                'field' => 'opened_at',
                'operator' => 'between',
                'value' => ['2025-08-22', '2026-08-22'],
            ],
        ],
        'sort' => 'opened_at',
        'direction' => 'desc',
        'limit' => 50,
        'confidence' => 'high',
        'interpretation' => 'Service requests for customer John Smith opened between 2025-08-22 and 2026-08-22',
        'clarification_needed' => null,
    ];
}

/**
 * @return array<string, mixed>
 */
function unknownFieldOutput(): array
{
    return [
        'entity' => 'customers',
        'filters' => [
            [
                'field' => 'is_vip',
                'operator' => '=',
                'value' => true,
            ],
        ],
        'sort' => null,
        'direction' => 'desc',
        'limit' => 50,
        'confidence' => 'high',
        'interpretation' => 'VIP customers',
        'clarification_needed' => null,
    ];
}

it('maps a faked structured response into a valid SearchQuery', function () {
    SearchQueryTranslator::fake([validTranslatorOutput()])->preventStrayPrompts();

    $translation = $this->service->translate(
        "show me all of customer John Smith's requests in the last year",
        $this->actor,
    );

    expect($translation)->toBeInstanceOf(Translation::class)
        ->and($translation->query->entity)->toBe('service_requests')
        ->and($translation->query->filters)->toHaveCount(2)
        ->and($translation->query->filters[0]->field)->toBe('customer_name')
        ->and($translation->query->filters[0]->operator)->toBe(Operator::Contains->value)
        ->and($translation->query->filters[0]->value)->toBe('John Smith')
        ->and($translation->query->filters[1]->field)->toBe('opened_at')
        ->and($translation->query->filters[1]->operator)->toBe(Operator::Between->value)
        ->and($translation->query->filters[1]->value)->toBe(['2025-08-22', '2026-08-22'])
        ->and($translation->interpretation)->toBe(
            'Service requests for customer John Smith opened between 2025-08-22 and 2026-08-22',
        )
        ->and($translation->confidence)->toBe('high')
        ->and($translation->clarification)->toBeNull()
        ->and($translation->attempts)->toBe(1);

    SearchQueryTranslator::assertPromptedTimes(1);
});

it('retries once with the validator error when the first response uses an unknown field', function () {
    SearchQueryTranslator::fake([
        unknownFieldOutput(),
        validTranslatorOutput(),
    ])->preventStrayPrompts();

    $translation = $this->service->translate(
        "show me all of customer John Smith's requests in the last year",
        $this->actor,
    );

    expect($translation)->toBeInstanceOf(Translation::class)
        ->and($translation->query->entity)->toBe('service_requests')
        ->and($translation->attempts)->toBe(2);

    SearchQueryTranslator::assertPrompted(fn ($prompt) => $prompt->contains('unknown field'));
    SearchQueryTranslator::assertPromptedTimes(2);
});

it('gives up after one retry and returns a failure result', function () {
    SearchQueryTranslator::fake([
        unknownFieldOutput(),
        unknownFieldOutput(),
    ])->preventStrayPrompts();

    $result = $this->service->translate('show VIP customers', $this->actor);

    expect($result)->toBeInstanceOf(TranslationFailed::class)
        ->and($result->attempts)->toBe(2)
        ->and($result->reason)->toContain("Unknown field 'is_vip'");

    SearchQueryTranslator::assertPromptedTimes(2);
});

it('never sends customer row data in the prompt', function () {
    Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john@acme.test',
        'tax_id' => '12-3456789',
    ]);

    SearchQueryTranslator::fake([validTranslatorOutput()])->preventStrayPrompts();

    $this->service->translate(
        "show me all of customer John Smith's requests in the last year",
        $this->actor,
    );

    SearchQueryTranslator::assertPrompted(function ($prompt) {
        $instructions = (string) $prompt->agent->instructions();

        return ! $prompt->contains('tax_id')
            && ! $prompt->contains('john@')
            && ! str_contains($instructions, 'tax_id')
            && ! str_contains($instructions, 'john@');
    });
});

it("includes today's date in the instructions", function () {
    SearchQueryTranslator::fake([validTranslatorOutput()])->preventStrayPrompts();

    $this->service->translate('show open requests', $this->actor);

    SearchQueryTranslator::assertPrompted(function ($prompt) {
        return str_contains((string) $prompt->agent->instructions(), '2026-08-22');
    });
});

it('surfaces clarification_needed instead of running a query', function () {
    SearchQueryTranslator::fake([
        [
            'entity' => 'customers',
            'filters' => [
                [
                    'field' => 'is_vip',
                    'operator' => '=',
                    'value' => true,
                ],
            ],
            'sort' => null,
            'direction' => 'desc',
            'limit' => 50,
            'confidence' => 'low',
            'interpretation' => 'The question is ambiguous.',
            'clarification_needed' => 'Did you mean active customers or all customers?',
        ],
    ])->preventStrayPrompts();

    $translation = $this->service->translate('how many customers are happy?', $this->actor);

    expect($translation)->toBeInstanceOf(Translation::class)
        ->and($translation->query)->toBeNull()
        ->and($translation->clarification)->toBe('Did you mean active customers or all customers?')
        ->and($translation->confidence)->toBe('low')
        ->and($translation->attempts)->toBe(1);

    SearchQueryTranslator::assertPromptedTimes(1);
});
