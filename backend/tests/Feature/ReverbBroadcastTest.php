<?php

use App\Events\ChatMessageReceived;
use App\Events\DeliveryRequestStatusUpdated;
use App\Models\DeliveryRequest;
use App\Models\User;
use Illuminate\Support\Facades\Event;

/**
 * Diffusion temps réel (F12 — Reverb).
 *
 * Chaque message de chat et chaque changement de statut doit être diffusé
 * sur le canal privé "conversation.{id}" réservé aux participants.
 * En environnement de test (BROADCAST_CONNECTION=null), on vérifie le
 * déclenchement des événements et leur canal via Event::fake().
 */

it('diffuse un message de chat sur le canal privé de la conversation', function () {
    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    Event::fake([ChatMessageReceived::class]);

    $this->actingAs($client)->postJson('/api/chat-messages', [
        'delivery_request_id' => $deliveryRequest->id,
        'message_type' => 'text',
        'content' => 'Bonjour, j\'arrive dans 10 minutes.',
    ])->assertCreated();

    Event::assertDispatched(ChatMessageReceived::class, function (ChatMessageReceived $event) use ($deliveryRequest) {
        // Le canal privé doit cibler la bonne conversation
        // (préfixe "private-" ajouté par le framework aux canaux privés).
        return $event->broadcastOn()->name === 'private-conversation.'.$deliveryRequest->id;
    });
});

it('diffuse un changement de statut sur le canal privé de la conversation', function () {
    $client = User::factory()->client()->create();
    $driver = User::factory()->driver()->create();
    $deliveryRequest = DeliveryRequest::factory()
        ->forClient($client)
        ->forDriver($driver)
        ->create();

    Event::fake([DeliveryRequestStatusUpdated::class]);

    $this->actingAs($driver)->patchJson('/api/delivery-requests/'.$deliveryRequest->id.'/status', [
        'status' => DeliveryRequest::STATUS_PRIX_PROPOSE,
        'proposed_price' => 75.50,
        'comment' => 'Prix proposé par le livreur',
    ])->assertOk();

    Event::assertDispatched(DeliveryRequestStatusUpdated::class, function (DeliveryRequestStatusUpdated $event) use ($deliveryRequest) {
        // Le canal privé doit cibler la bonne conversation
        // (préfixe "private-" ajouté par le framework aux canaux privés).
        return $event->broadcastOn()->name === 'private-conversation.'.$deliveryRequest->id;
    });
});