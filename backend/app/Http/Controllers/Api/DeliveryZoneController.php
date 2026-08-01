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
        $this->authorize('create', DeliveryZone::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new DeliveryZoneResource(DeliveryZone::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('view', $zone);

        return new DeliveryZoneResource($zone);
    }

    public function update(UpdateDeliveryZoneRequest $request, $id)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('update', $zone);

        $zone->update($request->validated());

        return new DeliveryZoneResource($zone->refresh());
    }

    public function destroy($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('delete', $zone);

        $zone->delete();

        return response()->json(['message' => 'Zone supprimée avec succès']);
    }

    public function toggleActive($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('update', $zone);

        $zone->update(['is_active' => !$zone->is_active]);

        return new DeliveryZoneResource($zone->refresh());
    }
}
