<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentTransactionRequest;
use App\Http\Requests\UpdatePaymentTransactionRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\DeliveryRequest;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentTransaction::query();

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

        return PaymentTransactionResource::collection($query->latest()->get());
    }

    public function store(StorePaymentTransactionRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [PaymentTransaction::class, $deliveryRequest]);

        return response()->json(
            new PaymentTransactionResource(PaymentTransaction::create($request->validated())),
            201
        );
    }

    public function show($id, Request $request)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('view', $transaction);

        return new PaymentTransactionResource($transaction);
    }

    public function update(UpdatePaymentTransactionRequest $request, $id)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('update', $transaction);

        $transaction->update($request->validated());

        return new PaymentTransactionResource($transaction->refresh());
    }

    public function destroy($id, Request $request)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json(['message' => 'Transaction supprimée avec succès']);
    }
}
