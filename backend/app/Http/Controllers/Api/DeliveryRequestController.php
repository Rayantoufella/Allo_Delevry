<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Http\Resources\DeliveryRequestResource;
use App\Models\DeliveryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DeliveryRequest::with(['client', 'driver', 'service', 'deliveryZone']);

        if ($user->isClient()) {
            $query->where('client_id', $user->id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        return DeliveryRequestResource::collection($query->latest()->paginate(20));
    }

    public function store(StoreDeliveryRequest $request)
    {
        $this->authorize('create', DeliveryRequest::class);

        $data = $request->validated();
        $data['client_id'] = $request->user()->id;
        $data['tracking_number'] = 'DLV-' . strtoupper(Str::random(10));
        $data['private_token'] = Str::random(32);
        $data['status'] = DeliveryRequest::STATUS_EN_ATTENTE;

        return response()->json(new DeliveryRequestResource(DeliveryRequest::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::with([
            'client', 'driver', 'service', 'deliveryZone',
            'statusHistories', 'chatMessages', 'proofs',
            'incidents', 'gpsLocations', 'paymentTransactions',
        ])->findOrFail($id);

        $this->authorize('view', $deliveryRequest);

        return new DeliveryRequestResource($deliveryRequest);
    }

    public function update(UpdateDeliveryRequest $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('update', $deliveryRequest);

        $deliveryRequest->update($request->validated());

        return new DeliveryRequestResource($deliveryRequest->refresh());
    }

    public function destroy($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('delete', $deliveryRequest);

        $deliveryRequest->delete();

        return response()->json(['message' => 'Demande de livraison supprimée avec succès']);
    }

    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:en_attente,prix_propose,confirmee,colis_recupere,en_livraison,livree,refusee,echec,annulee'],
            'comment' => ['nullable', 'string'],
        ]);

        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('update', $deliveryRequest);

        $oldStatus = $deliveryRequest->status;
        $deliveryRequest->update(['status' => $validated['status']]);

        $deliveryRequest->statusHistories()->create([
            'changed_by' => $request->user()->id,
            'old_status' => $oldStatus,
            'new_status' => $validated['status'],
            'comment' => $validated['comment'] ?? null,
        ]);

        return new DeliveryRequestResource($deliveryRequest->refresh());
    }
}
