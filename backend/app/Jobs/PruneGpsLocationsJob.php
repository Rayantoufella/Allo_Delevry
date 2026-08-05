<?php

namespace App\Jobs;

use App\Models\GpsLocation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class PruneGpsLocationsJob implements ShouldQueue
{
    use Queueable;

    public const DAYS = 7;

    public int $tries = 3;

    public int $timeout = 30;

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        GpsLocation::where('created_at', '<', now()->subDays(self::DAYS))->delete();
    }

    public function failed(\Throwable $e): void
    {
        Log::channel('jobs')->error('Job échoué', [
            'job' => static::class,
            'error' => $e->getMessage(),
        ]);
    }
}
