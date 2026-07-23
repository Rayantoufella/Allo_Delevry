<?php

namespace Database\Factories;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->driver(),
            'brand_name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'logo_path' => fake()->optional()->imageUrl(),
            'city' => fake()->optional()->city(),
            'rib' => fake()->optional()->bankAccountNumber(),
            'is_available' => true,
        ];
    }
}
