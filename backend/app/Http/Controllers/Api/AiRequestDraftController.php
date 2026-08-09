<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeAiRequestDraftRequest;
use App\Http\Requests\StoreAiRequestDraftRequest;
use App\Http\Requests\UpdateAiRequestDraftRequest;
use App\Http\Resources\AiRequestDraftResource;
use App\Jobs\AnalyzeAiRequestDraftJob;
use App\Models\AiRequestDraft;
use App\Models\DriverProfile;
use Illuminate\Http\Request;

/**
 * @group Assistant IA
 *
 * Brouillons de demandes générés par l'assistant IA. L'analyse transforme un message
 * libre en données structurées (destinataire, adresses, service, montant).
 *
 * @authenticated
 */
class AiRequestDraftController extends Controller
{
    /**
     * Lister mes brouillons IA
     *
     * Retourne les brouillons de l'utilisateur connecté. Pagination : 20 éléments par page.
     */
    public function index(Request $request)
    {
        return AiRequestDraftResource::collection(
            AiRequestDraft::where('user_id', $request->user()->id)->latest()->paginate(20)
        );
    }

    /**
     * Créer un brouillon IA
     *
     * Crée manuellement un brouillon de demande (sans analyse IA).
     *
     * @bodyParam input_message string required Le message décrivant la demande. Example: Envoie un colis à Sara
     * @bodyParam status string Le statut du brouillon. Example: pending
     */
    public function store(StoreAiRequestDraftRequest $request)
    {
        $this->authorize('create', AiRequestDraft::class);

        $data = $request->validated();
        $data['user_id'] = $request->user()->id;

        return response()->json(new AiRequestDraftResource(AiRequestDraft::create($data)), 201);
    }

    /**
     * Analyser un message avec l'IA
     *
     * Envoie un message libre à l'IA pour extraction de données structurées.
     * Un brouillon est créé et un job d'analyse est dispatché en arrière-plan.
     * Anti-doublon : si un brouillon identique existe (< 2 min), il est réutilisé.
     *
     * @bodyParam input_message string required Le message à analyser. Example: Envoie un colis à Sara, 420 DH à récupérer
     * @bodyParam driver_slug string required Le slug du livreur. Example: rayan-express
     *
     * @response 201 {"id": 1, "input_message": "Envoie un colis à Sara", "status": "pending", "user_id": 2}
     */
    public function analyze(AnalyzeAiRequestDraftRequest $request)
    {
        $this->authorize('create', AiRequestDraft::class);

        $driverProfile = DriverProfile::where('slug', $request->validated()['driver_slug'])->firstOrFail();

        $inputMessage = trim($request->validated()['input_message']);

        // Anti-doublon : réutiliser un draft récent avec le même message
        $existingDraft = AiRequestDraft::where('user_id', $request->user()->id)
            ->where('input_message', $inputMessage)
            ->whereIn('status', [AiRequestDraft::STATUS_PENDING, AiRequestDraft::STATUS_DONE])
            ->where('created_at', '>=', now()->subMinutes(2))
            ->first();

        if ($existingDraft) {
            return response()->json(new AiRequestDraftResource($existingDraft), 200);
        }

        $draft = AiRequestDraft::create([
            'user_id' => $request->user()->id,
            'input_message' => $inputMessage,
            'status' => AiRequestDraft::STATUS_PENDING,
        ]);

        AnalyzeAiRequestDraftJob::dispatch($draft, $driverProfile->user_id)->afterCommit();

        return response()->json(new AiRequestDraftResource($draft), 201);
    }

    /**
     * Détail d'un brouillon IA
     *
     * Retourne les détails d'un brouillon spécifique, y compris les données générées par l'IA.
     *
     * @urlParam id int required L'identifiant du brouillon. Example: 1
     */
    public function show($id, Request $request)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('view', $draft);

        return new AiRequestDraftResource($draft);
    }

    /**
     * Modifier un brouillon IA
     *
     * Met à jour les données d'un brouillon existant (avant utilisation pour créer une demande).
     *
     * @urlParam id int required L'identifiant du brouillon. Example: 1
     * @bodyParam input_message string Le message original. Example: Nouveau message
     * @bodyParam generated_data array Les données structurées générées. Example: {"recipient_name": "Sara"}
     * @bodyParam status string Le statut. Example: done
     */
    public function update(UpdateAiRequestDraftRequest $request, $id)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('update', $draft);

        $draft->update($request->validated());

        return new AiRequestDraftResource($draft->refresh());
    }

    /**
     * Supprimer un brouillon IA
     *
     * Supprime définitivement un brouillon.
     *
     * @urlParam id int required L'identifiant du brouillon. Example: 1
     *
     * @response 200 {"message": "Brouillon IA supprimé avec succès"}
     */
    public function destroy($id, Request $request)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('delete', $draft);

        $draft->delete();

        return response()->json(['message' => 'Brouillon IA supprimé avec succès']);
    }
}
