<?php

namespace App\Jobs;

use App\Exceptions\AiAnalysisException;
use App\Models\AiRequestDraft;
use App\Models\Service;
use App\Services\AiRequestAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
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

        $activeServiceNames = Service::where('user_id', $this->driverUserId)
            ->where('is_active', true)
            ->pluck('name')
            ->all();

        $result = (new AiRequestAnalyzer)->analyze($this->draft->input_message, $activeServiceNames);

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

            return;
        }

        $serviceId = null;

        if ($result['service'] !== null) {
            $service = Service::where('user_id', $this->driverUserId)
                ->where('is_active', true)
                ->where('name', $result['service'])
                ->first();

            if ($service === null) {
                $service = Service::where('user_id', $this->driverUserId)
                    ->where('is_active', true)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($result['service'])])
                    ->first();
            }

            $serviceId = $service?->id;
        }

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
}
