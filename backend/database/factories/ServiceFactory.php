<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'name' => fake()->word(),
            'description' => fake()->optional()->sentence(),
            'base_price' => fake()->optional()->randomFloat(2, 10, 200),
            'is_active' => true,
        ];
    }
}
