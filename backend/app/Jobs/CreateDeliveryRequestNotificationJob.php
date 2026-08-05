<?php

namespace App\Jobs;

use App\Models\DeliveryRequest;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateDeliveryRequestNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public DeliveryRequest $deliveryRequest) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->deliveryRequest->driver_id,
            'delivery_request_id' => $this->deliveryRequest->id,
            'type' => 'delivery_request_created',
            'title' => 'Nouvelle demande de livraison',
            'body' => 'Un client a créé une demande ('.$this->deliveryRequest->tracking_number.').',
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
