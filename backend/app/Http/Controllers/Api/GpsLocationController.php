<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGpsLocationRequest;
use App\Http\Requests\UpdateGpsLocationRequest;
use App\Http\Resources\GpsLocationResource;
use App\Models\DeliveryRequest;
use App\Models\GpsLocation;
use Illuminate\Http\Request;

/**
 * @group GPS
 *
 * Suivi de position GPS en temps réel pour les livraisons en cours.
 * Les positions sont enregistrées par le livreur pendant le trajet.
 *
 * @authenticated
 */
class GpsLocationController extends Controller
{
    /**
     * Lister les positions GPS
     *
     * Retourne les positions d'une demande spécifique ou toutes les positions de l'utilisateur.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
    public function index(Request $request)
    {
        $query = GpsLocation::query();

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

        return GpsLocationResource::collection($query->latest()->get());
    }

    /**
     * Enregistrer une position GPS
     *
     * Ajoute un point de géolocalisation pour une demande de livraison.
     * La date d'enregistrement est définie automatiquement si non fournie.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande. Example: 1
     * @bodyParam latitude float required La latitude. Example: 30.4278
     * @bodyParam longitude float required La longitude. Example: -9.5981
     */
    public function store(StoreGpsLocationRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [GpsLocation::class, $deliveryRequest]);

        $data = $request->validated();

        if (! isset($data['recorded_at'])) {
            $data['recorded_at'] = now();
        }

        return response()->json(new GpsLocationResource(GpsLocation::create($data)), 201);
    }

    /**
     * Détail d'une position GPS
     *
     * Retourne les informations d'un point de géolocalisation spécifique.
     *
     * @urlParam id int required L'identifiant de la position. Example: 1
     */
    public function show($id, Request $request)
    {
        $location = GpsLocation::findOrFail($id);

        $this->authorize('view', $location);

        return new GpsLocationResource($location);
    }

    /**
     * Modifier une position GPS
     *
     * Met à jour les coordonnées d'un point de géolocalisation.
     *
     * @urlParam id int required L'identifiant de la position. Example: 1
     * @bodyParam latitude float La nouvelle latitude. Example: 30.4280
     * @bodyParam longitude float La nouvelle longitude. Example: -9.5983
     */
    public function update(UpdateGpsLocationRequest $request, $id)
    {
        $location = GpsLocation::findOrFail($id);

        $this->authorize('update', $location);

        $location->update($request->validated());

        return new GpsLocationResource($location->refresh());
    }

    /**
     * Supprimer une position GPS
     *
     * Supprime définitivement un point de géolocalisation.
     *
     * @urlParam id int required L'identifiant de la position. Example: 1
     *
     * @response 200 {"message": "Position GPS supprimée avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $location = GpsLocation::findOrFail($id);

        $this->authorize('delete', $location);

        $location->delete();

        return response()->json(['message' => 'Position GPS supprimée avec succès']);
    }
}
