<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGpsLocationRequest;
use App\Http\Requests\UpdateGpsLocationRequest;
use App\Http\Resources\GpsLocationResource;
use App\Models\GpsLocation;
use Illuminate\Http\Request;

class GpsLocationController extends Controller
{
    public function index(Request $request)
    {
        $query = GpsLocation::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return GpsLocationResource::collection($query->latest()->get());
    }

    public function store(StoreGpsLocationRequest $request)
    {
        $data = $request->validated();

        if (!isset($data['recorded_at'])) {
            $data['recorded_at'] = now();
        }

        return response()->json(new GpsLocationResource(GpsLocation::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        return new GpsLocationResource(GpsLocation::findOrFail($id));
    }

    public function update(UpdateGpsLocationRequest $request, $id)
    {
        $location = GpsLocation::findOrFail($id);
        $location->update($request->validated());

        return new GpsLocationResource($location->refresh());
    }

    public function destroy($id, Request $request)
    {
        GpsLocation::findOrFail($id)->delete();

        return response()->json(['message' => 'Position GPS supprimée avec succès']);
    }
}
