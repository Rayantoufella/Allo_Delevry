<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            DriverProfilesSeeder::class,
            DelevryZonesSeeder::class,
            ServicesSeeder::class,
            AiRequestDraftsSeeder::class,
            DeliveryRequestsSeeder::class,
            RequestStatusHistoriesSeeder::class,
            ChatMessagesSeeder::class,
            DeliveryProofsSeeder::class,
            IncidentsSeeder::class,
            ReviewsSeeder::class,
            NotificationSeeder::class,
            GpsLocationsSeeder::class,
            PaymentTransactionsSeeder::class,
        ]);
    }
}
