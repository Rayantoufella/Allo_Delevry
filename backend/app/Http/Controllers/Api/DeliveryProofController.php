<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryProofRequest;
use App\Http\Requests\UpdateDeliveryProofRequest;
use App\Http\Resources\DeliveryProofResource;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use Illuminate\Http\Request;

class DeliveryProofController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryProof::query();

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

        return DeliveryProofResource::collection($query->latest()->get());
    }

    public function store(StoreDeliveryProofRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [DeliveryProof::class, $deliveryRequest]);

        $data = $request->validated();
        $data['uploaded_by'] = $request->user()->id;

        return response()->json(new DeliveryProofResource(DeliveryProof::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('view', $proof);

        return new DeliveryProofResource($proof);
    }

    public function update(UpdateDeliveryProofRequest $request, $id)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('update', $proof);

        $proof->update($request->validated());

        return new DeliveryProofResource($proof->refresh());
    }

    public function destroy($id, Request $request)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('delete', $proof);

        $proof->delete();

        return response()->json(['message' => 'Preuve de livraison supprimée avec succès']);
    }
}
