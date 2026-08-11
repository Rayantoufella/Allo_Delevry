<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\UpdateChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Jobs\CreateChatMessageNotificationJob;
use App\Models\ChatMessage;
use App\Models\DeliveryRequest;
use Illuminate\Http\Request;

/**
 * @group Chat
 *
 * Messages de chat entre clients et livreurs pour une demande de livraison.
 * Les messages sont persistés et consultés par polling (pas de temps réel).
 *
 * @authenticated
 */
class ChatMessageController extends Controller
{
    /**
     * Lister les messages
     *
     * Retourne les messages d'une demande spécifique ou tous les messages de l'utilisateur.
     * Pagination : 20 éléments par page.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
    public function index(Request $request)
    {
        $query = ChatMessage::query();

        if ($request->has('delivery_request_id')) {
            $deliveryRequest = DeliveryRequest::findOrFail($request->delivery_request_id);
            $this->authorize('view', $deliveryRequest);
            $query->where('delivery_request_id', $deliveryRequest->id);
        } else {
            $user = $request->user();
            $query->whereHas('deliveryRequest', function ($q) use ($user) {
                $q->where('client_id', $user->id)->orWhere('driver_id', $user->id);
            });
        }

        return ChatMessageResource::collection($query->oldest()->paginate(20));
    }

    /**
     * Envoyer un message
     *
     * Envoie un message dans le chat d'une demande de livraison.
     * Le destinataire est notifié (notification interne) et récupère
     * le message par polling.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande. Example: 1
     * @bodyParam content string required Le contenu du message. Example: Bonjour, j'arrive dans 10 min
     */
    public function store(StoreChatMessageRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [ChatMessage::class, $deliveryRequest]);

        $data = $request->validated();
        $data['sender_id'] = $request->user()->id;

        $message = ChatMessage::create($data);

        CreateChatMessageNotificationJob::dispatch($message)->afterCommit();

        return response()->json(new ChatMessageResource($message), 201);
    }

    /**
     * Détail d'un message
     *
     * Retourne les détails d'un message spécifique.
     *
     * @urlParam id int required L'identifiant du message. Example: 1
     */
    public function show($id, Request $request)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('view', $message);

        return new ChatMessageResource($message);
    }

    /**
     * Modifier un message
     *
     * Met à jour le contenu d'un message existant.
     *
     * @urlParam id int required L'identifiant du message. Example: 1
     * @bodyParam content string Le nouveau contenu. Example: Message modifié
     */
    public function update(UpdateChatMessageRequest $request, $id)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('update', $message);

        $message->update($request->validated());

        return new ChatMessageResource($message->refresh());
    }

    /**
     * Supprimer un message
     *
     * Supprime définitivement un message du chat.
     *
     * @urlParam id int required L'identifiant du message. Example: 1
     *
     * @response 200 {"message": "Message supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(['message' => 'Message supprimé avec succès']);
    }
}
