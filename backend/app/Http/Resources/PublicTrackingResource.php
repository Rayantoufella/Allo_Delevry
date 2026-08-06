<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource publique du suivi privé d'une demande de livraison (F11).
 *
 * Exposée via GET /api/tracking/{privateToken} sans authentification :
 * destinée à la page de suivi du frontend, elle ne contient aucune donnée
 * sensible (pas de jeton privé, ni de code de confirmation).
 * La dernière position GPS est exclue volontairement (bonus ultérieur).
 */
class PublicTrackingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // Identité et statut courant
            'tracking_number' => $this->tracking_number,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at,
            'picked_up_at' => $this->picked_up_at,
            'delivered_at' => $this->delivered_at,

            // Adresses et destinataire
            'pickup_address' => $this->pickup_address,
            'delivery_address' => $this->delivery_address,
            'recipient_name' => $this->recipient_name,

            // Informations utiles (F11) : expéditeur, livreur, service, zone
            'client' => $this->whenLoaded('client', fn () => [
                'name' => $this->client->name,
                'phone' => $this->client->phone,
            ]),
            'driver' => $this->whenLoaded('driver', fn () => [
                'name' => $this->driver->name,
                'phone' => $this->driver->phone,
                'brand_name' => $this->driver->driverProfile?->brand_name,
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'name' => $this->service->name,
            ]),
            'delivery_zone' => $this->whenLoaded('deliveryZone', fn () => [
                'origin_zone' => $this->deliveryZone->origin_zone,
                'destination_zone' => $this->deliveryZone->destination_zone,
            ]),

            // Timeline des statuts (historique ordonné)
            'timeline' => $this->statusHistories->map(fn ($history) => [
                'old_status' => $history->old_status,
                'new_status' => $history->new_status,
                'comment' => $history->comment,
                'created_at' => $history->created_at,
            ]),

            // Chat (F11) : 20 derniers échanges, expéditeur + contenu
            'chat_messages' => $this->whenLoaded('chatMessages', fn () => $this->chatMessages
                ->sortByDesc('created_at')
                ->take(20)
                ->map(fn ($message) => [
                    'sender_name' => $message->sender?->name,
                    'content' => $message->content,
                    'created_at' => $message->created_at,
                ])
                ->values()),

            // Preuves de livraison (F11) : photo, ticket ou signature
            'proofs' => $this->whenLoaded('proofs', fn () => $this->proofs->map(fn ($proof) => [
                'proof_type' => $proof->proof_type,
                'file_url' => $proof->file_path ? asset('storage/'.$proof->file_path) : null,
                'receiver_name' => $proof->receiver_name,
            ])),
        ];
    }
}
