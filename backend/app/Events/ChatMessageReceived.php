<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Diffusion temps réel d'un nouveau message de chat (F12).
 *
 * Le frontend écoute le canal privé "conversation.{id}" pour ajouter le
 * message à la conversation sans recharger la page.
 */
class ChatMessageReceived implements ShouldBroadcast
{
    use Dispatchable, SerializesModels;

    public function __construct(public ChatMessage $message) {}

    /**
     * Canal privé de la conversation : les participants seulement
     * (autorisation définie dans routes/channels.php).
     */
    public function broadcastOn(): Channel
    {
        return new PrivateChannel('conversation.'.$this->message->delivery_request_id);
    }

    /**
     * Payload minimal, sans donnée sensible (ni code, ni jeton privé).
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->message->id,
            'delivery_request_id' => $this->message->delivery_request_id,
            'sender' => [
                'id' => $this->message->sender_id,
                'name' => $this->message->sender?->name,
            ],
            'message_type' => $this->message->message_type,
            'content' => $this->message->content,
            'created_at' => $this->message->created_at,
        ];
    }
}
