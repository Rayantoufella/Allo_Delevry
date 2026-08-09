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

        $drivers = User::where('role', User::ROLE_DRIVER)->get();

        if ($drivers->isEmpty()) {
            $drivers = User::factory()->count(10)->driver()->create();
        }

        // Chaque client est rattaché à un livreur précis (RG scoping) : on va chercher,
        // pour chaque livreur, ses propres clients plutôt que de piocher au hasard dans
        // une liste globale, sinon une demande pourrait se retrouver avec un client_id
        // et un driver_id qui ne correspondent pas au même livreur.
        $clients = User::where('role', User::ROLE_CLIENT)->whereIn('driver_id', $drivers->pluck('id'))->get();

        if ($clients->isEmpty()) {
            $clients = $drivers->flatMap(fn (User $driver) => User::factory()->count(2)->clientOf($driver)->create());
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

        // On ne recycle pas $clients et $drivers indépendamment : ça pourrait associer
        // le client d'un livreur A à une demande dont driver_id pointe vers un livreur B.
        // On tire donc des paires (client, son propre livreur) déjà cohérentes.
        $pairs = $clients->map(fn (User $client) => [
            'client' => $client,
            'driver' => $drivers->firstWhere('id', $client->driver_id) ?? $drivers->random(),
        ]);

        $recycle = [$services, $zones, $drafts];

        $createBatch = function (int $count, ?string $state = null) use ($pairs, $recycle): void {
            for ($i = 0; $i < $count; $i++) {
                $pair = $pairs->random();

                $factory = DeliveryRequest::factory()
                    ->forClient($pair['client'])
                    ->forDriver($pair['driver'])
                    ->recycle($recycle);

                if ($state !== null) {
                    $factory = $factory->{$state}();
                }

                $factory->create();
            }
        };

        $createBatch(15);
        $createBatch(10, 'confirmed');
        $createBatch(5, 'inDelivery');
        $createBatch(10, 'delivered');
    }
}
