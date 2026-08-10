<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendAiChatMessageRequest;
use App\Http\Requests\StoreAiRequestDraftRequest;
use App\Http\Requests\UpdateAiRequestDraftRequest;
use App\Http\Resources\AiRequestDraftResource;
use App\Jobs\ProcessAiChatMessageJob;
use App\Models\AiRequestDraft;
use App\Models\DriverProfile;
use Illuminate\Http\Request;

/**
 * @group Assistant IA
 *
 * Brouillons de demandes générés par l'assistant IA. La conversation en mode chat
 * transforme un message libre en données structurées (destinataire, adresses, service, montant).
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
     * Démarrer une conversation IA
     *
     * Crée un brouillon vide en mode conversation : le client et l'IA échangent des
     * messages pour compléter le formulaire de demande progressivement.
     *
     * @response 201 {"id": 1, "status": "pending", "chat_history": [], "user_id": 2}
     */
    public function start(Request $request)
    {
        $this->authorize('create', AiRequestDraft::class);

        $draft = AiRequestDraft::create([
            'user_id' => $request->user()->id,
            'input_message' => '',
            'chat_history' => [],
            'status' => AiRequestDraft::STATUS_PENDING,
        ]);

        return response()->json(new AiRequestDraftResource($draft), 201);
    }

    /**
     * Envoyer un message dans une conversation IA
     *
     * Ajoute le message du client à l'historique et dispatche un job qui génère
     * la réponse de l'IA (modèle rapide) puis extrait les données structurées
     * (modèle puissant) pour pré-remplir le formulaire de demande.
     *
     * @urlParam draft int required L'identifiant du brouillon. Example: 1
     * @bodyParam content string required Le message du client. Example: Je veux envoyer un colis à Sara
     * @bodyParam driver_slug string required Le slug du livreur. Example: rayan-express
     *
     * @response 200 {"id": 1, "status": "pending", "chat_history": [{"role": "user", "content": "Je veux envoyer un colis à Sara"}]}
     */
    public function sendMessage(SendAiChatMessageRequest $request, $id)
    {
        $draft = AiRequestDraft::findOrFail($id);

        $this->authorize('update', $draft);

        $driverProfile = DriverProfile::where('slug', $request->validated()['driver_slug'])->firstOrFail();

        // Ajouter le message utilisateur à l'historique, sans doublon : si le
        // dernier message est un 'user' identique envoyé il y a moins de 2 min
        // (renvoi après timeout / échec réseau), on ne l'ajoute pas une 2e fois.
        $history = $draft->chat_history ?? [];
        $lastMessage = $history ? end($history) : null;
        $content = $request->validated()['content'];
        $lastTime = $lastMessage['created_at'] ?? null;
        $isDuplicate = $lastMessage !== null
            && $lastMessage['role'] === 'user'
            && $lastMessage['content'] === $content
            && $lastTime !== null
            && \Illuminate\Support\Carbon::parse($lastTime)->diffInSeconds(now()) < 120;

        if (! $isDuplicate) {
            $history[] = [
                'role' => 'user',
                'content' => $content,
                'created_at' => now()->toIso8601String(),
            ];
        }

        $draft->update([
            'chat_history' => $history,
            'status' => AiRequestDraft::STATUS_PENDING,
            'error_message' => null,
        ]);

        // Toujours dispatcher : le job est idempotent (si la réponse existe déjà
        // pour le dernier message, il ne relance que l'extraction des données).
        ProcessAiChatMessageJob::dispatch($draft, $driverProfile->user_id)->afterCommit();

        return response()->json(new AiRequestDraftResource($draft->refresh()), 200);
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
