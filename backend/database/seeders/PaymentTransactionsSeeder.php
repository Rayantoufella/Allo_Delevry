<?php

namespace Database\Seeders;

use App\Models\DeliveryRequest;
use App\Models\PaymentTransaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentTransactionsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (PaymentTransaction::exists()) {
            return;
        }

        $deliveries = DeliveryRequest::all();

        if ($deliveries->isEmpty()) {
            $deliveries = DeliveryRequest::factory()->count(10)->delivered()->create();
        }

        PaymentTransaction::factory()
            ->count(20)
            ->recycle($deliveries)
            ->create();
    }
}
