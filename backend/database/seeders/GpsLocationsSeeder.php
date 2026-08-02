<?php

namespace Database\Seeders;

use App\Models\DeliveryRequest;
use App\Models\GpsLocation;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GpsLocationsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (GpsLocation::exists()) {
            return;
        }

        $deliveries = DeliveryRequest::where('status', DeliveryRequest::STATUS_EN_LIVRAISON)->get();

        if ($deliveries->isEmpty()) {
            $deliveries = DeliveryRequest::factory()->count(5)->inDelivery()->create();
        }

        GpsLocation::factory()
            ->count(30)
            ->recycle($deliveries)
            ->create();
    }
}
