<?php

namespace Database\Seeders;

use App\Models\DriverProfile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DriverProfilesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDemoProfile();

        if (DriverProfile::exists()) {
            return;
        }

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        foreach ($drivers as $driver) {
            DriverProfile::factory()->for($driver, 'user')->create();
        }
    }

    /**
     * Profil déterministe du livreur démo (driver@example.com) : slug stable
     * « rayan-express », indépendant du garde-fou ci-dessus — sinon un re-seed
     * après une factory aléatoire casserait le lien public du compte démo.
     */
    private function seedDemoProfile(): void
    {
        $demo = User::where('email', 'driver@example.com')->first();

        if (! $demo) {
            return;
        }

        // Si le slug démo est déjà pris par un autre profil (re-seed après une
        // factory aléatoire), on le lui rend d'abord.
        $holder = DriverProfile::where('slug', 'rayan-express')
            ->where('user_id', '!=', $demo->id)
            ->first();

        if ($holder) {
            $base = Str::slug($holder->brand_name) ?: 'profil';
            $candidate = $base;
            $i = 1;
            while (DriverProfile::where('slug', $candidate)->where('id', '!=', $holder->id)->exists()) {
                $candidate = $base.'-'.$i++;
            }
            $holder->update(['slug' => $candidate]);
        }

        DriverProfile::updateOrCreate(
            ['user_id' => $demo->id],
            [
                'brand_name' => 'Rayan Express',
                'slug' => 'rayan-express',
                'city' => 'Agadir',
                'description' => 'Livraison express & courses à Agadir, 7j/7.',
                'is_available' => true,
                'logo_path' => null,
            ],
        );

        // Téléphone public : stocké sur users.phone (champ alimenté par Profil & marque).
        if (empty($demo->phone)) {
            $demo->update(['phone' => '+212 6 61 23 45 67']);
        }
    }
}
