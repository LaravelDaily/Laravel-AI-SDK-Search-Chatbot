<?php

namespace Database\Factories;

use App\Models\AiSearchLog;
use App\Models\Company;
use App\Models\User;
use App\Search\SearchOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiSearchLog>
 */
class AiSearchLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'company_id' => Company::factory(),
            'question' => 'show open requests',
            'interpretation' => 'Open service requests',
            'query_json' => [
                'entity' => 'service_requests',
                'filters' => [
                    ['field' => 'status', 'operator' => '=', 'value' => 'open'],
                ],
                'sort' => 'opened_at',
                'direction' => 'desc',
                'limit' => 50,
                'aggregate' => null,
            ],
            'compiled_sql' => 'select * from service_requests where company_id = 1 and status = \'open\' limit 50',
            'row_count' => 3,
            'attempts' => 1,
            'input_tokens' => 800,
            'output_tokens' => 120,
            'estimated_cost_cents' => 1,
            'outcome' => SearchOutcome::Ok,
            'duration_ms' => 250,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'interpretation' => null,
            'query_json' => null,
            'compiled_sql' => null,
            'row_count' => null,
            'attempts' => 2,
            'outcome' => SearchOutcome::Failed,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'compiled_sql' => null,
            'row_count' => null,
            'outcome' => SearchOutcome::Blocked,
        ]);
    }
}
