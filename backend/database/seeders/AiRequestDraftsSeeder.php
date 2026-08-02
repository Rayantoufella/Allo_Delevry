<?php

namespace Database\Seeders;

use App\Models\AiRequestDraft;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AiRequestDraftsSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (AiRequestDraft::exists()) {
            return;
        }

        $clients = User::where('role', User::ROLE_CLIENT)->get();

        if ($clients->isEmpty()) {
            $clients = User::factory()->count(10)->client()->create();
        }

        $services = Service::all();

        if ($services->isEmpty()) {
            $services = Service::factory()->count(5)->create();
        }

        AiRequestDraft::factory()
            ->count(20)
            ->recycle([$clients, $services])
            ->create();
    }
}
