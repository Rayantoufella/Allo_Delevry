<?php

use App\Exceptions\AiAnalysisException;
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

function aiDraftDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'ai-driver-slug',
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

function aiDraftGrokResponse(array $data): string
{
    return json_encode(['choices' => [['message' => ['content' => json_encode($data)]]]]);
}

it('analyzes a free text and marks the draft as done with structured data', function () {
    Http::fake([
        'api.x.ai/*' => Http::response(aiDraftGrokResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => '0612345678',
            'pickup_address' => 'Avenue Hassan II',
            'delivery_address' => 'Quartier Al Houda, Agadir',
            'package_description' => 'Chaussures, taille 39',
            'product_amount' => 500,
            'amount_to_collect' => 420,
            'scheduled_at' => '2026-08-10T14:00:00',
            'service' => 'Envoi de colis',
        ])),
    ]);

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();
    $service = Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Livrer demain avant 15h à Sara, 06 12 34 56 78, quartier Al Houda Agadir. Colis : chaussures taille 39. Montant à encaisser : 420 DH. Retrait Avenue Hassan II.',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new AnalyzeAiRequestDraftJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->service_id)->toBe($service->id);
    expect($draft->generated_data['recipient_name'])->toBe('Sara');
    expect($draft->generated_data['recipient_phone'])->toBe('0612345678');
    expect($draft->generated_data['delivery_address'])->toBe('Quartier Al Houda, Agadir');
    expect($draft->generated_data['amount_to_collect'])->toEqual(420.0);
    expect($draft->generated_data['service'])->toBe('Envoi de colis');
});

it('does not select a service that is not in the driver active catalog (RG11)', function () {
    Http::fake([
        'api.x.ai/*' => Http::response(aiDraftGrokResponse([
            'recipient_name' => 'Sara',
            'recipient_phone' => '0612345678',
            'pickup_address' => 'Avenue Hassan II',
            'delivery_address' => 'Quartier Al Houda, Agadir',
            'service' => 'Service pirate inexistant',
        ])),
    ]);

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi de colis',
        'is_active' => true,
    ]);
    Service::factory()->create([
        'user_id' => $driver->id,
        'name' => 'Envoi urgent',
        'is_active' => false,
    ]);

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Un message libre',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    (new AnalyzeAiRequestDraftJob($draft, $driver->id))->handle();

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->service_id)->toBeNull();
    expect($draft->generated_data['service'])->toBeNull();
});

it('marks the draft as failed with a controlled message when the AI returns invalid JSON', function () {
    Http::fake([
        'api.x.ai/*' => Http::response(json_encode([
            'choices' => [['message' => ['content' => 'pas du json {']]],
        ])),
    ]);

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Un message libre',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    $job = new AnalyzeAiRequestDraftJob($draft, $driver->id);

    try {
        $job->handle();
        $this->fail('AiAnalysisException attendue pour un JSON invalide.');
    } catch (AiAnalysisException $e) {
        $job->failed($e);
    }

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_FAILED);
    expect($draft->error_message)->toContain('JSON invalide');
});

it('marks the draft as failed when the API key is missing', function () {
    config()->set('services.xai.api_key', null);

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Un message libre',
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    $job = new AnalyzeAiRequestDraftJob($draft, $driver->id);

    try {
        $job->handle();
        $this->fail('AiAnalysisException attendue sans clé API.');
    } catch (AiAnalysisException $e) {
        $job->failed($e);
    }

    $draft->refresh();

    expect($draft->status)->toBe(AiRequestDraft::STATUS_FAILED);
    expect($draft->error_message)->toContain('XAI_API_KEY');
});

it('does nothing when the draft is already processed', function () {
    Http::fake(['api.x.ai/*' => Http::response('{"choices":[]}')]);

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();

    $draft = AiRequestDraft::factory()->create([
        'user_id' => $client->id,
        'input_message' => 'Déjà traité',
        'status' => AiRequestDraft::STATUS_DONE,
        'generated_data' => ['recipient_name' => 'Déjà là'],
    ]);

    (new AnalyzeAiRequestDraftJob($draft, $driver->id))->handle();

    expect($draft->refresh()->status)->toBe(AiRequestDraft::STATUS_DONE);
    expect($draft->generated_data['recipient_name'])->toBe('Déjà là');
});

it('creates a pending draft and dispatches the analysis job via the endpoint', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();

    Sanctum::actingAs($client);
    $response = $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => 'Livrer à Sara, 06 12 34 56 78, colis chaussures.',
        'driver_slug' => 'ai-driver-slug',
    ])->assertCreated();

    expect($response->json('status'))->toBe(AiRequestDraft::STATUS_PENDING);
    expect($response->json('user_id'))->toBe($client->id);

    Queue::assertPushed(AnalyzeAiRequestDraftJob::class, function ($job) use ($driver) {
        return $job->driverUserId === $driver->id;
    });
});

it('rejects the analyze endpoint without a driver slug', function () {
    $client = User::factory()->client()->create();

    Sanctum::actingAs($client);
    $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => 'Livrer à Sara.',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('driver_slug');
});

it('rejects the analyze endpoint with an unknown driver slug', function () {
    $client = User::factory()->client()->create();

    Sanctum::actingAs($client);
    $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => 'Livrer à Sara.',
        'driver_slug' => 'slug-inexistant',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('driver_slug');
});

it('rate limits the analyze endpoint', function () {
    Queue::fake();

    $client = User::factory()->client()->create();
    $driver = aiDraftDriver();

    Sanctum::actingAs($client);

    foreach (range(1, 10) as $i) {
        $this->postJson('/api/ai-request-drafts/analyze', [
            'input_message' => 'Message '.$i,
            'driver_slug' => 'ai-driver-slug',
        ])->assertStatus(201);
    }

    $this->postJson('/api/ai-request-drafts/analyze', [
        'input_message' => 'Message 11',
        'driver_slug' => 'ai-driver-slug',
    ])->assertStatus(429);
});