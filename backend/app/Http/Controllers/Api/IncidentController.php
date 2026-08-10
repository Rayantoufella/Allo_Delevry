<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Http\Resources\IncidentResource;
use App\Models\DeliveryRequest;
use App\Models\Incident;
use Illuminate\Http\Request;

/**
 * @group Incidents
 *
 * Signalement d'incidents liés à une demande de livraison (retard, problème, etc.).
 *
 * @authenticated
 */
class IncidentController extends Controller
{
    /**
     * Lister les incidents
     *
     * Retourne les incidents d'une demande spécifique ou tous les incidents de l'utilisateur.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
    public function index(Request $request)
    {
        $query = Incident::query();

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

        return IncidentResource::collection($query->latest()->get());
    }

    /**
     * Signaler un incident
     *
     * Crée un signalement d'incident pour une demande de livraison.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande. Example: 1
     * @bodyParam type string required Le type d'incident. Example: retard
     * @bodyParam description string La description détaillée. Example: Le destinataire n'est pas joignable
     */
    public function store(StoreIncidentRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [Incident::class, $deliveryRequest]);

        $data = $request->validated();
        $data['reported_by'] = $request->user()->id;

        return response()->json(new IncidentResource(Incident::create($data)), 201);
    }

    /**
     * Détail d'un incident
     *
     * Retourne les détails d'un incident spécifique.
     *
     * @urlParam id int required L'identifiant de l'incident. Example: 1
     */
    public function show($id, Request $request)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('view', $incident);

        return new IncidentResource($incident);
    }

    /**
     * Modifier un incident
     *
     * Met à jour les informations d'un incident existant.
     *
     * @urlParam id int required L'identifiant de l'incident. Example: 1
     * @bodyParam description string La nouvelle description. Example: Mise à jour du problème
     * @bodyParam status string Le nouveau statut. Example: resolved
     */
    public function update(UpdateIncidentRequest $request, $id)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('update', $incident);

        $incident->update($request->validated());

        return new IncidentResource($incident->refresh());
    }

    /**
     * Supprimer un incident
     *
     * Supprime définitivement un incident.
     *
     * @urlParam id int required L'identifiant de l'incident. Example: 1
     *
     * @response 200 {"message": "Incident supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $incident = Incident::findOrFail($id);

        $this->authorize('delete', $incident);

        $incident->delete();

        return response()->json(['message' => 'Incident supprimé avec succès']);
    }
}
