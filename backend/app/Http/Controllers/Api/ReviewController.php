<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Http\Resources\ReviewResource;
use App\Models\DeliveryRequest;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Avis
 *
 * Système de notation et d'avis sur les livraisons terminées.
 * Un avis ne peut être laissé qu'une seule fois par demande livrée.
 *
 * @authenticated
 */
class ReviewController extends Controller
{
    /**
     * Lister mes avis
     *
     * Retourne les avis laissés par l'utilisateur connecté.
     */
    public function index(Request $request)
    {
        return ReviewResource::collection(
            Review::where('user_id', $request->user()->id)->latest()->get()
        );
    }

    /**
     * Laisser un avis
     *
     * Crée un avis sur une demande livrée. Un seul avis par demande et par utilisateur.
     *
     * @bodyParam delivery_request_id int required L'ID de la demande livrée. Example: 1
     * @bodyParam rating int required Note de 1 à 5. Example: 4
     * @bodyParam comment string Commentaire optionnel. Example: Très bon service, rapide et efficace
     */
    public function store(StoreReviewRequest $request)
    {
        $deliveryRequest = DeliveryRequest::findOrFail($request->validated()['delivery_request_id']);
        $this->authorize('create', [Review::class, $deliveryRequest]);

        if ($deliveryRequest->status !== DeliveryRequest::STATUS_LIVREE) {
            throw ValidationException::withMessages([
                'delivery_request_id' => 'Un avis ne peut être laissé que sur une demande livrée.',
            ]);
        }

        if (Review::where('delivery_request_id', $deliveryRequest->id)->where('user_id', $request->user()->id)->exists()) {
            throw ValidationException::withMessages([
                'delivery_request_id' => 'Vous avez déjà laissé un avis pour cette demande.',
            ]);
        }

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new ReviewResource(Review::create($data)), 201);
    }

    /**
     * Détail d'un avis
     *
     * Retourne les détails d'un avis spécifique.
     *
     * @urlParam id int required L'identifiant de l'avis. Example: 1
     */
    public function show($id, Request $request)
    {
        $review = Review::findOrFail($id);

        $this->authorize('view', $review);

        return new ReviewResource($review);
    }

    /**
     * Modifier un avis
     *
     * Met à jour le commentaire ou la note d'un avis existant.
     *
     * @urlParam id int required L'identifiant de l'avis. Example: 1
     * @bodyParam rating int La nouvelle note. Example: 5
     * @bodyParam comment string Le nouveau commentaire. Example: Service excellent
     */
    public function update(UpdateReviewRequest $request, $id)
    {
        $review = Review::findOrFail($id);

        $this->authorize('update', $review);

        $review->update($request->validated());

        return new ReviewResource($review->refresh());
    }

    /**
     * Supprimer un avis
     *
     * Supprime définitivement un avis.
     *
     * @urlParam id int required L'identifiant de l'avis. Example: 1
     *
     * @response 200 {"message": "Avis supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $review = Review::findOrFail($id);

        $this->authorize('delete', $review);

        $review->delete();

        return response()->json(['message' => 'Avis supprimé avec succès']);
    }
}
