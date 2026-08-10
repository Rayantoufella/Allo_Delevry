<?php

namespace App\Services;

use App\Exceptions\AiAnalysisException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiRequestAnalyzer
{
    /**
     * Analyse un texte libre et retourne les données structurées de la demande de livraison.
     *
     * @param  string  $freeText  Message libre du client
     * @param  array<int, string>  $activeServiceNames  Noms des services actifs autorisés pour le livreur
     * @return array{recipient_name: string|null, recipient_phone: string|null, pickup_address: string|null, delivery_address: string|null, package_description: string|null, product_amount: float|null, amount_to_collect: float|null, scheduled_at: string|null, service: string|null}
     *
     * @throws AiAnalysisException
     */
    public function analyze(string $freeText, array $activeServiceNames): array
    {
        $this->ensureApiKeyIsConfigured();

        $rawContent = $this->requestRawContent($freeText, $activeServiceNames);

        $decoded = $this->decodeRawContent($rawContent);

        return $this->normalizeResult($decoded, $activeServiceNames);
    }

    /**
     * Génère une réponse conversationnelle (texte libre) en tant que livreur IA.
     *
     * @param  array<int, array{role: string, content: string, created_at?: string}>  $history  Historique de la conversation
     * @param  array<int, string>  $activeServiceNames  Noms des services actifs du livreur
     * @return string  Texte de la réponse de l'assistant
     *
     * @throws AiAnalysisException
     */
    public function chatReply(array $history, array $activeServiceNames): string
    {
        $this->ensureApiKeyIsConfigured();

        $models = $this->getChatModels();
        $lastException = null;

        $messages = [
            ['role' => 'system', 'content' => $this->chatSystemPrompt()],
            ...$this->stripCreatedAt($history),
        ];

        $payload = [
            'model' => $models[0],
            'messages' => $messages,
            'temperature' => 0.7,
            'max_completion_tokens' => 500,
        ];

        foreach ($models as $index => $model) {
            $payload['model'] = $model;

            try {
                $response = $this->sendRequest($payload);
            } catch (\Throwable $e) {
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Chat modèle {$model} échoué (exception: {$e->getMessage()})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur lors de l\'appel API OpenRouter : '.$e->getMessage(), $e);
                continue;
            }

            if (! $response->successful()) {
                $reason = 'HTTP '.$response->status();
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Chat modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur API OpenRouter (HTTP '.$response->status().').');
                continue;
            }

            $content = $response->json('choices.0.message.content');

            if (empty($content) || ! is_string($content)) {
                $reason = 'réponse vide ou non string';
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Chat modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Réponse IA vide ou format inattendu.');
                continue;
            }

            return $content;
        }

        Log::channel('jobs')->error('Tous les modèles chat IA ont échoué.', [
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new AiAnalysisException('Aucun modèle IA disponible.');
    }

    /**
     * Extrait les données structurées de la demande à partir de l'historique conversationnel complet.
     *
     * @param  array<int, array{role: string, content: string, created_at?: string}>  $history  Historique complet de la conversation
     * @param  array<int, string>  $activeServiceNames  Noms des services actifs autorisés pour le livreur
     * @return array{recipient_name: string|null, recipient_phone: string|null, pickup_address: string|null, delivery_address: string|null, package_description: string|null, product_amount: float|null, amount_to_collect: float|null, scheduled_at: string|null, service: string|null}
     *
     * @throws AiAnalysisException
     */
    public function extractFromConversation(array $history, array $activeServiceNames): array
    {
        $this->ensureApiKeyIsConfigured();

        $models = $this->getExtractModels();
        $lastException = null;

        $conversationText = $this->buildConversationText($history, $activeServiceNames);

        $payload = [
            'model' => $models[0],
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $conversationText],
            ],
            'temperature' => 0.1,
            'max_completion_tokens' => 800,
            'response_format' => ['type' => 'json_object'],
        ];

        foreach ($models as $index => $model) {
            $payload['model'] = $model;

            try {
                $response = $this->sendRequest($payload);
            } catch (\Throwable $e) {
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Extract modèle {$model} échoué (exception: {$e->getMessage()})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur lors de l\'appel API OpenRouter : '.$e->getMessage(), $e);
                continue;
            }

            if (! $response->successful()) {
                $reason = 'HTTP '.$response->status();
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Extract modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur API OpenRouter (HTTP '.$response->status().').');
                continue;
            }

            $content = $response->json('choices.0.message.content');

            if (empty($content) || ! is_string($content)) {
                $reason = 'réponse vide ou non string';
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Extract modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Réponse IA vide ou format inattendu.');
                continue;
            }

            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                $reason = 'JSON invalide';
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Extract modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('JSON invalide retourné par l\'IA.');
                continue;
            }

            return $this->normalizeResult($decoded, $activeServiceNames);
        }

        Log::channel('jobs')->error('Tous les modèles extract IA ont échoué.', [
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new AiAnalysisException('Aucun modèle IA disponible.');
    }

    /**
     * Vérifie que la clé API OpenRouter est configurée.
     *
     * @throws AiAnalysisException
     */
    private function ensureApiKeyIsConfigured(): void
    {
        if (empty(config('services.openrouter.api_key'))) {
            Log::channel('jobs')->error('Clé API OpenRouter non configurée (OPENROUTER_API_KEY).');

            throw new AiAnalysisException('Clé API OpenRouter non configurée (OPENROUTER_API_KEY).');
        }
    }

    /**
     * Envoie une requête HTTP vers OpenRouter.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendRequest(array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withToken(config('services.openrouter.api_key'))
            ->acceptJson()
            ->withHeaders([
                'HTTP-Referer' => config('app.url', 'http://localhost'),
                'X-Title' => 'Allo Delivery',
            ])
            ->timeout(60)
            ->post(config('services.openrouter.base_url').'/chat/completions', $payload);
    }

    /**
     * Retourne la liste ordonnée des modèles à tester pour l'extraction (principal + fallbacks).
     *
     * @return list<string>
     */
    private function getModels(): array
    {
        $fallbackModels = config('services.openrouter.fallback_models', '');

        $fallbacks = array_filter(array_map('trim', explode(',', (string) $fallbackModels)));

        return array_merge(
            [config('services.openrouter.model')],
            $fallbacks
        );
    }

    /**
     * Retourne la liste ordonnée des modèles pour le chat (chat_model + fallbacks).
     *
     * @return list<string>
     */
    private function getChatModels(): array
    {
        $fallbackModels = config('services.openrouter.fallback_models', '');

        $fallbacks = array_filter(array_map('trim', explode(',', (string) $fallbackModels)));

        return array_merge(
            [config('services.openrouter.chat_model')],
            $fallbacks
        );
    }

    /**
     * Retourne la liste ordonnée des modèles pour l'extraction conversationnelle (extract_model + fallbacks).
     *
     * @return list<string>
     */
    private function getExtractModels(): array
    {
        $fallbackModels = config('services.openrouter.fallback_models', '');

        $fallbacks = array_filter(array_map('trim', explode(',', (string) $fallbackModels)));

        return array_merge(
            [config('services.openrouter.extract_model')],
            $fallbacks
        );
    }

    /**
     * Itère sur les modèles (principal + fallbacks) et retourne le premier contenu brut validé en JSON.
     *
     * @param  array<int, string>  $activeServiceNames
     *
     * @throws AiAnalysisException
     */
    private function requestRawContent(string $freeText, array $activeServiceNames): string
    {
        $models = $this->getModels();
        $lastException = null;

        foreach ($models as $index => $model) {
            $payload = $this->buildPayload($freeText, $activeServiceNames, $model);

            try {
                $response = $this->sendRequest($payload);
            } catch (\Throwable $e) {
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Modèle {$model} échoué (exception: {$e->getMessage()})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur lors de l\'appel API OpenRouter : '.$e->getMessage(), $e);
                continue;
            }

            if (! $response->successful()) {
                $reason = 'HTTP '.$response->status();
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Erreur API OpenRouter (HTTP '.$response->status().').');
                continue;
            }

            $content = $response->json('choices.0.message.content');

            if (empty($content) || ! is_string($content)) {
                $reason = 'réponse vide ou non string';
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('Réponse IA vide ou format inattendu.');
                continue;
            }

            // Valide que le contenu est du JSON exploitable
            $decoded = json_decode($content, true);
            if (! is_array($decoded)) {
                $reason = 'JSON invalide';
                $nextModel = $models[$index + 1] ?? null;
                Log::channel('jobs')->warning(
                    "Modèle {$model} échoué ({$reason})"
                    .($nextModel ? ", bascule sur {$nextModel}" : '')
                );
                $lastException = new AiAnalysisException('JSON invalide retourné par l\'IA.');
                continue;
            }

            return $content;
        }

        Log::channel('jobs')->error('Tous les modèles IA ont échoué.', [
            'last_error' => $lastException?->getMessage(),
        ]);

        throw $lastException ?? new AiAnalysisException('Aucun modèle IA disponible.');
    }

    /**
     * Construit la requête Chat Completions (messages + paramètres de génération) pour l'extraction directe.
     *
     * @param  array<int, string>  $activeServiceNames
     * @return array<string, mixed>
     */
    private function buildPayload(string $freeText, array $activeServiceNames, ?string $model = null): array
    {
        return [
            'model' => $model ?? config('services.openrouter.model'),
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->buildUserMessage($freeText, $activeServiceNames)],
            ],
            'temperature' => 0.1,
            'max_completion_tokens' => 800,
            'response_format' => ['type' => 'json_object'],
        ];
    }

    /**
     * Prompt système : règles d'extraction des champs de la demande.
     */
    private function systemPrompt(): string
    {
        return <<<'PROMPT'
Tu es l'assistant de création de demande de livraison Allo Delivery. À partir du message libre du client, tu remplis le formulaire de demande : destinataire, téléphone, adresses de retrait et de livraison, description du colis, montant à encaisser, date souhaitée, et le service demandé. Tu ne calcules jamais de prix. Tu ne choisis le service QUE parmi la liste fournie.

Réponds UNIQUEMENT avec un objet JSON valide, sans texte ni balise, au format suivant :
{"recipient_name": "prénom ou nom complet", "recipient_phone": "numéro de téléphone", "pickup_address": "adresse de retrait", "delivery_address": "adresse de livraison", "package_description": "description du colis", "product_amount": null, "amount_to_collect": null, "scheduled_at": null, "service": null}

Règles :
- product_amount = montant de la valeur du produit en dirhams (number), ou null si non mentionné.
- amount_to_collect = montant à encaisser auprès du destinataire en dirhams (number), ou null si non mentionné.
- scheduled_at = date/heure souhaitée au format ISO 8601 (ex: "2026-08-10T14:00:00"), ou null si immédiat.
- service = exactement un des noms de services dans la liste fournie, ou null si non mentionné ou non reconnu.
- Si une information n'est pas disponible dans le texte, mets null.
PROMPT;
    }

    /**
     * Prompt système pour le chat conversationnel (persona livreur).
     */
    private function chatSystemPrompt(): string
    {
        return <<<'PROMPT'
Tu es un livreur d'Allo Delivery qui discute avec le client pour compléter sa demande de livraison. Tu poses une question à la fois sur les informations manquantes (nom du destinataire, téléphone, adresse de retrait, adresse de livraison, description du colis, montant à encaisser, service souhaité). Tu ne calcules JAMAIS de prix (tarif fixé par zone par le livreur). Tu ne confirmes jamais la création de la demande. Tu réponds en français, court et naturel.
PROMPT;
    }

    /**
     * Message utilisateur : texte libre + liste des services autorisés.
     *
     * @param  array<int, string>  $activeServiceNames
     */
    private function buildUserMessage(string $freeText, array $activeServiceNames): string
    {
        if (empty($activeServiceNames)) {
            return $freeText."\n\nServices autorisés : aucun service autorisé.";
        }

        return $freeText."\n\nServices autorisés : ".implode(', ', $activeServiceNames).'.';
    }

    /**
     * Construit le texte de la conversation pour l'extraction (extrait les infos utiles de l'historique).
     *
     * @param  array<int, array{role: string, content: string, created_at?: string}>  $history
     * @param  array<int, string>  $activeServiceNames
     */
    private function buildConversationText(array $history, array $activeServiceNames): string
    {
        $lines = [];

        foreach ($history as $message) {
            $role = $message['role'] === 'user' ? 'Client' : 'Livreur';
            $lines[] = "{$role} : {$message['content']}";
        }

        $conversationText = implode("\n", $lines);

        if (! empty($activeServiceNames)) {
            $conversationText .= "\n\nServices autorisés : ".implode(', ', $activeServiceNames).'.';
        }

        return $conversationText;
    }

    /**
     * Supprime les created_at des messages d'historique avant envoi à l'API.
     *
     * @param  array<int, array{role: string, content: string, created_at?: string}>  $history
     * @return array<int, array{role: string, content: string}>
     */
    private function stripCreatedAt(array $history): array
    {
        return array_map(fn (array $message): array => [
            'role' => $message['role'],
            'content' => $message['content'],
        ], $history);
    }

    /**
     * Décode le contenu JSON renvoyé par l'IA (défensif, le JSON est déjà validé dans la boucle).
     *
     * @return array<string, mixed>
     *
     * @throws AiAnalysisException
     */
    private function decodeRawContent(string $rawContent): array
    {
        $decoded = json_decode($rawContent, true);

        if (! is_array($decoded)) {
            Log::channel('jobs')->error('JSON invalide retourné par l\'IA : '.$rawContent);

            throw new AiAnalysisException('JSON invalide retourné par l\'IA.');
        }

        return $decoded;
    }

    /**
     * Normalise la réponse : ne conserve le service que s'il appartient au catalogue autorisé.
     *
     * @param  array<string, mixed>  $decoded
     * @param  array<int, string>  $activeServiceNames
     * @return array{recipient_name: string|null, recipient_phone: string|null, pickup_address: string|null, delivery_address: string|null, package_description: string|null, product_amount: float|null, amount_to_collect: float|null, scheduled_at: string|null, service: string|null}
     */
    private function normalizeResult(array $decoded, array $activeServiceNames): array
    {
        $allowedServicesLower = array_map('strtolower', $activeServiceNames);

        $service = $decoded['service'] ?? null;

        if (is_string($service)) {
            $serviceIndex = array_search(strtolower($service), $allowedServicesLower, true);
            $service = $serviceIndex !== false ? $activeServiceNames[$serviceIndex] : null;
        } else {
            $service = null;
        }

        return [
            'recipient_name' => $decoded['recipient_name'] ?? null,
            'recipient_phone' => $decoded['recipient_phone'] ?? null,
            'pickup_address' => $decoded['pickup_address'] ?? null,
            'delivery_address' => $decoded['delivery_address'] ?? null,
            'package_description' => $decoded['package_description'] ?? null,
            'product_amount' => isset($decoded['product_amount']) && is_numeric($decoded['product_amount']) ? (float) $decoded['product_amount'] : null,
            'amount_to_collect' => isset($decoded['amount_to_collect']) && is_numeric($decoded['amount_to_collect']) ? (float) $decoded['amount_to_collect'] : null,
            'scheduled_at' => $decoded['scheduled_at'] ?? null,
            'service' => $service,
        ];
    }
}
