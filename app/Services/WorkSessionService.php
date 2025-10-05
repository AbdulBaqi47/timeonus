<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\WorkSession;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkSessionService
{
    public function __construct(protected IdleService $idleService)
    {
    }

    public function ensureActiveSession(AttendanceDay $attendanceDay, CarbonInterface $moment, string $type = 'shift', array $metadata = []): WorkSession
    {
        if ($session = $this->currentSession($attendanceDay)) {
            return $session;
        }

        return $attendanceDay->workSessions()->create([
            'session_type' => $type,
            'source' => Arr::pull($metadata, 'source', 'system'),
            'started_at' => $moment,
            'last_activity_at' => $moment,
            'metadata' => $metadata,
        ]);
    }

    public function currentSession(AttendanceDay $attendanceDay): ?WorkSession
    {
        return $attendanceDay->workSessions()
            ->where('is_closed', false)
            ->orderByDesc('started_at')
            ->first();
    }

    public function closeSession(WorkSession $session, CarbonInterface $endedAt, string $source = 'system'): WorkSession
    {
        if ($session->is_closed) {
            return $session;
        }

        return DB::transaction(function () use ($session, $endedAt, $source) {
            if ($idle = $this->idleService->getActiveIdlePeriod($session)) {
                $this->idleService->resolveIdle($idle, $endedAt, notes: 'session_closed');
            }

            $previous = $session->last_activity_at ?? $session->started_at ?? $endedAt;
            $delta = max(0, $previous->diffInSeconds($endedAt));
            $delta = min($delta, config('services.time_tracker.max_heartbeat_delta', 300));

            if ($delta > 0) {
                $session->gross_seconds += $delta;
                $session->attendanceDay?->increment('total_work_seconds', $delta);
            }

            $session->ended_at = $endedAt;
            $session->is_closed = true;
            $session->metadata = array_merge($session->metadata ?? [], [
                'closed_source' => $source,
            ]);
            $session->save();

            $attendance = $session->attendanceDay;
            if ($attendance) {
                $attendance->last_activity_at = $endedAt;
                $attendance->logout_at ??= $endedAt;
                $attendance->save();
            }

            return $session;
        });
    }

    public function closeOpenSessionsForDay(AttendanceDay $attendanceDay, CarbonInterface $endedAt): void
    {
        $attendanceDay->workSessions()
            ->where('is_closed', false)
            ->get()
            ->each(fn (WorkSession $session) => $this->closeSession($session, $endedAt));
    }
}
