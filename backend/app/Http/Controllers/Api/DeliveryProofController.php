<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryProofRequest;
use App\Http\Requests\UpdateDeliveryProofRequest;
use App\Http\Resources\DeliveryProofResource;
use App\Models\DeliveryProof;
use Illuminate\Http\Request;

class DeliveryProofController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryProof::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return DeliveryProofResource::collection($query->latest()->get());
    }

    public function store(StoreDeliveryProofRequest $request)
    {
        $data = $request->validated();
        $data['uploaded_by'] = $request->user()->id;

        return response()->json(new DeliveryProofResource(DeliveryProof::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        return new DeliveryProofResource(DeliveryProof::findOrFail($id));
    }

    public function update(UpdateDeliveryProofRequest $request, $id)
    {
        $proof = DeliveryProof::findOrFail($id);
        $proof->update($request->validated());

        return new DeliveryProofResource($proof->refresh());
    }

    public function destroy($id, Request $request)
    {
        DeliveryProof::findOrFail($id)->delete();

        return response()->json(['message' => 'Preuve de livraison supprimée avec succès']);
    }
}
