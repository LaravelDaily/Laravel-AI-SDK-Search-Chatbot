<?php

namespace Database\Factories;

use App\Models\RequestNote;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestNote>
 */
class RequestNoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_request_id' => ServiceRequest::factory(),
            'author_id' => User::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => fake()->boolean(20),
        ];
    }

    public function customerSubmitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_internal' => false,
        ]);
    }
}
