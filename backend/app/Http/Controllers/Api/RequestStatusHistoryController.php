<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestStatusHistoryRequest;
use App\Http\Requests\UpdateRequestStatusHistoryRequest;
use App\Http\Resources\RequestStatusHistoryResource;
use App\Models\RequestStatusHistory;
use Illuminate\Http\Request;

class RequestStatusHistoryController extends Controller
{
    public function index(Request $request)
    {
        $query = RequestStatusHistory::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return RequestStatusHistoryResource::collection($query->latest()->get());
    }

    public function store(StoreRequestStatusHistoryRequest $request)
    {
        $data = $request->validated();
        $data['changed_by'] = $request->user()->id;

        return response()->json(
            new RequestStatusHistoryResource(RequestStatusHistory::create($data)),
            201
        );
    }

    public function show($id, Request $request)
    {
        return new RequestStatusHistoryResource(RequestStatusHistory::findOrFail($id));
    }

    public function update(UpdateRequestStatusHistoryRequest $request, $id)
    {
        $history = RequestStatusHistory::findOrFail($id);
        $history->update($request->validated());

        return new RequestStatusHistoryResource($history->refresh());
    }

    public function destroy($id, Request $request)
    {
        RequestStatusHistory::findOrFail($id)->delete();

        return response()->json(['message' => 'Historique supprimé avec succès']);
    }
}
