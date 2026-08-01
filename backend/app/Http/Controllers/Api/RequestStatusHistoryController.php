<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestStatusHistoryRequest;
use App\Http\Requests\UpdateRequestStatusHistoryRequest;
use App\Http\Resources\RequestStatusHistoryResource;
use App\Models\DeliveryRequest;
use App\Models\RequestStatusHistory;
use Illuminate\Http\Request;

class RequestStatusHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = RequestStatusHistory::query();

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

        return RequestStatusHistoryResource::collection($query->latest()->get());
    }

    public function store(StoreRequestStatusHistoryRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [RequestStatusHistory::class, $deliveryRequest]);

        $data = $request->validated();
        $data['changed_by'] = $request->user()->id;

        return response()->json(
            new RequestStatusHistoryResource(RequestStatusHistory::create($data)),
            201
        );
    }

    public function show($id, Request $request)
    {
        $history = RequestStatusHistory::findOrFail($id);

        $this->authorize('view', $history);

        return new RequestStatusHistoryResource($history);
    }

    public function update(UpdateRequestStatusHistoryRequest $request, $id)
    {
        $history = RequestStatusHistory::findOrFail($id);

        $this->authorize('update', $history);

        $history->update($request->validated());

        return new RequestStatusHistoryResource($history->refresh());
    }

    public function destroy($id, Request $request)
    {
        $history = RequestStatusHistory::findOrFail($id);

        $this->authorize('delete', $history);

        $history->delete();

        return response()->json(['message' => 'Historique supprimé avec succès']);
    }
}
