<?php

namespace App\Services;

use App\Models\IdlePeriod;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class IdleService
{
    public function getActiveIdlePeriod(WorkSession $session): ?IdlePeriod
    {
        return $session->idlePeriods()
            ->whereNull('ended_at')
            ->orderByDesc('started_at')
            ->first();
    }

    public function startIdle(WorkSession $session, CarbonInterface $startedAt, string $reason = 'auto', string $detectedBy = 'system', array $metadata = []): IdlePeriod
    {
        if ($existing = $this->getActiveIdlePeriod($session)) {
            return $existing;
        }

        return $session->idlePeriods()->create([
            'started_at' => $startedAt,
            'reason' => $reason,
            'detected_by' => $detectedBy,
            'metadata' => Arr::except($metadata, ['token']),
        ]);
    }

    public function resolveIdle(IdlePeriod $idle, CarbonInterface $endedAt, ?User $resolvedBy = null, ?string $notes = null): IdlePeriod
    {
        if ($idle->ended_at) {
            return $idle;
        }

        return DB::transaction(function () use ($idle, $endedAt, $resolvedBy, $notes) {
            $duration = max(0, $idle->started_at->diffInSeconds($endedAt));

            $idle->ended_at = $endedAt;
            $idle->duration_seconds = $duration;
            $idle->resolved_by = $resolvedBy?->getKey();
            $idle->resolved_at = $endedAt;
            $idle->notes = $notes ?? $idle->notes;
            $idle->save();

            $session = $idle->workSession;
            if ($session) {
                $session->idle_seconds += $duration;
                $session->save();

                $attendance = $session->attendanceDay;
                if ($attendance) {
                    $attendance->total_idle_seconds += $duration;
                    $attendance->save();
                }
            }

            return $idle;
        });
    }
}
