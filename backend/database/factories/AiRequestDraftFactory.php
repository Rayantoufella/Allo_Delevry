<?php

namespace Database\Factories;

use App\Models\AiRequestDraft;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AiRequestDraftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->client(),
            'service_id' => Service::factory(),
            'input_message' => fake()->paragraph(),
            'generated_data' => fake()->optional()->randomElement([
                ['recipient' => fake()->name(), 'address' => fake()->address()],
                null,
            ]),
            'status' => fake()->randomElement([
                AiRequestDraft::STATUS_PENDING,
                AiRequestDraft::STATUS_DONE,
                AiRequestDraft::STATUS_FAILED,
            ]),
            'error_message' => fake()->optional()->sentence(),
            'validated_at' => fake()->optional()->dateTime(),
        ];
    }
}
