<?php

use App\Ai\Agents\SearchQueryTranslator;
use App\Livewire\SearchPanel;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->acme = Company::factory()->create(['name' => 'Acme Services']);

    $this->actor = User::factory()->agent()->create([
        'company_id' => $this->acme->id,
        'email' => 'agent@acme.test',
        'name' => 'Acme Agent',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function searchPanelTranslatorOutput(array $overrides = []): array
{
    return [
        ...[
            'entity' => 'service_requests',
            'filters' => [
                [
                    'field' => 'status',
                    'operator' => '=',
                    'value' => 'open',
                ],
                [
                    'field' => 'title',
                    'operator' => 'contains',
                    'value' => 'Heater',
                ],
            ],
            'sort' => 'opened_at',
            'direction' => 'desc',
            'limit' => 50,
            'confidence' => 'high',
            'interpretation' => 'Open heater service requests',
            'clarification_needed' => null,
        ],
        ...$overrides,
    ];
}

it('lists filter chips after a search', function () {
    SearchQueryTranslator::fake([searchPanelTranslatorOutput()])->preventStrayPrompts();

    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->actor->id,
        'title' => 'Heater repair',
        'status' => 'open',
    ]);

    Livewire::actingAs($this->actor)
        ->test(SearchPanel::class)
        ->set('question', 'show open heater requests')
        ->call('search')
        ->assertSee('Applied filters')
        ->assertSee('status')
        ->assertSee('open')
        ->assertSee('title')
        ->assertSee('Heater')
        ->assertSee('Heater repair');
});

it('re-runs the query without calling the model when a chip is removed', function () {
    SearchQueryTranslator::fake([searchPanelTranslatorOutput()])->preventStrayPrompts();

    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->actor->id,
        'title' => 'Heater repair',
        'status' => 'open',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->actor->id,
        'title' => 'Boiler leak',
        'status' => 'open',
    ]);

    Livewire::actingAs($this->actor)
        ->test(SearchPanel::class)
        ->set('question', 'show open heater requests')
        ->call('search')
        ->assertSee('Heater repair')
        ->assertDontSee('Boiler leak')
        ->call('removeFilter', 1)
        ->assertSee('Heater repair')
        ->assertSee('Boiler leak')
        ->assertDontSee('title');

    SearchQueryTranslator::assertPromptedTimes(1);
});

it('shows the clarification state instead of results when confidence is low', function () {
    SearchQueryTranslator::fake([
        searchPanelTranslatorOutput([
            'confidence' => 'low',
            'interpretation' => 'The question is ambiguous.',
            'clarification_needed' => null,
        ]),
    ])->preventStrayPrompts();

    $customer = Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'Ada Lovelace',
        'email' => 'ada@acme.test',
    ]);
    ServiceRequest::factory()->create([
        'company_id' => $this->acme->id,
        'customer_id' => $customer->id,
        'assigned_user_id' => $this->actor->id,
        'title' => 'Heater repair',
        'status' => 'open',
    ]);

    Livewire::actingAs($this->actor)
        ->test(SearchPanel::class)
        ->set('question', 'show the important ones')
        ->call('search')
        ->assertSee('The question is ambiguous.')
        ->assertSee('Please be more specific.')
        ->assertDontSee('Heater repair')
        ->assertDontSee('Applied filters');
});
