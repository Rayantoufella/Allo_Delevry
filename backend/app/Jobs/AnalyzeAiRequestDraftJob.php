<?php

namespace App\Jobs;

use App\Exceptions\AiAnalysisException;
use App\Models\AiRequestDraft;
use App\Models\Service;
use App\Services\AiRequestAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AnalyzeAiRequestDraftJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

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

        $services = $this->activeServices();

        $result = (new AiRequestAnalyzer)->analyze(
            $this->draft->input_message,
            $services->pluck('name')->all(),
        );

        if (! $this->validateResult($result)) {
            return;
        }

        $serviceId = $this->matchServiceId($result['service'] ?? null, $services);

        if ($serviceId === null) {
            $result['service'] = null;
        }

        $this->draft->update([
            'generated_data' => $result,
            'service_id' => $serviceId,
            'status' => AiRequestDraft::STATUS_DONE,
            'error_message' => null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('jobs')->error('Job échoué', [
            'job' => static::class,
            'resource_id' => $this->draft?->id,
            'error' => $e->getMessage(),
        ]);

        if ($e instanceof AiAnalysisException && $this->draft->exists) {
            $this->draft->update([
                'status' => AiRequestDraft::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
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
     * Valide la structure de la réponse IA. En cas d'échec, marque le brouillon comme failed.
     *
     * @param  array<string, mixed>  $result
     */
    private function validateResult(array $result): bool
    {
        $validator = Validator::make($result, [
            'recipient_name' => ['nullable', 'string', 'max:255'],
            'recipient_phone' => ['nullable', 'string', 'max:20'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'delivery_address' => ['nullable', 'string', 'max:255'],
            'package_description' => ['nullable', 'string'],
            'product_amount' => ['nullable', 'numeric', 'min:0'],
            'amount_to_collect' => ['nullable', 'numeric', 'min:0'],
            'scheduled_at' => ['nullable', 'date'],
            'service' => ['nullable', 'string'],
        ]);

        if ($validator->fails()) {
            $this->draft->update([
                'status' => AiRequestDraft::STATUS_FAILED,
                'error_message' => 'La réponse de l\'IA est invalide : '.$validator->errors()->first(),
            ]);

            return false;
        }

        return true;
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