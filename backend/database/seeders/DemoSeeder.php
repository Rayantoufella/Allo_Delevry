<?php

namespace Database\Seeders;

use App\Models\ChatMessage;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\RequestStatusHistory;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Données de démonstration 100 % fictives (README, captures d'écran).
 *
 * Emails en @example.com (domaine réservé), téléphones dans la plage
 * 01 99 00 xx xx réservée à la fiction par l'ARCEP, adresses inventées.
 */
class DemoSeeder extends Seeder
{
    public const DRIVER_EMAIL = 'driver@example.com';

    public const CLIENT_EMAIL = 'client@example.com';

    public const PASSWORD = 'password';

    public const SLUG = 'express-demo';

    public function run(): void
    {
        if (User::where('email', self::DRIVER_EMAIL)->whereNull('driver_id')->exists()) {
            return;
        }

        $driver = User::create([
            'name' => 'Livreur Démo',
            'email' => self::DRIVER_EMAIL,
            'password' => self::PASSWORD,
            'role' => User::ROLE_DRIVER,
            'phone' => '+33 1 99 00 00 01',
        ]);

        DriverProfile::create([
            'user_id' => $driver->id,
            'brand_name' => 'Express Démo',
            'slug' => self::SLUG,
            'city' => 'Agadir',
            'description' => 'Livraison de colis, documents et courses en ville, 7j/7 (marque fictive de démonstration).',
            'is_available' => true,
        ]);

        $client = User::create([
            'name' => 'Client Démo',
            'email' => self::CLIENT_EMAIL,
            'password' => self::PASSWORD,
            'role' => User::ROLE_CLIENT,
            'phone' => '+33 1 99 00 00 02',
            'driver_id' => $driver->id,
        ]);

        $colis = Service::create(['user_id' => $driver->id, 'name' => 'Envoi de colis', 'description' => 'Petit à moyen colis, remis en main propre', 'base_price' => 15]);
        $documents = Service::create(['user_id' => $driver->id, 'name' => 'Documents & plis', 'description' => 'Papiers, contrats, plis urgents', 'base_price' => 12]);
        $courses = Service::create(['user_id' => $driver->id, 'name' => 'Courses & achats', 'description' => 'Achat pour le client puis livraison', 'base_price' => 20]);

        $centre = DeliveryZone::create(['user_id' => $driver->id, 'origin_zone' => 'Centre-ville', 'destination_zone' => 'Centre-ville', 'fixed_price' => 15]);
        $peripherie = DeliveryZone::create(['user_id' => $driver->id, 'origin_zone' => 'Centre-ville', 'destination_zone' => 'Périphérie', 'fixed_price' => 30]);

        $flow = [
            DeliveryRequest::STATUS_EN_ATTENTE,
            DeliveryRequest::STATUS_CONFIRMEE,
            DeliveryRequest::STATUS_COLIS_RECUPERE,
            DeliveryRequest::STATUS_EN_LIVRAISON,
            DeliveryRequest::STATUS_LIVREUR_ARRIVE,
            DeliveryRequest::STATUS_LIVREE,
        ];

        // 1. Commande livrée, avec preuves de récupération et de remise.
        $delivered = $this->createRequest($client, $driver, $colis, $centre, [
            'tracking_number' => 'DLV-DEMO000001',
            'private_token' => 'demo-suivi-livree',
            'recipient_name' => 'Destinataire Démo A',
            'recipient_phone' => '+33 1 99 00 01 01',
            'pickup_address' => '12 rue de l\'Exemple, Centre-ville',
            'delivery_address' => '8 avenue Fictive, Centre-ville',
            'package_description' => 'Boîte à chaussures, 1 kg',
            'amount_to_collect' => 15,
        ], $flow, now()->subDay()->setTime(10, 0));

        $this->addProof($delivered, $driver, 'pickup_photo', 'proofs/demo-recuperation.png', 'Photo de recuperation (demo)', null);
        $this->addProof($delivered, $driver, 'photo', 'proofs/demo-remise.png', 'Photo de remise (demo)', 'Destinataire Démo A');

        $this->addChat($delivered, [
            [$client, 'Bonjour, le colis est prêt à l\'accueil.'],
            [$driver, 'Bien reçu, je passe le récupérer dans 15 minutes.'],
            [$driver, 'Colis remis au destinataire, merci !'],
        ]);

        // 2. Commande en cours de livraison.
        $inDelivery = $this->createRequest($client, $driver, $documents, $peripherie, [
            'tracking_number' => 'DLV-DEMO000002',
            'private_token' => 'demo-suivi-en-livraison',
            'recipient_name' => 'Destinataire Démo B',
            'recipient_phone' => '+33 1 99 00 01 02',
            'pickup_address' => '3 place du Modèle, Centre-ville',
            'delivery_address' => '27 rue Imaginaire, Périphérie',
            'package_description' => 'Enveloppe A4 (contrat)',
            'amount_to_collect' => 30,
        ], array_slice($flow, 0, 4), now()->subHours(2));

        $this->addProof($inDelivery, $driver, 'pickup_photo', 'proofs/demo-recuperation-2.png', 'Photo de recuperation (demo)', null);

        // 3. Commande acceptée par le livreur.
        $this->createRequest($client, $driver, $courses, $centre, [
            'tracking_number' => 'DLV-DEMO000003',
            'private_token' => 'demo-suivi-confirmee',
            'recipient_name' => 'Destinataire Démo C',
            'recipient_phone' => '+33 1 99 00 01 03',
            'pickup_address' => 'Marché central (démo), Centre-ville',
            'delivery_address' => '5 impasse du Test, Centre-ville',
            'package_description' => 'Courses : fruits et pain',
            'product_amount' => 80,
            'amount_to_collect' => 95,
        ], array_slice($flow, 0, 2), now()->subMinutes(50));

        // 4. Nouvelle demande, en attente de réponse du livreur.
        $this->createRequest($client, $driver, $colis, $peripherie, [
            'tracking_number' => 'DLV-DEMO000004',
            'private_token' => 'demo-suivi-en-attente',
            'recipient_name' => 'Destinataire Démo D',
            'recipient_phone' => '+33 1 99 00 01 04',
            'pickup_address' => '12 rue de l\'Exemple, Centre-ville',
            'delivery_address' => '40 boulevard Fictif, Périphérie',
            'package_description' => 'Carton moyen, 3 kg',
            'amount_to_collect' => 30,
        ], array_slice($flow, 0, 1), now()->subMinutes(10));
    }

    /**
     * Crée une demande et son historique de statuts, horodaté de façon réaliste.
     *
     * @param  array<string, mixed>  $attributes
     * @param  list<string>  $statuses
     */
    private function createRequest(User $client, User $driver, Service $service, DeliveryZone $zone, array $attributes, array $statuses, Carbon $start): DeliveryRequest
    {
        $final = end($statuses);
        $request = new DeliveryRequest(array_merge($attributes, [
            'client_id' => $client->id,
            'driver_id' => $driver->id,
            'service_id' => $service->id,
            'delivery_zone_id' => $zone->id,
            'proposed_price' => $zone->fixed_price,
            'status' => $final,
        ]));
        $request->created_at = $start;

        $comments = [
            DeliveryRequest::STATUS_EN_ATTENTE => 'Demande créée par le client',
            DeliveryRequest::STATUS_CONFIRMEE => 'Demande acceptée par le livreur',
            DeliveryRequest::STATUS_COLIS_RECUPERE => 'Colis récupéré (photo jointe)',
            DeliveryRequest::STATUS_EN_LIVRAISON => 'Départ en livraison',
            DeliveryRequest::STATUS_LIVREUR_ARRIVE => 'Le livreur est arrivé à l\'adresse',
            DeliveryRequest::STATUS_LIVREE => 'Remise confirmée (photo jointe)',
        ];

        $at = $start->copy();
        $timeline = [];
        $previous = null;
        foreach ($statuses as $status) {
            if ($status === DeliveryRequest::STATUS_COLIS_RECUPERE) {
                $request->picked_up_at = $at->copy();
            }
            if ($status === DeliveryRequest::STATUS_LIVREE) {
                $request->delivered_at = $at->copy();
            }
            $timeline[] = [$previous, $status, $at->copy()];
            $previous = $status;
            $at->addMinutes(12);
        }

        $request->save();

        foreach ($timeline as [$old, $new, $time]) {
            $history = new RequestStatusHistory([
                'delivery_request_id' => $request->id,
                'changed_by' => $new === DeliveryRequest::STATUS_EN_ATTENTE ? $client->id : $driver->id,
                'old_status' => $old,
                'new_status' => $new,
                'comment' => $comments[$new],
            ]);
            $history->created_at = $time;
            $history->updated_at = $time;
            $history->save();
        }

        return $request;
    }

    private function addProof(DeliveryRequest $request, User $driver, string $type, string $path, string $label, ?string $receiverName): void
    {
        Storage::disk('public')->put($path, $this->placeholderImage($label, $request->tracking_number));

        DeliveryProof::create([
            'delivery_request_id' => $request->id,
            'uploaded_by' => $driver->id,
            'proof_type' => $type,
            'file_path' => $path,
            'receiver_name' => $receiverName,
        ]);
    }

    /**
     * @param  list<array{0: User, 1: string}>  $messages
     */
    private function addChat(DeliveryRequest $request, array $messages): void
    {
        $at = $request->created_at->copy()->addMinutes(5);

        foreach ($messages as [$sender, $content]) {
            $message = new ChatMessage([
                'delivery_request_id' => $request->id,
                'sender_id' => $sender->id,
                'content' => $content,
                'is_read' => true,
            ]);
            $message->created_at = $at->copy();
            $message->updated_at = $at->copy();
            $message->save();
            $at->addMinutes(20);
        }
    }

    /**
     * Image PNG générée (aucune vraie photo n'est versionnée dans le dépôt).
     */
    private function placeholderImage(string $label, string $trackingNumber): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            // PNG 1x1 transparent si GD est absent.
            return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
        }

        $image = imagecreatetruecolor(640, 420);
        imagefill($image, 0, 0, imagecolorallocate($image, 226, 232, 240));
        $box = imagecolorallocate($image, 180, 140, 90);
        imagefilledrectangle($image, 220, 130, 420, 290, $box);
        imagefilledrectangle($image, 305, 130, 335, 290, imagecolorallocate($image, 150, 110, 70));
        $text = imagecolorallocate($image, 30, 41, 59);
        imagestring($image, 5, 24, 24, $label, $text);
        imagestring($image, 4, 24, 380, $trackingNumber.' - image fictive de demonstration', $text);

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
