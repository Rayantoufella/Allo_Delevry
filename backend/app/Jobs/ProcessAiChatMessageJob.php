<?php

namespace App\Jobs;

use App\Exceptions\AiAnalysisException;
use App\Models\AiRequestDraft;
use App\Models\DeliveryZone;
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

    // Deux appels Google AI (réponse + extraction), fallbacks inclus : laisser
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
        $activeZones = $this->activeZones();

        // Idempotence du retry : si une réponse assistant existe déjà pour le
        // dernier message utilisateur (échec de l'extraction lors d'une
        // tentative précédente), on ne re-génère pas la réponse — on ne
        // relance que l'extraction pour éviter les doublons dans le chat.
        $lastMessage = $history ? end($history) : null;

        if ($lastMessage === null || $lastMessage['role'] !== 'assistant') {
            // 1. Générer la réponse conversationnelle (modèle rapide)
            $reply = (new AiRequestAnalyzer)->chatReply($history, $activeServiceNames, $activeZones);

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
        $extracted = (new AiRequestAnalyzer)->extractFromConversation($history, $activeServiceNames, $activeZones);

        // 4. Fusion partielle : les champs non-null écrasent, les null ne touchent pas l'existant
        $old = $this->draft->generated_data;
        $generatedData = array_merge($old ?? [], array_filter($extracted, fn ($v): bool => $v !== null));

        // 4b. Zone de livraison : priorité à l'extraction IA ; sinon déduction
        // déterministe depuis la NOUVELLE adresse de livraison. L'IA ne doit
        // jamais laisser la zone vide quand le quartier est connu — le client
        // n'a pas à choisir la zone manuellement avant de valider.
        $extractedZone = $extracted['delivery_zone'] ?? null;

        if ($extractedZone !== null) {
            $generatedData['delivery_zone'] = $extractedZone;
        } else {
            $deliveryAddress = $extracted['delivery_address'] ?? $generatedData['delivery_address'] ?? null;
            $deducedZone = $this->deduceDeliveryZone($deliveryAddress, $activeZones);

            if ($deducedZone !== null) {
                $generatedData['delivery_zone'] = $deducedZone;
            }
            // Aucune déduction : l'ancienne zone éventuelle est conservée telle quelle.
        }

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
     * Zones de livraison actives du livreur, formatées pour les prompts IA.
     *
     * @return list<array{name: string, price: float|null}>
     */
    private function activeZones(): array
    {
        $zones = DeliveryZone::where('user_id', $this->driverUserId)
            ->where('is_active', true)
            ->get();

        $result = [];

        foreach ($zones as $zone) {
            $name = $zone->destination_zone !== null && $zone->destination_zone !== ''
                ? $zone->destination_zone
                : $zone->origin_zone;

            if ($name !== null && $name !== '') {
                $result[] = [
                    'name' => $name,
                    'price' => $zone->fixed_price !== null ? (float) $zone->fixed_price : null,
                ];
            }
        }

        return $result;
    }

    /**
     * Déduit la zone de livraison depuis l'adresse. Stratégie de correspondance
     * (insensible à la casse, trim) :
     * 1. nom complet de la zone présent dans l'adresse, ou adresse contenue dans
     *    le nom (ex. adresse « houda » → zone « houda-salam-dakhla ») ;
     * 2. un mot significatif du nom de zone présent dans l'adresse
     *    (ex. « vers houda quartier » → token « houda » de « houda-salam-dakhla »).
     * En cas de multiples candidats, la zone au nom le plus long gagne.
     * Retourne le nom EXACT de la zone, ou null si aucune correspondance.
     *
     * @param  list<array{name: string, price: float|null}>  $activeZones
     */
    private function deduceDeliveryZone(?string $deliveryAddress, array $activeZones): ?string
    {
        $address = mb_strtolower(trim((string) $deliveryAddress));

        if ($address === '') {
            return null;
        }

        $addressTokens = $this->significantTokens($address);

        if ($addressTokens === []) {
            return null;
        }

        $bestZone = null;
        $bestNameLength = 0;

        foreach ($activeZones as $zone) {
            $rawName = trim($zone['name']);

            if ($rawName === '') {
                continue;
            }

            $nameLower = mb_strtolower($rawName);

            // 1. Correspondance directe sur la chaîne complète.
            $matches = str_contains($address, $nameLower) || str_contains($nameLower, $address);

            // 2. Sinon, correspondance par mot significatif partagé.
            if (! $matches) {
                foreach ($this->significantTokens($nameLower) as $token) {
                    if (in_array($token, $addressTokens, true)) {
                        $matches = true;
                        break;
                    }
                }
            }

            if ($matches && mb_strlen($nameLower) > $bestNameLength) {
                $bestZone = $rawName;
                $bestNameLength = mb_strlen($nameLower);
            }
        }

        return $bestZone;
    }

    /**
     * Mots significatifs d'un texte (minuscules, au moins 3 caractères).
     *
     * @return list<string>
     */
    private function significantTokens(string $text): array
    {
        $tokens = preg_split('/\W+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter($tokens, fn (string $token): bool => mb_strlen($token) >= 3));
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
