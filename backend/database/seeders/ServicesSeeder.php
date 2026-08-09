<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ServicesSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedDemoCatalog();

        if (Service::exists()) {
            return;
        }

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        foreach ($drivers as $driver) {
            Service::factory()->count(2)->for($driver, 'user')->create();
        }
    }

    /**
     * Catalogue du livreur démo aligné sur le prototype : 5 services nommés
     * avec leur prix de base. Les services déjà présents sont reformatés
     * (valeurs du prototype), les surplus non référencés par une demande sont
     * supprimés — sans quoi la page publique démo montrait des noms de
     * factory (« vel », « odio »).
     */
    private function seedDemoCatalog(): void
    {
        $demo = User::where('email', 'driver@example.com')->first();

        if (! $demo) {
            return;
        }

        $catalog = [
            ['name' => 'Envoi de colis', 'description' => 'Petit à moyen colis, main propre', 'base_price' => 15],
            ['name' => 'Documents & plis', 'description' => 'Papiers, contrats, plis urgents', 'base_price' => 12],
            ['name' => 'Courses & achats', 'description' => 'Achat puis livraison', 'base_price' => 20],
            ['name' => 'Plats & repas', 'description' => 'Récupération et livraison de repas', 'base_price' => 18],
            ['name' => 'Pharmacie', 'description' => 'Médicaments et parapharmacie', 'base_price' => 14],
        ];

        $existing = $demo->services()->orderBy('id')->get();

        foreach ($catalog as $i => $item) {
            $service = $existing->get($i);

            if ($service) {
                $service->update($item + ['is_active' => true]);
            } else {
                $demo->services()->create($item + ['is_active' => true]);
            }
        }

        foreach ($existing->slice(count($catalog)) as $extra) {
            if ($extra->deliveryRequests()->doesntExist()) {
                $extra->delete();
            }
        }
    }
}
