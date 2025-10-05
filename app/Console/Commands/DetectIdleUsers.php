<?php

namespace App\Console\Commands;

use App\Models\WorkSession;
use App\Services\IdleService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DetectIdleUsers extends Command
{
    /**
     * @var string
     */
    protected $signature = 'time:detect-idle';

    /**
     * @var string
     */
    protected $description = 'Detects inactive sessions and marks them idle after the configured threshold';

    public function __construct(protected IdleService $idleService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $threshold = config('services.time_tracker.idle_threshold_seconds', 180);
        $now = Carbon::now();
        $cutoff = $now->copy()->subSeconds($threshold);
        $detected = 0;

        WorkSession::query()
            ->where('is_closed', false)
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('last_activity_at')
                    ->orWhere('last_activity_at', '<=', $cutoff);
            })
            ->with(['idlePeriods' => fn ($query) => $query->whereNull('ended_at')])
            ->chunkById(100, function ($sessions) use (&$detected, $now, $threshold) {
                foreach ($sessions as $session) {
                    if ($this->idleService->getActiveIdlePeriod($session)) {
                        continue;
                    }

                    $startedAt = $session->last_activity_at
                        ? $session->last_activity_at->copy()
                        : $session->started_at?->copy() ?? $now->copy()->subSeconds($threshold);

                    $this->idleService->startIdle($session, $startedAt, 'auto', 'scheduler', [
                        'scheduler_detected_at' => $now->toIso8601String(),
                    ]);
                    $detected++;
                }
            });

        $this->info("Flagged {$detected} session(s) as idle.");

        return self::SUCCESS;
    }
}