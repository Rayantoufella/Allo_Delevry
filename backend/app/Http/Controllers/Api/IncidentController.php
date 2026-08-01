<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Incident::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return IncidentResource::collection($query->latest()->get());
    }

    public function store(StoreIncidentRequest $request)
    {
        $this->authorize('create', Incident::class);

        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;

        return response()->json(new IncidentResource(Incident::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('view', $incident);

        return new IncidentResource($incident);
    }

    public function update(UpdateIncidentRequest $request, $id)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('update', $incident);

        $incident->update($request->validated());

        return new IncidentResource($incident->refresh());
    }

    public function destroy($id, Request $request)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('delete', $incident);

        $incident->delete();

        return response()->json(['message' => 'Incident supprimé avec succès']);
    }
}
