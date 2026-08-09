<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DelevryZonesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDemoZones();

        if (DeliveryZone::exists()) {
            return;
        }

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        foreach ($drivers as $driver) {
            DeliveryZone::factory()->count(3)->for($driver, 'user')->create();
        }
    }

    /**
     * Zones du livreur démo alignées sur le prototype (écran 11) : quatre
     * zones nommées avec leurs tarifs. Le modèle backend exige un couple
     * origin/destination ; le prototype ne connaît qu'un nom par zone, on
     * écrit donc la même valeur dans les deux champs. Les zones déjà présentes
     * sont reformatées, les surplus non référencés par une demande supprimés.
     */
    private function seedDemoZones(): void
    {
        $demo = User::where('email', 'driver@example.com')->first();

        if (! $demo) {
            return;
        }

        $zones = [
            ['origin_zone' => 'Centre-ville', 'destination_zone' => 'Centre-ville', 'fixed_price' => 15],
            ['origin_zone' => 'Al Houda', 'destination_zone' => 'Al Houda', 'fixed_price' => 20],
            ['origin_zone' => 'Hay Mohammadi', 'destination_zone' => 'Hay Mohammadi', 'fixed_price' => 25],
            ['origin_zone' => 'Périphérie', 'destination_zone' => 'Périphérie', 'fixed_price' => 35],
        ];

        $existing = $demo->deliveryZones()->orderBy('id')->get();

        foreach ($zones as $i => $zone) {
            $current = $existing->get($i);

            if ($current) {
                $current->update($zone + ['is_active' => true]);
            } else {
                $demo->deliveryZones()->create($zone + ['is_active' => true]);
            }
        }

        foreach ($existing->slice(count($zones)) as $extra) {
            if ($extra->deliveryRequests()->doesntExist()) {
                $extra->delete();
            }
        }
    }
}
