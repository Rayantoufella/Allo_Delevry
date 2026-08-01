<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChatMessageRequest;
use App\Http\Requests\UpdateChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function index(Request $request)
    {
        $query = ChatMessage::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return ChatMessageResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreChatMessageRequest $request)
    {
        $this->authorize('create', ChatMessage::class);

        $data = $request->validated();
        $data['sender_id'] = $request->user()->id;

        return response()->json(new ChatMessageResource(ChatMessage::create($data)), 201);
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
