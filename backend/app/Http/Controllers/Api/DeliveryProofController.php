<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryProofRequest;
use App\Http\Requests\UpdateDeliveryProofRequest;
use App\Http\Resources\DeliveryProofResource;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * @group Preuves
 *
 * Preuves de livraison (photos, signatures, tickets).
 * La photo de récupération du colis (pickup_photo) est obligatoire avant le statut "colis_recupere".
 *
 * @authenticated
 */
class DeliveryProofController extends Controller
{
    /**
     * Lister les preuves
     *
     * Retourne les preuves d'une demande spécifique ou toutes les preuves de l'utilisateur.
     *
     * @query delivery_request_id int Filtrer par ID de demande. Example: 1
     */
    public function index(Request $request)
    {
        $query = DeliveryProof::query();

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

        return DeliveryProofResource::collection($query->latest()->get());
    }

    /**
     * Ajouter une preuve
     *
     * Upload un fichier de preuve (photo, signature, ticket) pour une demande.
     * Le fichier est stocké sur le disque `public`.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande. Example: 1
     * @bodyParam proof_type string required Le type de preuve (photo, signature, ticket, pickup_photo, pickup_id_card). Example: pickup_photo
     * @bodyParam file file required Le fichier de preuve (image/jpeg, image/png).
     */
    public function store(StoreDeliveryProofRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [DeliveryProof::class, $deliveryRequest]);

        $data = $request->validated();
        $data['uploaded_by'] = $request->user()->id;
        $data['file_path'] = $request->file('file')->store('proofs', 'public');
        unset($data['file']);

        return response()->json(new DeliveryProofResource(DeliveryProof::create($data)), 201);
    }

    /**
     * Détail d'une preuve
     *
     * Retourne les informations d'une preuve spécifique.
     *
     * @urlParam id int required L'identifiant de la preuve. Example: 1
     */
    public function show($id, Request $request)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('view', $proof);

        return new DeliveryProofResource($proof);
    }

    /**
     * Modifier une preuve
     *
     * Remplace le fichier ou modifie le type d'une preuve existante.
     *
     * @urlParam id int required L'identifiant de la preuve. Example: 1
     * @bodyParam proof_type string Le nouveau type. Example: photo
     * @bodyParam file file Le nouveau fichier.
     */
    public function update(UpdateDeliveryProofRequest $request, $id)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('update', $proof);

        $data = $request->validated();

        if ($request->hasFile('file')) {
            if ($proof->file_path) {
                Storage::disk('public')->delete($proof->file_path);
            }
            $data['file_path'] = $request->file('file')->store('proofs', 'public');
            unset($data['file']);
        }

        $proof->update($data);

        return new DeliveryProofResource($proof->refresh());
    }

    /**
     * Supprimer une preuve
     *
     * Supprime définitivement une preuve et son fichier associé.
     *
     * @urlParam id int required L'identifiant de la preuve. Example: 1
     *
     * @response 200 {"message": "Preuve de livraison supprimée avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $proof = DeliveryProof::findOrFail($id);

        $this->authorize('delete', $proof);

        $proof->delete();

        return response()->json(['message' => 'Preuve de livraison supprimée avec succès']);
    }
}
