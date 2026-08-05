<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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

        return RequestStatusHistoryResource::collection($query->latest()->paginate(20));
    }

    public function show($id, Request $request)
    {
        $history = RequestStatusHistory::findOrFail($id);

        $this->authorize('view', $history);

        return new RequestStatusHistoryResource($history);
    }
}
