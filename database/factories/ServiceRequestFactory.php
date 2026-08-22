<?php

namespace Database\Factories;

use App\Enums\ServiceRequestPriority;
use App\Enums\ServiceRequestStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'status' => fake()->randomElement(ServiceRequestStatus::cases()),
            'priority' => fake()->randomElement(ServiceRequestPriority::cases()),
            'opened_at' => fake()->dateTimeBetween('-3 years'),
            'closed_at' => null,
            'assigned_user_id' => User::factory(),
            'legacy_flag2' => null,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequestStatus::Resolved,
            'closed_at' => now(),
        ]);
    }
}
