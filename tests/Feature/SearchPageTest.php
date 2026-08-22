<?php

use App\Ai\Agents\SearchQueryTranslator;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;

beforeEach(function () {
    $this->acme = Company::factory()->create(['name' => 'Acme Services']);

    $this->actor = User::factory()->agent()->create([
        'company_id' => $this->acme->id,
        'email' => 'agent@acme.test',
        'name' => 'Acme Agent',
    ]);
    $this->admin = User::factory()->admin()->create([
        'company_id' => $this->acme->id,
        'email' => 'admin@acme.test',
        'name' => 'Acme Admin',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function searchPageTranslatorOutput(array $overrides = []): array
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
            ],
            'sort' => 'opened_at',
            'direction' => 'desc',
            'limit' => 50,
            'confidence' => 'high',
            'interpretation' => 'Open service requests',
            'clarification_needed' => null,
        ],
        ...$overrides,
    ];
}

it('guests are redirected from the search page', function () {
    $this->get(route('search.index'))
        ->assertRedirect(route('login'));
});

it('renders results server side for a plain GET request', function () {
    SearchQueryTranslator::fake([searchPageTranslatorOutput()])->preventStrayPrompts();

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

    $this->actingAs($this->actor)
        ->get(route('search.results', ['q' => 'show open requests']))
        ->assertSuccessful()
        ->assertSee('Heater repair')
        ->assertSee('Ada Lovelace');
});

it('shows the interpretation sentence above results', function () {
    SearchQueryTranslator::fake([searchPageTranslatorOutput()])->preventStrayPrompts();

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

    $this->actingAs($this->actor)
        ->get(route('search.results', ['q' => 'show open requests']))
        ->assertSuccessful()
        ->assertSeeInOrder([
            'Interpretation',
            'Open service requests',
            'Heater repair',
        ]);
});

it('shows a disambiguation list when multiple customers match', function () {
    SearchQueryTranslator::fake([
        searchPageTranslatorOutput([
            'filters' => [
                [
                    'field' => 'customer_name',
                    'operator' => 'contains',
                    'value' => 'John Smith',
                ],
            ],
            'interpretation' => 'Service requests for customer John Smith',
        ]),
    ])->preventStrayPrompts();

    Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.one@acme.test',
    ]);
    Customer::factory()->create([
        'company_id' => $this->acme->id,
        'name' => 'John Smith',
        'email' => 'john.two@acme.test',
    ]);

    $this->actingAs($this->actor)
        ->get(route('search.results', ['q' => "show me John Smith's requests"]))
        ->assertSuccessful()
        ->assertSee('Which customer did you mean?')
        ->assertSee('john.one@acme.test')
        ->assertSee('john.two@acme.test')
        ->assertDontSee('Heater repair');
});

it('non admins cannot see the generated SQL panel', function () {
    SearchQueryTranslator::fake(fn (): array => searchPageTranslatorOutput())->preventStrayPrompts();

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

    $this->actingAs($this->actor)
        ->get(route('search.results', ['q' => 'show open requests']))
        ->assertSuccessful()
        ->assertSee('Heater repair')
        ->assertDontSee('Show generated query');

    $this->actingAs($this->admin)
        ->get(route('search.results', ['q' => 'show open requests']))
        ->assertSuccessful()
        ->assertSee('Show generated query')
        ->assertSee('select');
});

it('non admins cannot open the ai logs page', function () {
    $this->actingAs($this->actor)
        ->get(route('admin.ai-logs.index'))
        ->assertForbidden();

    $this->actingAs($this->admin)
        ->get(route('admin.ai-logs.index'))
        ->assertSuccessful();
});
