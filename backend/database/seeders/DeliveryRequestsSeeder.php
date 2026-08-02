<?php

namespace Database\Seeders;

use App\Models\AiRequestDraft;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DeliveryRequestsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DeliveryRequest::exists()) {
            return;
        }

        $clients = User::where('role', User::ROLE_CLIENT)->get();

        if ($clients->isEmpty()) {
            $clients = User::factory()->count(10)->client()->create();
        }

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        $services = Service::all();

        if ($services->isEmpty()) {
            $services = Service::factory()->count(5)->create();
        }

        $zones = DeliveryZone::all();

        if ($zones->isEmpty()) {
            $zones = DeliveryZone::factory()->count(10)->recycle($drivers)->create();
        }

        $drafts = AiRequestDraft::all();

        if ($drafts->isEmpty()) {
            $drafts = AiRequestDraft::factory()->count(10)->recycle([$clients, $services])->create();
        }

        $recycle = [$clients, $drivers, $services, $zones, $drafts];

        DeliveryRequest::factory()
            ->count(15)
            ->recycle($recycle)
            ->create();

        DeliveryRequest::factory()
            ->count(10)
            ->confirmed()
            ->recycle($recycle)
            ->create();

        DeliveryRequest::factory()
            ->count(5)
            ->inDelivery()
            ->recycle($recycle)
            ->create();

        DeliveryRequest::factory()
            ->count(10)
            ->delivered()
            ->recycle($recycle)
            ->create();
    }
}
