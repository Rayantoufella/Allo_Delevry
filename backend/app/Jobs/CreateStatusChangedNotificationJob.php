<?php

namespace App\Jobs;

use App\Models\DeliveryRequest;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateStatusChangedNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public DeliveryRequest $deliveryRequest,
        public string $newStatus,
        public ?int $changedBy = null,
    ) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        if ($this->changedBy === null) {
            return;
        }

        if ($this->changedBy === $this->deliveryRequest->driver_id) {
            $userId = $this->deliveryRequest->client_id;
        } elseif ($this->changedBy === $this->deliveryRequest->client_id) {
            $userId = $this->deliveryRequest->driver_id;
        } else {
            return;
        }

        if ($userId === null) {
            return;
        }

        $label = match ($this->newStatus) {
            'en_attente' => 'en attente',
            'prix_propose' => 'prix proposé',
            'confirmee' => 'confirmée',
            'colis_recupere' => 'colis récupéré',
            'en_livraison' => 'en livraison',
            'livree' => 'livrée',
            'refusee' => 'refusée',
            'echec' => 'échec',
            'annulee' => 'annulée',
        };

        Notification::create([
            'user_id' => $userId,
            'delivery_request_id' => $this->deliveryRequest->id,
            'type' => 'status_changed',
            'title' => 'Statut mis à jour',
            'body' => 'La demande '.$this->deliveryRequest->tracking_number.' est passée au statut « '.$label.' ».',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('jobs')->error('Job échoué', [
            'job' => static::class,
            'resource_id' => $this->deliveryRequest?->id,
            'error' => $e->getMessage(),
        ]);
    }
}
