<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'delivery_request_id' => DeliveryRequest::factory(),
            'type' => fake()->randomElement(['new_request', 'status_update', 'payment']),
            'title' => fake()->sentence(4),
            'body' => fake()->optional()->paragraph(),
            'read_at' => fake()->optional()->dateTime(),
        ];
    }
}
