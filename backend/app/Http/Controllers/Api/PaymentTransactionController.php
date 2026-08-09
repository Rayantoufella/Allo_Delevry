<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePaymentTransactionRequest;
use App\Http\Requests\UpdatePaymentTransactionRequest;
use App\Http\Resources\PaymentTransactionResource;
use App\Models\DeliveryRequest;
use App\Models\PaymentTransaction;
use Illuminate\Http\Request;

/**
 * @group Paiements
 *
 * Transactions de paiement liées aux livraisons (espèces, virement, etc.).
 *
 * @authenticated
 */
class PaymentTransactionController extends Controller
{
    /**
     * Lister les transactions
     *
     * Retourne les transactions d'une demande spécifique ou toutes les transactions de l'utilisateur.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
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

    /**
     * Enregistrer une transaction
     *
     * Crée une nouvelle transaction de paiement pour une demande.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande. Example: 1
     * @bodyParam amount float required Le montant en DH. Example: 42.85
     * @bodyParam type string required Le type de transaction. Example: payment
     * @bodyParam method string La méthode de paiement. Example: cash
     */
    public function store(StorePaymentTransactionRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [PaymentTransaction::class, $deliveryRequest]);

        return response()->json(
            new PaymentTransactionResource(PaymentTransaction::create($request->validated())),
            201
        );
    }

    /**
     * Détail d'une transaction
     *
     * Retourne les informations d'une transaction spécifique.
     *
     * @urlParam id int required L'identifiant de la transaction. Example: 1
     */
    public function show($id, Request $request)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('view', $transaction);

        return new PaymentTransactionResource($transaction);
    }

    /**
     * Modifier une transaction
     *
     * Met à jour les informations d'une transaction existante.
     *
     * @urlParam id int required L'identifiant de la transaction. Example: 1
     * @bodyParam amount float Le nouveau montant. Example: 50.00
     * @bodyParam status string Le nouveau statut. Example: completed
     */
    public function update(UpdatePaymentTransactionRequest $request, $id)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('update', $transaction);

        $transaction->update($request->validated());

        return new PaymentTransactionResource($transaction->refresh());
    }

    /**
     * Supprimer une transaction
     *
     * Supprime définitivement une transaction de paiement.
     *
     * @urlParam id int required L'identifiant de la transaction. Example: 1
     *
     * @response 200 {"message": "Transaction supprimée avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $transaction = PaymentTransaction::findOrFail($id);

        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->json(['message' => 'Transaction supprimée avec succès']);
    }
}
