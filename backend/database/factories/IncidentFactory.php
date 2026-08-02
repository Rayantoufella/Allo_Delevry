<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'reported_by' => User::factory(),
            'type' => fake()->randomElement(['delay', 'damage', 'loss', 'other']),
            'description' => fake()->optional()->paragraph(),
            'status' => fake()->randomElement(['open', 'investigating', 'resolved']),
        ];
    }
}
