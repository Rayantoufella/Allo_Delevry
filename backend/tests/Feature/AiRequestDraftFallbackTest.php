<?php

use App\Jobs\AnalyzeAiRequestDraftJob;
use App\Models\AiRequestDraft;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function fallbackDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'fallback-driver-slug',
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

function fallbackAiResponse(array $data): string
{
    return json_encode(['choices' => [['message' => ['content' => json_encode($data)]]]]);
}

it('prevents duplicate drafts for the same client message within 2 minutes', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = fallbackDriver();

    Sanctum::actingAs($client);

    $message = 'Livrer à Sara, 06 12 34 56 78, colis chaussures.';

    // Premier POST → 201, nouveau draft
    $response1 = $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => $message,
        'driver_slug' => 'fallback-driver-slug',
    ])->assertCreated();

    $draftId1 = $response1->json('id');

    // Deuxième POST identique → 200, même draft
    $response2 = $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => $message,
        'driver_slug' => 'fallback-driver-slug',
    ])->assertOk();

    expect($response2->json('id'))->toBe($draftId1);

    // Un seul job poussé
    Queue::assertPushed(AnalyzeAiRequestDraftJob::class, 1);

    // Troisième POST avec message différent → 201, nouveau draft
    $response3 = $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => 'Envoyer un colis à Mohammed, 06 98 76 54 32.',
        'driver_slug' => 'fallback-driver-slug',
    ])->assertCreated();

    expect($response3->json('id'))->not->toBe($draftId1);

    // Deux jobs au total (premier + troisième)
    Queue::assertPushed(AnalyzeAiRequestDraftJob::class, 2);
});

it('falls back to second model when primary returns 404', function () {
    $client = User::factory()->client()->create();
    $driver = fallbackDriver();
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    config()->set('services.openrouter.fallback_models', 'nvidia/nemotron-3-ultra-550b-a55b:free');

    Http::fakeSequence('openrouter.ai/*')
        ->push('Not Found', 404)
        ->push(fallbackAiResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => '0612345678',
            'pickup_address' => 'Avenue Hassan II',
            'delivery_address' => 'Quartier Al Houda, Agadir',
            'package_description' => 'Chaussures',
            'product_amount' => null,
            'amount_to_collect' => null,
            'scheduled_at' => null,
            'service' => 'Envoi de colis',
        ]), 200);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Livrer des chaussures à Sara.',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new AnalyzeAiRequestDraftJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->generated_data['recipient_name'])->toBe('Sara');
    expect($draft->generated_data['service'])->toBe('Envoi de colis');

    // Vérifie que les 2 requêtes ont bien été faites avec les bons modèles
    Http::assertSentCount(2);
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'chat/completions')
            && ($request->data()['model'] ?? '') === 'nvidia/nemotron-3-super-120b-a12b:free';
    });
    Http::assertSent(function ($request) {
        return str_contains($request->url(), 'chat/completions')
            && ($request->data()['model'] ?? '') === 'nvidia/nemotron-3-ultra-550b-a55b:free';
    });
});

it('marks draft as failed when all models fail', function () {
    $client = User::factory()->client()->create();
    $driver = fallbackDriver();

    config()->set('services.openrouter.fallback_models', 'nvidia/nemotron-3-ultra-550b-a55b:free');

    Http::fakeSequence('openrouter.ai/*')
        ->push('Not Found', 404)
        ->push('Service Unavailable', 503);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Un message test.',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    $job = new AnalyzeAiRequestDraftJob($draft, $driver->id);

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

it('falls back when primary returns invalid JSON and succeeds on fallback', function () {
    $client = User::factory()->client()->create();
    $driver = fallbackDriver();
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    config()->set('services.openrouter.fallback_models', 'nvidia/nemotron-3-ultra-550b-a55b:free');

    Http::fakeSequence('openrouter.ai/*')
        ->push(json_encode([
            'choices' => [['message' => ['content' => 'pas du json {']]],
        ]), 200)
        ->push(fallbackAiResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => '0612345678',
            'pickup_address' => 'Avenue Hassan II',
            'delivery_address' => 'Quartier Al Houda, Agadir',
            'package_description' => 'Chaussures',
            'product_amount' => null,
            'amount_to_collect' => null,
            'scheduled_at' => null,
            'service' => 'Envoi de colis',
        ]), 200);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Livrer des chaussures.',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new AnalyzeAiRequestDraftJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->generated_data['recipient_name'])->toBe('Sara');

    Http::assertSentCount(2);
});

it('marks draft as failed when fallback is empty and primary fails', function () {
    config()->set('services.openrouter.fallback_models', '');

    $client = User::factory()->client()->create();
    $driver = fallbackDriver();

    Http::fake(['openrouter.ai/*' => Http::response('Not Found', 404)]);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Un message test.',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    $job = new AnalyzeAiRequestDraftJob($draft, $driver->id);

    try {
        $job->handle();
        $this->fail('AiAnalysisException attendue quand le modèle principal échoue sans fallback.');
    } catch (\App\Exceptions\AiAnalysisException $e) {
        $job->failed($e);
    }

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_FAILED);
});
