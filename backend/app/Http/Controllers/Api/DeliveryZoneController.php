<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryZoneRequest;
use App\Http\Requests\UpdateDeliveryZoneRequest;
use App\Http\Resources\DeliveryZoneResource;
use App\Models\DeliveryZone;
use Illuminate\Http\Request;

/**
 * @group Zones & tarifs
 *
 * Gestion des zones de livraison et de leurs tarifs fixes.
 * Chaque zone a un nom, une destination/origine et un prix fixe.
 *
 * @authenticated
 */
class DeliveryZoneController extends Controller
{
    /**
     * Lister mes zones de livraison
     *
     * Retourne les zones du livreur connecté avec le nombre de livraisons par zone.
     */
    public function index(Request $request)
    {
        return DeliveryZoneResource::collection(
            DeliveryZone::where('user_id', $request->user()->id)
                ->withCount('deliveryRequests')
                ->latest()
                ->get()
        );
    }

    /**
     * Créer une zone de livraison
     *
     * Ajoute une nouvelle zone avec son tarif fixe.
     *
     * @bodyParam origin string required Lieu de départ. Example: Centre-ville
     * @bodyParam destination string required Lieu d'arrivée. Example: Al Houda
     * @bodyParam fixed_price float required Prix fixe de la livraison en DH. Example: 20.00
     */
    public function store(StoreDeliveryZoneRequest $request)
    {
        $this->authorize('create', DeliveryZone::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new DeliveryZoneResource(DeliveryZone::create($data)), 201);
    }

    /**
     * Détail d'une zone
     *
     * Retourne les informations d'une zone spécifique.
     *
     * @urlParam id int required L'identifiant de la zone. Example: 1
     */
    public function show($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('view', $zone);

        return new DeliveryZoneResource($zone);
    }

    /**
     * Modifier une zone
     *
     * Met à jour le nom ou le tarif d'une zone existante.
     *
     * @urlParam id int required L'identifiant de la zone. Example: 1
     * @bodyParam origin string Le lieu de départ. Example: Centre-ville
     * @bodyParam destination string Le lieu d'arrivée. Example: Al Houda
     * @bodyParam fixed_price float Le tarif fixe en DH. Example: 25.00
     */
    public function update(UpdateDeliveryZoneRequest $request, $id)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('update', $zone);

        $zone->update($request->validated());

        return new DeliveryZoneResource($zone->refresh());
    }

    /**
     * Supprimer une zone
     *
     * Supprime définitivement une zone de livraison.
     *
     * @urlParam id int required L'identifiant de la zone. Example: 1
     *
     * @response 200 {"message": "Zone supprimée avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('delete', $zone);

        $zone->delete();

        return response()->json(['message' => 'Zone supprimée avec succès']);
    }

    /**
     * Activer/Désactiver une zone
     *
     * Bascule le statut actif/inactif d'une zone de livraison.
     *
     * @urlParam id int required L'identifiant de la zone. Example: 1
     */
    public function toggleActive($id, Request $request)
    {
        $zone = DeliveryZone::findOrFail($id);

        $this->authorize('update', $zone);

        $zone->update(['is_active' => ! $zone->is_active]);

        return new DeliveryZoneResource($zone->refresh());
    }
}
