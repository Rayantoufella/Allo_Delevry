<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        return ServiceResource::collection(
            Service::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    public function store(StoreServiceRequest $request)
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new ServiceResource(Service::create($data)), 201);
    }

    public function show($id, Request $request)
    {
        return new ServiceResource(
            Service::where('user_id', $request->user()->id)->findOrFail($id)
        );
    }

    public function update(UpdateServiceRequest $request, $id)
    {
        $service = Service::where('user_id', $request->user()->id)->findOrFail($id);
        $service->update($request->validated());

        return new ServiceResource($service->refresh());
    }

    public function destroy($id, Request $request)
    {
        Service::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return response()->json(['message' => 'Service supprimé avec succès']);
    }
}
