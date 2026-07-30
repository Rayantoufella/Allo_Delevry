<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryZoneRequest;
use App\Http\Requests\UpdateDeliveryZoneRequest;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;

class DeliveryZoneController extends Controller
{
    public function index(Request $request)
    {
        return DeliveryZoneResource::collection(
            DeliveryZone::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function store(StoreDeliveryZoneRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new DeliveryZoneResource(DeliveryZone::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        return new DeliveryZoneResource(
            DeliveryZone::where('user_id', $request->user()->id)->findOrFail($id)
        );
    }

    public function update(UpdateDeliveryZoneRequest $request, $id)
    {
        $zone = DeliveryZone::where('user_id', $request->user()->id)->findOrFail($id);
        $zone->update($request->validated());

        return new DeliveryZoneResource($zone->refresh());
    }

    public function destroy($id, Request $request)
    {
        DeliveryZone::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => 'Zone supprimée avec succès']);
    }

    public function toggleActive($id, Request $request)
    {
        $zone = DeliveryZone::where('user_id', $request->user()->id)->findOrFail($id);
        $zone->update(['is_active' => !$zone->is_active]);

        return new DeliveryZoneResource($zone->refresh());
    }
}
