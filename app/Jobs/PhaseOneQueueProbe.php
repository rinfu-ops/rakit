<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PhaseOneQueueProbe implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public readonly string $cacheKey) {}

    public function handle(): void
    {
        Cache::put($this->cacheKey, true, now()->addMinute());
    }
}
