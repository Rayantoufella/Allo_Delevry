<?php

namespace App\Services;

use App\Exceptions\AiAnalysisException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiRequestAnalyzer
{
    /**
     * Analyze free text and return structured delivery request data.
     *
     * @param  string  $freeText  The client's free text message
     * @param  array  $activeServiceNames  List of active service names allowed for the driver
     * @return array{recipient_name: string|null, recipient_phone: string|null, pickup_address: string|null, delivery_address: string|null, package_description: string|null, product_amount: float|null, amount_to_collect: float|null, scheduled_at: string|null, service: string|null}
     *
     * @throws AiAnalysisException
     */
    public function analyze(string $freeText, array $activeServiceNames): array
    {
        $apiKey = config('services.openrouter.api_key');
        $model = config('services.openrouter.model');
        $baseUrl = config('services.openrouter.base_url');

        if (empty($apiKey)) {
            Log::channel('jobs')->error('Clé API OpenRouter non configurée (OPENROUTER_API_KEY).');

            throw new AiAnalysisException('Clé API OpenRouter non configurée (OPENROUTER_API_KEY).');
        }

        $systemPrompt = <<<'PROMPT'
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

        $userServiceMessage = $freeText;
        if (empty($activeServiceNames)) {
            $userServiceMessage .= "\n\nServices autorisés : aucun service autorisé.";
        } else {
            $userServiceMessage .= "\n\nServices autorisés : ".implode(', ', $activeServiceNames).'.';
        }

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userServiceMessage],
            ],
            'temperature' => 0.1,
            'max_completion_tokens' => 800,
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->withHeaders([
                    'HTTP-Referer' => config('app.url', 'http://localhost'),
                    'X-Title' => 'Allo Delivery',
                ])
                ->timeout(60)
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::channel('jobs')->error('Exception lors de l\'appel API OpenRouter : '.$e->getMessage());

            throw new AiAnalysisException('Erreur lors de l\'appel API OpenRouter : '.$e->getMessage(), $e);
        }

        if (! $response->successful()) {
            Log::channel('jobs')->error('Erreur API OpenRouter HTTP '.$response->status().': '.$response->body());

            throw new AiAnalysisException('Erreur API OpenRouter (HTTP '.$response->status().').');
        }

        $content = $response->json('choices.0.message.content');

        if (empty($content) || ! is_string($content)) {
            Log::channel('jobs')->error('Réponse IA vide ou non string : '.($content ?? 'null'));

            throw new AiAnalysisException('Réponse IA vide ou format inattendu.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            Log::channel('jobs')->error('JSON invalide retourné par l\'IA : '.$content);

            throw new AiAnalysisException('JSON invalide retourné par l\'IA.');
        }

        $allowedServicesLower = array_map('strtolower', $activeServiceNames);

        $service = $decoded['service'] ?? null;
        if (is_string($service)) {
            $serviceLower = strtolower($service);
            $serviceIndex = array_search($serviceLower, $allowedServicesLower);
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
