<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestStatusHistoryResource;
use App\Models\DeliveryRequest;
use App\Models\RequestStatusHistory;
use Illuminate\Http\Request;

/**
 * @group Historique des statuts
 *
 * Historique des transitions de statut d'une demande de livraison.
 * Chaque changement de statut est enregistré avec l'utilisateur, la date et un commentaire.
 *
 * @authenticated
 */
class RequestStatusHistoryController extends Controller
{
    /**
     * Lister l'historique
     *
     * Retourne l'historique des statuts d'une demande spécifique ou toutes les entrées de l'utilisateur.
     * Pagination : 20 éléments par page.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
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

    /**
     * Détail d'une entrée d'historique
     *
     * Retourne les détails d'une transition de statut spécifique.
     *
     * @urlParam id int required L'identifiant de l'entrée. Example: 1
     */
    public function show($id, Request $request)
    {
        $history = RequestStatusHistory::findOrFail($id);

        $this->authorize('view', $history);

        return new RequestStatusHistoryResource($history);
    }
}
