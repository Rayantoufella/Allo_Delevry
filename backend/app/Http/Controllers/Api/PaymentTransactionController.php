<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentTransactionRequest;
use App\Http\Requests\UpdatePaymentTransactionRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

class PaymentTransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentTransaction::query();

        if ($request->has('delivery_request_id')) {
            $query->where('delivery_request_id', $request->delivery_request_id);
        }

        return PaymentTransactionResource::collection($query->latest()->get());
    }

    public function store(StorePaymentTransactionRequest $request)
    {
        $this->authorize('create', PaymentTransaction::class);

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
