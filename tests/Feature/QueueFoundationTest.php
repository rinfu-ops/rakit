<?php

namespace Tests\Feature;

use App\Jobs\PhaseOneQueueProbe;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueFoundationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_queue_worker_processes_phase_one_probe_job(): void
    {
        $cacheKey = 'phase-one-queue-probe';

        $this->assertSame('database', config('queue.default'));

        Cache::forget($cacheKey);

        PhaseOneQueueProbe::dispatch($cacheKey);

        $this->assertNull(Cache::get($cacheKey));
        $this->assertSame(1, DB::table('jobs')->count());

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--stop-when-empty' => true,
        ])->assertExitCode(0);

        $this->assertTrue(Cache::get($cacheKey));
        $this->assertSame(0, DB::table('jobs')->count());
    }
}
