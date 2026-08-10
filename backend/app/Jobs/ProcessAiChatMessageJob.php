<?php

namespace App\Jobs;

use App\Exceptions\AiAnalysisException;
use App\Models\AiRequestDraft;
use App\Models\Service;
use App\Services\AiRequestAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Log;

class ProcessAiChatMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Deux appels OpenRouter (réponse + extraction), fallbacks inclus : laisser
    // le temps à une API lente de répondre sans tuer le job au milieu.
    public int $timeout = 180;

    public function __construct(
        public AiRequestDraft $draft,
        public int $driverUserId,
    ) {}

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        if ($this->draft->status !== AiRequestDraft::STATUS_PENDING) {
            return;
        }

        $history = $this->draft->chat_history ?? [];
        $services = $this->activeServices();
        $activeServiceNames = $services->pluck('name')->all();

        // Idempotence du retry : si une réponse assistant existe déjà pour le
        // dernier message utilisateur (échec de l'extraction lors d'une
        // tentative précédente), on ne re-génère pas la réponse — on ne
        // relance que l'extraction pour éviter les doublons dans le chat.
        $lastMessage = $history ? end($history) : null;

        if ($lastMessage === null || $lastMessage['role'] !== 'assistant') {
            // 1. Générer la réponse conversationnelle (modèle rapide)
            $reply = (new AiRequestAnalyzer)->chatReply($history, $activeServiceNames);

            // 2. Ajouter la réponse de l'assistant à l'historique et la publier
            // immédiatement : le client voit la question posée pendant que
            // l'extraction (2e appel, plus lent) tourne encore en arrière-plan.
            $history[] = [
                'role' => 'assistant',
                'content' => $reply,
                'created_at' => now()->toIso8601String(),
            ];

            $this->draft->update([
                'chat_history' => $history,
                'status' => AiRequestDraft::STATUS_PENDING,
                'error_message' => null,
            ]);
        }

        // 3. Extraire les données structurées (modèle puissant)
        $extracted = (new AiRequestAnalyzer)->extractFromConversation($history, $activeServiceNames);

        // 4. Fusion partielle : les champs non-null écrasent, les null ne touchent pas l'existant
        $old = $this->draft->generated_data;
        $generatedData = array_merge($old ?? [], array_filter($extracted, fn ($v): bool => $v !== null));

        // 5. Résoudre le service_id
        $serviceId = $this->matchServiceId($extracted['service'] ?? null, $services);

        if ($serviceId === null) {
            $generatedData['service'] = $extracted['service'] ?? $generatedData['service'] ?? null;
        }

        // 6. Sauvegarder
        $this->draft->update([
            'chat_history' => $history,
            'generated_data' => $generatedData,
            'service_id' => $serviceId,
            'status' => AiRequestDraft::STATUS_DONE,
            'error_message' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('jobs')->error('Job chat IA échoué', [
            'job' => static::class,
            'resource_id' => $this->draft?->id,
            'error' => $e->getMessage(),
        ]);

        if ($e instanceof AiAnalysisException || $e instanceof TimeoutExceededException) {
            if ($this->draft->exists) {
                $this->draft->update([
                    'status' => AiRequestDraft::STATUS_FAILED,
                    'error_message' => $e instanceof TimeoutExceededException
                        ? "L'IA a mis trop de temps à répondre. Réessaie ou remplis le formulaire manuellement."
                        : $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Services actifs du catalogue du livreur.
     *
     * @return Collection<int, Service>
     */
    private function activeServices(): Collection
    {
        return Service::where('user_id', $this->driverUserId)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Retrouve l'identifiant du service dans le catalogue actif du livreur (exact puis insensible à la casse).
     *
     * @param  Collection<int, Service>  $services
     */
    private function matchServiceId(?string $serviceName, Collection $services): ?int
    {
        if ($serviceName === null) {
            return null;
        }

        $service = $services->firstWhere('name', $serviceName)
            ?? $services->first(fn (Service $candidate): bool => mb_strtolower($candidate->name) === mb_strtolower($serviceName));

        return $service?->id;
    }
}
