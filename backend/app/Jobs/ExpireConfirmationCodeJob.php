<?php

namespace App\Jobs;

use App\Models\DeliveryRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireConfirmationCodeJob implements ShouldQueue
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
        if ($this->deliveryRequest->confirmation_code_hash === null) {
            return;
        }

        $expiresAt = $this->deliveryRequest->confirmation_code_expires_at;

        if ($expiresAt === null || $expiresAt->isPast()) {
            return;
        }

        $this->deliveryRequest->update(['confirmation_code_expires_at' => now()]);
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
