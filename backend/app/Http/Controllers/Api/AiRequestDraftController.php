<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAiRequestDraftRequest;
use App\Http\Requests\UpdateAiRequestDraftRequest;
use App\Http\Resources\AiRequestDraftResource;
use App\Models\AiRequestDraft;
use Illuminate\Http\Request;

class AiRequestDraftController extends Controller
{
    public function index(Request $request)
    {
        return AiRequestDraftResource::collection(
            AiRequestDraft::where('user_id', $request->user()->id)->latest()->paginate(20)
        );
    }

    public function store(StoreAiRequestDraftRequest $request)
    {
        $this->authorize('create', AiRequestDraft::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new AiRequestDraftResource(AiRequestDraft::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('view', $draft);

        return new AiRequestDraftResource($draft);
    }

    public function update(UpdateAiRequestDraftRequest $request, $id)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('update', $draft);

        $draft->update($request->validated());

        return new AiRequestDraftResource($draft->refresh());
    }

    public function destroy($id, Request $request)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('delete', $draft);

        $draft->delete();

        return response()->json(['message' => 'Brouillon IA supprimé avec succès']);
    }
}
