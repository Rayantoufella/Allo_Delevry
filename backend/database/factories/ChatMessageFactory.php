<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChatMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'sender_id' => User::factory(),
            'message_type' => fake()->randomElement(['text', 'image', 'system']),
            'content' => fake()->paragraph(),
            'is_read' => fake()->boolean(70),
        ];
    }
}
