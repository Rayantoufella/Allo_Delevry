<?php

namespace App\Events;

use App\Models\DeliveryRequest;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion temps réel d'un changement de statut (F12 / F10).
 *
 * Les participants voient le statut et la timeline se mettre à jour
 * en direct, sans recharger la page de suivi.
 */
class DeliveryRequestStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DeliveryRequest $deliveryRequest,
        public ?int $changedBy = null,
        public ?string $comment = null,
    ) {}

    /**
     * Canal privé de la conversation : les participants seulement
     * (autorisation définie dans routes/channels.php).
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('conversation.'.$this->deliveryRequest->id);
    }

    /**
     * Payload minimal, sans donnée sensible (ni code, ni jeton privé).
     */
    public function broadcastWith(): array
    {
        return [
            'delivery_request_id' => $this->deliveryRequest->id,
            'tracking_number' => $this->deliveryRequest->tracking_number,
            'status' => $this->deliveryRequest->status,
            'comment' => $this->comment,
            'changed_by' => $this->changedBy,
            'updated_at' => $this->deliveryRequest->updated_at,
        ];
    }
}
