<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DeliveryProofFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'uploaded_by' => User::factory(),
            'proof_type' => fake()->randomElement(['photo', 'ticket', 'signature']),
            'file_path' => 'proofs/'.fake()->uuid().'.jpg',
            'receiver_name' => fake()->optional()->name(),
        ];
    }
}
