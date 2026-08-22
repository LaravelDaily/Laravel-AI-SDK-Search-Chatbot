<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-2 years');

        return [
            'company_id' => Company::factory(),
            'customer_id' => Customer::factory(),
            'service_request_id' => null,
            'total_cents' => fake()->numberBetween(5_000, 250_000),
            'issued_at' => $issuedAt,
            'paid_at' => fake()->boolean(70) ? fake()->dateTimeBetween($issuedAt, 'now') : null,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'paid_at' => null,
        ]);
    }
}
