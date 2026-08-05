<?php

namespace App\Jobs;

use App\Models\ChatMessage;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CreateChatMessageNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public ChatMessage $chatMessage) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $deliveryRequest = $this->chatMessage->deliveryRequest;

        if ($this->chatMessage->sender_id === $deliveryRequest->client_id) {
            $recipientId = $deliveryRequest->driver_id;
        } elseif ($this->chatMessage->sender_id === $deliveryRequest->driver_id) {
            $recipientId = $deliveryRequest->client_id;
        } else {
            return;
        }

        if ($recipientId === null) {
            return;
        }

        Notification::create([
            'user_id' => $recipientId,
            'delivery_request_id' => $deliveryRequest->id,
            'type' => 'chat_message',
            'title' => 'Nouveau message',
            'body' => Str::limit($this->chatMessage->content, 120),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('jobs')->error('Job échoué', [
            'job' => static::class,
            'resource_id' => $this->chatMessage?->id,
            'delivery_request_id' => $this->chatMessage->delivery_request_id ?? null,
            'error' => $e->getMessage(),
        ]);
    }
}
