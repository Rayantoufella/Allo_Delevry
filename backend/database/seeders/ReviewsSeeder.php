<?php

namespace Database\Seeders;

use App\Models\DeliveryRequest;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReviewsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Review::exists()) {
            return;
        }

        $deliveries = DeliveryRequest::where('status', DeliveryRequest::STATUS_LIVREE)->get();

        if ($deliveries->isEmpty()) {
            $deliveries = DeliveryRequest::factory()->count(10)->delivered()->create();
        }

        $clients = User::where('role', User::ROLE_CLIENT)->get();

        if ($clients->isEmpty()) {
            $clients = User::factory()->count(10)->client()->create();
        }

        foreach ($deliveries->take(15) as $delivery) {
            Review::factory()
                ->recycle($clients)
                ->create(['delivery_request_id' => $delivery->id]);
        }
    }
}
