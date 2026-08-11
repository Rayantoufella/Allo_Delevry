<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Http\Resources\DeliveryRequestResource;
use App\Http\Resources\DeliveryZoneResource;
use App\Http\Resources\PublicTrackingResource;
use App\Http\Resources\ServiceResource;
use App\Jobs\CreateDeliveryRequestNotificationJob;
use App\Models\AiRequestDraft;
use App\Models\DeliveryProof;
use App\Models\DeliveryRequest;
use App\Models\DeliveryZone;
use App\Models\DriverProfile;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * @group Demandes de livraison
 *
 * Gestion complète des demandes de livraison : création, suivi, transitions de statut,
 * génération de code de confirmation, preuves et validation finale.
 *
 * @authenticated
 */
class DeliveryRequestController extends Controller
{
    /**
     * Lister mes demandes
     *
     * Retourne les demandes de livraison de l'utilisateur connecté (client ou livreur).
     * Les clients voient uniquement leurs propres demandes ; les livreurs voient toutes les demandes qui leur sont assignées.
     * Pagination : 20 éléments par page.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = DeliveryRequest::with(['client', 'driver', 'service', 'deliveryZone']);

        if ($user->isClient()) {
            $query->where('client_id', $user->id)->where('driver_id', $user->driver_id);
        } elseif ($user->isDriver()) {
            $query->where('driver_id', $user->id);
        }

        return DeliveryRequestResource::collection($query->latest()->paginate(20));
    }

    /**
     * Créer une demande (via un livreur)
     *
     * Crée une nouvelle demande de livraison dans le contexte d'un livreur spécifique.
     * Le client doit être rattaché au livreur (inscription via `/drivers/{slug}/register`).
     *
     * @urlParam slug string required Le slug du livreur. Example: rayan-express
     * @bodyParam recipient_name string required Nom du destinataire. Example: Sara
     * @bodyParam recipient_phone string required Téléphone du destinataire. Example: +212600000000
     * @bodyParam pickup_address string required Adresse de retrait. Example: 12 Rue Principale, Agadir
     * @bodyParam delivery_address string required Adresse de livraison. Example: 45 Avenue Hassan II, Agadir
     * @bodyParam service_id int ID du service choisi. Example: 1
     * @bodyParam delivery_zone_id int ID de la zone de livraison. Example: 2
     * @bodyParam package_description string Description du colis. Example: Petit carton 2kg
     * @bodyParam product_amount float Montant du produit à encaisser en DH. Example: 150.00
     * @bodyParam amount_to_collect float Montant à encaisser du destinataire en DH. Example: 42.85
     * @bodyParam ai_request_draft_id int ID du brouillon IA utilisé pour pré-remplir la demande. Example: 5
     *
     * @response 201 {"id": 1, "tracking_number": "DLV-ABC123", "status": "en_attente", "client_id": 2, "driver_id": 1}
     */
    public function storeForDriver(string $slug, StoreDeliveryRequest $request)
    {
        $profile = DriverProfile::where('slug', $slug)->firstOrFail();

        $this->authorize('create', DeliveryRequest::class);

        if ($request->user()->driver_id !== $profile->user_id) {
            abort(403, 'Vous ne pouvez créer une demande que chez votre livreur.');
        }

        $data = $request->validated();

        $this->ensureOwnedByDriver($profile, $data);

        $this->ensureDraftOwnedByClient($data['ai_request_draft_id'] ?? null, $request->user()->id);

        $data['client_id'] = $request->user()->id;
        $data['driver_id'] = $profile->user_id;
        $data['tracking_number'] = 'DLV-'.strtoupper(Str::random(10));
        $data['private_token'] = Str::random(32);
        $data['status'] = DeliveryRequest::STATUS_EN_ATTENTE;

        $deliveryRequest = DeliveryRequest::create($data);

        CreateDeliveryRequestNotificationJob::dispatch($deliveryRequest)->afterCommit();

        return response()->json(new DeliveryRequestResource($deliveryRequest), 201);
    }

    /**
     * Détail d'une demande
     *
     * Retourne le détail complet d'une demande de livraison, y compris service et zone.
     *
     * @urlParam id int required L'identifiant de la demande. Example: 1
     */
    public function show($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('view', $deliveryRequest);

        $deliveryRequest->loadMissing(['service', 'deliveryZone']);

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Modifier une demande
     *
     * Met à jour une demande non encore en cours de livraison.
     *
     * @urlParam deliveryRequest int required L'identifiant de la demande. Example: 1
     * @bodyParam recipient_name string Nom du destinataire. Example: Sara
     * @bodyParam delivery_address string Adresse de livraison. Example: Nouvelle adresse
     */
    public function update(UpdateDeliveryRequest $request, DeliveryRequest $deliveryRequest)
    {
        $this->authorize('update', $deliveryRequest);

        if (! $deliveryRequest->isEditable()) {
            throw ValidationException::withMessages([
                'status' => 'Une demande en cours de livraison ou terminée ne peut plus être modifiée.',
            ]);
        }

        $data = $request->validated();

        $this->ensureBelongsToDriver($deliveryRequest->driver_id, $data);

        $deliveryRequest->update($data);

        return new DeliveryRequestResource($deliveryRequest->refresh());
    }

    /**
     * Supprimer une demande
     *
     * Supprime une demande uniquement si elle est à un statut terminal (livrée, annulée, refusée).
     *
     * @urlParam id int required L'identifiant de la demande. Example: 1
     *
     * @response 200 {"message": "Demande de livraison supprimée avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('delete', $deliveryRequest);

        if (! $deliveryRequest->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => 'Seules les demandes aux statuts terminaux peuvent être supprimées.',
            ]);
        }

        $deliveryRequest->delete();

        return response()->json(['message' => 'Demande de livraison supprimée avec succès']);
    }

    /**
     * Mettre à jour le statut d'une demande
     *
     * Fait transitionner une demande vers un nouveau statut (selon les règles métier).
     * La photo de récupération (pickup_photo) est requise avant le passage à "colis_recupere".
     *
     * @urlParam id int required L'identifiant de la demande. Example: 1
     * @bodyParam status string required Nouveau statut. Example: en_livraison
     * @bodyParam comment string Commentaire optionnel. Example: Colis récupéré
     * @bodyParam proposed_price float Prix proposé (requis pour le statut "prix_propose"). Example: 25.00
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(DeliveryRequest::statuses())],
            'comment' => ['nullable', 'string'],
            'proposed_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('updateStatus', $deliveryRequest);

        $newStatus = $validated['status'];

        if (! $deliveryRequest->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => "Transition non autorisée : '{$deliveryRequest->status}' → '{$newStatus}'.",
            ]);
        }

        if ($newStatus === DeliveryRequest::STATUS_PRIX_PROPOSE && empty($validated['proposed_price'])) {
            throw ValidationException::withMessages([
                'proposed_price' => 'Le prix proposé est requis pour passer au statut "prix_propose".',
            ]);
        }

        // RG06 (récupération) : la photo de récupération du colis est obligatoire
        // avant le passage au statut "colis_recupere".
        if ($newStatus === DeliveryRequest::STATUS_COLIS_RECUPERE
            && ! $deliveryRequest->proofs()->where('proof_type', DeliveryProof::TYPE_PICKUP_PHOTO)->exists()) {
            throw ValidationException::withMessages([
                'proof' => 'La photo de récupération du colis est requise avant le passage au statut "colis_recupere".',
            ]);
        }

        if (! empty($validated['proposed_price'])) {
            $deliveryRequest->proposed_price = $validated['proposed_price'];
        }

        $deliveryRequest->transitionTo(
            $newStatus,
            changedBy: $request->user()->id,
            comment: $validated['comment'] ?? null,
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Confirmer le prix proposé
     *
     * Le client confirme le prix proposé par le livreur (transition vers "confirmee").
     *
     * @urlParam deliveryRequest int required L'identifiant de la demande. Example: 1
     */
    public function confirmPrice(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('confirmPrice', $deliveryRequest);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_PRIX_PROPOSE) {
            throw ValidationException::withMessages([
                'status' => 'Le prix ne peut être confirmé que sur une demande au statut "prix_propose".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_CONFIRMEE,
            changedBy: $request->user()->id,
            comment: 'Prix proposé confirmé par le client',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Annuler une demande
     *
     * Annule une demande aux statuts "en_attente" ou "prix_propose".
     *
     * @urlParam deliveryRequest int required L'identifiant de la demande. Example: 1
     */
    public function cancel(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('cancel', $deliveryRequest);

        if (! $deliveryRequest->canBeCancelled()) {
            throw ValidationException::withMessages([
                'status' => 'Une demande ne peut être annulée qu\'aux statuts "en_attente" ou "prix_propose".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_ANNULEE,
            changedBy: $request->user()->id,
            comment: 'Demande annulée par le client',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Suivi public d'une demande
     *
     * Affiche les informations de suivi d'une demande via son jeton privé.
     * Accessible sans authentification. Inclut l'historique des statuts, les participants,
     * le service, la zone, le chat et les preuves de livraison.
     *
     * @unauthenticated
     *
     * @urlParam privateToken string required Le jeton privé de suivi. Example: abc123def456...
     */
    public function tracking(string $privateToken)
    {
        $deliveryRequest = DeliveryRequest::with([
            'statusHistories',
            'client',
            'driver.driverProfile',
            'service',
            'deliveryZone',
            'chatMessages.sender',
            'proofs',
        ])->where('private_token', $privateToken)->firstOrFail();

        return new PublicTrackingResource($deliveryRequest);
    }

    /**
     * Confirmer l'arrivée du livreur
     *
     * Le livreur confirme qu'il est arrivé à l'adresse de livraison
     * (transition "en_livraison" → "livreur_arrive"). Tous les boutons de
     * changement de statut sont côté livreur : le client n'a aucun bouton.
     *
     * @urlParam id int required L'identifiant de la demande. Example: 1
     */
    public function confirmArrival(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('confirmArrival', $deliveryRequest);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_EN_LIVRAISON) {
            throw ValidationException::withMessages([
                'status' => 'L\'arrivée du livreur ne peut être confirmée qu\'au statut "en_livraison".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_LIVREUR_ARRIVE,
            changedBy: $request->user()->id,
            comment: 'Le livreur confirme qu\'il est arrivé à domicile',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    /**
     * Confirmer la remise de la commande
     *
     * Le livreur confirme que le client a récupéré la commande (transition
     * "livreur_arrive" → "livree"). Le client ne dispose d'aucun bouton de
     * clôture : c'est le livreur, présent sur place, qui valide la remise.
     *
     * @urlParam id int required L'identifiant de la demande. Example: 1
     */
    public function confirmHandover(Request $request, $id)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($id);

        $this->authorize('confirmHandover', $deliveryRequest);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_LIVREUR_ARRIVE) {
            throw ValidationException::withMessages([
                'status' => 'La remise ne peut être confirmée qu\'au statut "livreur_arrive".',
            ]);
        }

        $deliveryRequest->transitionTo(
            DeliveryRequest::STATUS_LIVREE,
            changedBy: $request->user()->id,
            comment: 'Livreur confirme la remise : colis récupéré par le client',
        );

        return new DeliveryRequestResource($deliveryRequest);
    }

    private function ensureOwnedByDriver(DriverProfile $profile, array $data): void
    {
        $this->ensureBelongsToDriver($profile->user_id, $data);
    }

    private function ensureDraftOwnedByClient(?int $draftId, int $clientUserId): void
    {
        if ($draftId !== null
            && ! AiRequestDraft::where('id', $draftId)->where('user_id', $clientUserId)->exists()) {
            throw ValidationException::withMessages([
                'ai_request_draft_id' => 'Ce brouillon IA n\'appartient pas à ce client.',
            ]);
        }
    }

    private function ensureBelongsToDriver(int $driverUserId, array $data): void
    {
        if (! empty($data['service_id'])
            && ! Service::where('id', $data['service_id'])->where('user_id', $driverUserId)->exists()) {
            throw ValidationException::withMessages([
                'service_id' => 'Ce service n\'appartient pas à ce livreur.',
            ]);
        }

        if (! empty($data['delivery_zone_id'])
            && ! DeliveryZone::where('id', $data['delivery_zone_id'])->where('user_id', $driverUserId)->exists()) {
            throw ValidationException::withMessages([
                'delivery_zone_id' => 'Cette zone de livraison n\'appartient pas à ce livreur.',
            ]);
        }
    }
}
