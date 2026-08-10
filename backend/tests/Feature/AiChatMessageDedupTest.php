<?php

use App\Jobs\ProcessAiChatMessageJob;
use App\Models\AiRequestDraft;
use App\Models\DriverProfile;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function dedupDriver(): User
{
    $driver = User::factory()->driver()->create();
    DriverProfile::factory()->create([
        'user_id' => $driver->id,
        'slug' => 'dedup-driver-slug',
        'rib' => 'FR76 3000 6000 0112 3456 7890 189',
    ]);

    return $driver;
}

it('does not duplicate a user message re-sent within the timeout window', function () {
    $client = User::factory()->client()->create();
    $driver = dedupDriver();
    Service::factory()->create(['user_id' => $driver->id, 'is_active' => true, 'name' => 'Livraison']);

    Sanctum::actingAs($client);
    Queue::fake();

    $draft = $this->postJson('/api/ai-request-drafts/start')->json();

    // 1er envoi du message
    $this->postJson("/api/ai-request-drafts/{$draft['id']}/messages", [
        'content' => 'Envoie un colis à Sara',
        'driver_slug' => 'dedup-driver-slug',
    ])->assertOk();

    expect(AiRequestDraft::find($draft['id'])->chat_history)->toHaveCount(1);

    // Renvoi identique immédiat (simulation du renvoi après timeout) : pas de doublon
    $this->postJson("/api/ai-request-drafts/{$draft['id']}/messages", [
        'content' => 'Envoie un colis à Sara',
        'driver_slug' => 'dedup-driver-slug',
    ])->assertOk();

    $history = AiRequestDraft::find($draft['id'])->chat_history;
    expect($history)->toHaveCount(1);
    expect($history[0]['content'])->toBe('Envoie un colis à Sara');

    // Un message différent est bien ajouté
    $this->postJson("/api/ai-request-drafts/{$draft['id']}/messages", [
        'content' => 'Envoie un colis à Karim',
        'driver_slug' => 'dedup-driver-slug',
    ])->assertOk();

    expect(AiRequestDraft::find($draft['id'])->chat_history)->toHaveCount(2);
});

it('does not add a duplicate when the draft already has the assistant reply (post-timeout resend)', function () {
    $client = User::factory()->client()->create();
    $driver = dedupDriver();
    Service::factory()->create(['user_id' => $driver->id, 'is_active' => true, 'name' => 'Livraison']);

    Sanctum::actingAs($client);
    Queue::fake();

    $draftId = $this->postJson('/api/ai-request-drafts/start')->json('id');

    // État du scénario réel : le job a écrit la réponse puis a timeout,
    // le draft reste pending avec [user, assistant] dans l'historique.
    AiRequestDraft::find($draftId)->update([
        'chat_history' => [
            ['role' => 'user', 'content' => 'Envoie un colis à Sara', 'created_at' => now()->toIso8601String()],
            ['role' => 'assistant', 'content' => 'Quelle est l\'adresse ?', 'created_at' => now()->toIso8601String()],
        ],
        'status' => AiRequestDraft::STATUS_PENDING,
    ]);

    // L'utilisateur renvoie le même message : il ne doit pas être ré-ajouté,
    // mais le traitement est relancé (le job est idempotent).
    $this->postJson("/api/ai-request-drafts/{$draftId}/messages", [
        'content' => 'Envoie un colis à Sara',
        'driver_slug' => 'dedup-driver-slug',
    ])->assertOk();

    $history = AiRequestDraft::find($draftId)->chat_history;
    expect($history)->toHaveCount(2);
    expect($history[1]['role'])->toBe('assistant');

    Queue::assertPushed(ProcessAiChatMessageJob::class);
});

it('does not relaunch the job when the duplicate is sent on a completed draft', function () {
    $client = User::factory()->client()->create();
    $driver = dedupDriver();
    Service::factory()->create(['user_id' => $driver->id, 'is_active' => true, 'name' => 'Livraison']);

    Sanctum::actingAs($client);
    Queue::fake();

    $draftId = $this->postJson('/api/ai-request-drafts/start')->json('id');

    AiRequestDraft::find($draftId)->update([
        'chat_history' => [
            ['role' => 'user', 'content' => 'Envoie un colis à Sara', 'created_at' => now()->toIso8601String()],
            ['role' => 'assistant', 'content' => 'Parfait, je prépare la livraison.', 'created_at' => now()->toIso8601String()],
        ],
        'generated_data' => ['recipient_name' => 'Sara'],
        'status' => AiRequestDraft::STATUS_DONE,
    ]);

    $this->postJson("/api/ai-request-drafts/{$draftId}/messages", [
        'content' => 'Envoie un colis à Sara',
        'driver_slug' => 'dedup-driver-slug',
    ])->assertOk()
        ->assertJsonPath('status', 'done');

    expect(AiRequestDraft::find($draftId)->chat_history)->toHaveCount(2);

    Queue::assertNotPushed(ProcessAiChatMessageJob::class);
});
