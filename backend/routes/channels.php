<?php

use App\Models\DeliveryRequest;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (F12 — temps réel Reverb)
|--------------------------------------------------------------------------
*/

/**
 * Canal privé de conversation d'une demande de livraison.
 *
 * Réservé aux deux participants (client ou livreur) : les événements de
 * chat (ChatMessageReceived) et de changement de statut
 * (DeliveryRequestStatusUpdated) y sont diffusés en temps réel.
 */
Broadcast::channel('conversation.{deliveryRequestId}', function ($user, int $deliveryRequestId) {
    return DeliveryRequest::where('id', $deliveryRequestId)
        ->where(function ($query) use ($user) {
            $query->where('client_id', $user->id)->orWhere('driver_id', $user->id);
        })
        ->exists();
}, ['guards' => ['sanctum']]);
