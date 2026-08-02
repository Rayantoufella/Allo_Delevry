<?php

namespace Database\Factories;

use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RequestStatusHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'delivery_request_id' => DeliveryRequest::factory(),
            'changed_by' => User::factory(),
            'old_status' => fake()->optional()->randomElement([
                'en_attente', 'prix_propose', 'confirmee',
            ]),
            'new_status' => fake()->randomElement([
                'en_attente', 'prix_propose', 'confirmee', 'colis_recupere',
                'en_livraison', 'livree', 'refusee', 'echec', 'annulee',
            ]),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
