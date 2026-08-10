<?php

use App\Jobs\ProcessAiChatMessageJob;
use App\Models\AiRequestDraft;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function chatDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'chat-driver-slug',
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

function chatTextResponse(string $text): string
{
    return json_encode(['choices' => [['message' => ['content' => $text]]]]);
}

function chatJsonResponse(array $data): string
{
    return json_encode(['choices' => [['message' => ['content' => json_encode($data)]]]]);
}

it('creates a pending draft with empty chat_history via the start endpoint', function () {
    $client = User::factory()->client()->create();

    Sanctum::actingAs($client);
    $response = $this->postJson('/api/ai-request-drafts/start')
        ->assertCreated();

    expect($response->json('status'))->toBe('pending');
    expect($response->json('chat_history'))->toBe([]);
    expect($response->json('user_id'))->toBe($client->id);
    expect($response->json('input_message'))->toBe('');
});

it('sends a message and queues the ProcessAiChatMessageJob', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = chatDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    Sanctum::actingAs($client);
    $response = $this->postJson("/api/ai-request-drafts/{$draft->id}/messages", [
        'content' => 'Je veux envoyer un colis à Sara',
        'driver_slug' => 'chat-driver-slug',
    ])->assertOk();

    expect($response->json('chat_history'))->toHaveCount(1);
    expect($response->json('chat_history.0.role'))->toBe('user');
    expect($response->json('chat_history.0.content'))->toBe('Je veux envoyer un colis à Sara');

    Queue::assertPushed(ProcessAiChatMessageJob::class, function ($job) use ($draft, $driver) {
        return $job->draft->id === $draft->id
            && $job->driverUserId === $driver->id;
    });
});

it('rejects send message with empty content', function () {
    $client = User::factory()->client()->create();
    $driver = chatDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/ai-request-drafts/{$draft->id}/messages", [
        'content' => '',
        'driver_slug' => 'chat-driver-slug',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('content');
});

it('rejects send message on a draft belonging to another user', function () {
    $client = User::factory()->client()->create();
    $otherClient = User::factory()->client()->create();
    $driver = chatDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $otherClient->id,
        'chat_history' => [],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    Sanctum::actingAs($client);
    $this->postJson("/api/ai-request-drafts/{$draft->id}/messages", [
        'content' => 'Un message',
        'driver_slug' => 'chat-driver-slug',
    ])->assertStatus(403);
});

it('processes a full chat conversation successfully', function () {
    $client = User::factory()->client()->create();
    $driver = chatDriver();
    $service = Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    $chatModel = config('services.openrouter.chat_model');
    $extractModel = config('services.openrouter.extract_model');

    Http::fakeSequence('openrouter.ai/*')
        ->push(chatTextResponse('Bien sûr ! Quel est le nom du destinataire ?'), 200)
        ->push(chatJsonResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => '0612345678',
            'pickup_address' => 'Avenue Hassan II',
            'delivery_address' => 'Quartier Al Houda, Agadir',
            'package_description' => 'Chaussures, taille 39',
            'product_amount' => null,
            'amount_to_collect' => 420,
            'scheduled_at' => null,
            'service' => 'Envoi de colis',
        ]), 200);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [
            ['role' => 'user', 'content' => 'Je veux envoyer un colis à Sara', 'created_at' => now()->toIso8601String()],
        ],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new ProcessAiChatMessageJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->chat_history)->toHaveCount(2);
    expect($draft->chat_history[0]['role'])->toBe('user');
    expect($draft->chat_history[0]['content'])->toBe('Je veux envoyer un colis à Sara');
    expect($draft->chat_history[1]['role'])->toBe('assistant');
    expect($draft->chat_history[1]['content'])->toBe('Bien sûr ! Quel est le nom du destinataire ?');
    expect($draft->generated_data['recipient_name'])->toBe('Sara');
    expect($draft->generated_data['amount_to_collect'])->toEqual(420.0);
    expect($draft->service_id)->toBe($service->id);
    expect($draft->error_message)->toBeNull();

    // Vérifie les modèles utilisés
    Http::assertSent(function ($request) use ($chatModel) {
        return str_contains($request->url(), 'chat/completions')
            && ($request->data()['model'] ?? '') === $chatModel;
    });
    Http::assertSent(function ($request) use ($extractModel) {
        return str_contains($request->url(), 'chat/completions')
            && ($request->data()['model'] ?? '') === $extractModel;
    });
});

it('preserves existing generated_data during partial merge', function () {
    $client = User::factory()->client()->create();
    $driver = chatDriver();
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    $chatModel = config('services.openrouter.chat_model');
    $extractModel = config('services.openrouter.extract_model');

    Http::fakeSequence('openrouter.ai/*')
        ->push(chatTextResponse('Merci ! Autre chose ?'), 200)
        ->push(chatJsonResponse([
            'recipient_name' => null,
            'recipient_phone' => '0612345678',
            'pickup_address' => null,
            'delivery_address' => 'Quartier Al Houda',
            'package_description' => null,
            'product_amount' => null,
            'amount_to_collect' => null,
            'scheduled_at' => null,
            'service' => null,
        ]), 200);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [
            ['role' => 'user', 'content' => 'Je veux envoyer un colis à Sara', 'created_at' => now()->toIso8601String()],
        ],
        'generated_data' => [
            'recipient_name' => 'Sara',
            'pickup_address' => 'Avenue Hassan II',
        ],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new ProcessAiChatMessageJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    // Sara conservée (null n'écrase pas)
    expect($draft->generated_data['recipient_name'])->toBe('Sara');
    // Avenue Hassan II conservée
    expect($draft->generated_data['pickup_address'])->toBe('Avenue Hassan II');
    // Nouvelle valeur ajoutée
    expect($draft->generated_data['recipient_phone'])->toBe('0612345678');
    expect($draft->generated_data['delivery_address'])->toBe('Quartier Al Houda');
});

it('marks draft as failed when all models fail during chat', function () {
    $client = User::factory()->client()->create();
    $driver = chatDriver();

    config()->set('services.openrouter.fallback_models', 'nvidia/nemotron-3-ultra-550b-a55b:free');

    Http::fakeSequence('openrouter.ai/*')
        ->push('Not Found', 404)
        ->push('Service Unavailable', 503);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    $job = new ProcessAiChatMessageJob($draft, $driver->id);

    try {
        $job->handle();
        $this->fail('AiAnalysisException attendue quand tous les modèles échouent.');
    } catch (\App\Exceptions\AiAnalysisException $e) {
        $job->failed($e);
    }

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_FAILED);
    expect($draft->error_message)->toContain('OpenRouter');
});

it('returns null service_id for unknown service (RG11)', function () {
    $client = User::factory()->client()->create();
    $driver = chatDriver();
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    $chatModel = config('services.openrouter.chat_model');
    $extractModel = config('services.openrouter.extract_model');

    Http::fakeSequence('openrouter.ai/*')
        ->push(chatTextResponse('D\'accord.'), 200)
        ->push(chatJsonResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => null,
            'pickup_address' => null,
            'delivery_address' => null,
            'package_description' => null,
            'product_amount' => null,
            'amount_to_collect' => null,
            'scheduled_at' => null,
            'service' => 'Inconnu',
        ]), 200);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [
            ['role' => 'user', 'content' => 'Je veux envoyer un colis', 'created_at' => now()->toIso8601String()],
        ],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new ProcessAiChatMessageJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->service_id)->toBeNull();
    expect($draft->generated_data['service'])->toBeNull();
});

it('does nothing when draft is not pending', function () {
    Http::fake(['openrouter.ai/*' => Http::response('should not be called')]);

    $client = User::factory()->client()->create();
    $driver = chatDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'chat_history' => [],
        'status' => AiRequestDraft::STATUS_DONE,
    ]);

    (new ProcessAiChatMessageJob($draft, $driver->id))->handle();

    expect($draft->refresh()->status)->toBe(AiRequestDraft::STATUS_DONE);

    Http::assertNothingSent();
});
