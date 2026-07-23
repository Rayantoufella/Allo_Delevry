<?php

namespace Database\Factories;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'origin_zone' => fake()->city(),
            'destination_zone' => fake()->city(),
            'fixed_price' => fake()->optional()->randomFloat(2, 15, 100),
            'is_active' => true,
        ];
    }
}
