<?php

use App\Models\ChatMessage;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\RequestStatusHistory;
use App\Models\Service;
use App\Models\User;

/**
 * Suivi privé d'une demande (F11 — AR-37/40).
 *
 * GET /api/tracking/{privateToken} : page publique de suivi accessible
 * sans authentification via le jeton privé. Doit exposer le numéro de suivi,
 * la timeline, les adresses, les participants (client/livreur + marque),
 * le service, la zone, le chat et les preuves — sans aucune donnée sensible.
 */

it('renvoie le suivi complet d\'une demande via le jeton privé', function () {
    // Données de base : client, livreur (avec marque), service et zone.
    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'brand_name' => 'Allo Express',
        'is_available' => true,
    ]);
    $service = Service::factory()->create();
    $zone = DeliveryZone::factory()->create([
        'origin_zone' => 'Casablanca',
        'destination_zone' => 'Rabat',
    ]);

    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create([
            'service_id' => $service->id,
            'delivery_zone_id' => $zone->id,
        ]);

    // Historique de statuts + un échange de chat + une preuve de type ticket.
    RequestStatusHistory::create([
        'delivery_request_id' => $deliveryRequest->id,
        'changed_by' => $client->id,
        'old_status' => DeliveryRequest::STATUS_EN_ATTENTE,
        'new_status' => DeliveryRequest::STATUS_CONFIRMEE,
        'comment' => 'Prix confirmé',
    ]);

    ChatMessage::create([
        'delivery_request_id' => $deliveryRequest->id,
        'sender_id' => $client->id,
        'message_type' => 'text',
        'content' => 'Bonjour, quand arrivez-vous ?',
    ]);

    DeliveryProof::create([
        'delivery_request_id' => $deliveryRequest->id,
        'uploaded_by' => $driver->id,
        'proof_type' => 'ticket',
        'file_path' => 'proofs/ticket-example.jpg',
        'receiver_name' => 'Jean Dupont',
    ]);

    // Le suivi doit être accessible SANS authentification (lien public).
    // La réponse est un JsonResource : les données sont sous la clé "data".
    $this->getJson('/api/tracking/'.$deliveryRequest->private_token)
        ->assertOk()
        ->assertJsonPath('data.tracking_number', $deliveryRequest->tracking_number)
        ->assertJsonPath('data.status', $deliveryRequest->status)
        ->assertJsonPath('data.pickup_address', $deliveryRequest->pickup_address)
        ->assertJsonPath('data.delivery_address', $deliveryRequest->delivery_address)
        ->assertJsonPath('data.client.name', $client->name)
        ->assertJsonPath('data.driver.name', $driver->name)
        ->assertJsonPath('data.driver.brand_name', 'Allo Express')
        ->assertJsonPath('data.service.name', $service->name)
        ->assertJsonPath('data.delivery_zone.origin_zone', 'Casablanca')
        ->assertJsonPath('data.delivery_zone.destination_zone', 'Rabat')
        ->assertJsonPath('data.timeline.0.new_status', DeliveryRequest::STATUS_CONFIRMEE)
        ->assertJsonPath('data.timeline.0.comment', 'Prix confirmé')
        ->assertJsonPath('data.chat_messages.0.sender_name', $client->name)
        ->assertJsonPath('data.chat_messages.0.content', 'Bonjour, quand arrivez-vous ?')
        ->assertJsonPath('data.proofs.0.proof_type', 'ticket')
        ->assertJsonPath('data.proofs.0.receiver_name', 'Jean Dupont');
});

it('renvoie une erreur 404 pour un jeton privé inconnu', function () {
    $this->getJson('/api/tracking/jeton-inconnu')->assertNotFound();
});

it('ne révèle aucun jeton privé ni code de confirmation', function () {
    $deliveryRequest = DeliveryRequest::factory()->create();

    $this->getJson('/api/tracking/'.$deliveryRequest->private_token)
        ->assertOk()
        ->assertJsonMissingPath('data.private_token')
        ->assertJsonMissingPath('data.confirmation_code_hash');
});