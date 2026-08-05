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

class ChatMessageController extends Controller
{
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

        return ChatMessageResource::collection($query->latest()->paginate(20));
    }

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

    public function show($id, Request $request)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('view', $message);

        return new ChatMessageResource($message);
    }

    public function update(UpdateChatMessageRequest $request, $id)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('update', $message);

        $message->update($request->validated());

        return new ChatMessageResource($message->refresh());
    }

    public function destroy($id, Request $request)
    {
        $message = ChatMessage::findOrFail($id);

        $this->authorize('delete', $message);

        $message->delete();

        return response()->json(['message' => 'Message supprimé avec succès']);
    }
}
