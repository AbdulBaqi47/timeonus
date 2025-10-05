<?php

namespace App\Services;

use App\Models\AttendanceDay;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkSession;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        protected WorkSessionService $workSessions,
        protected IdleService $idleService,
        protected CacheRepository $cache,
    ) {
    }

    public function registerLogin(User $user, array $payload = []): AttendanceDay
    {
        $now = now($this->resolveTimezone($user));

        return DB::transaction(function () use ($user, $payload, $now) {
            $attendance = $this->ensureAttendanceDay($user, $now);
            $locationId = $this->resolveOfficeLocationId($payload['location'] ?? null, $user);

            if (! $attendance->login_at) {
                $attendance->login_at = $now;
            }

            $attendance->status = 'present';
            $attendance->office_location_id = $locationId ?? $attendance->office_location_id;
            $attendance->first_activity_at ??= $now;
            $attendance->last_activity_at = $now;
            $attendance->metrics_snapshot = $this->mergeMetricsSnapshot($attendance->metrics_snapshot ?? [], $payload);
            $attendance->save();

            $session = $this->workSessions->ensureActiveSession($attendance, $now, 'shift', [
                'source' => 'login',
                'payload' => Arr::except($payload, ['token']),
            ]);

            $session->last_activity_at = $now;
            $session->save();

            $this->cacheHeartbeat($user, $now, $payload);

            return $attendance->fresh(['workSessions']);
        });
    }

    public function registerLogout(User $user, array $payload = []): AttendanceDay
    {
        $now = now($this->resolveTimezone($user));

        return DB::transaction(function () use ($user, $payload, $now) {
            $attendance = $this->ensureAttendanceDay($user, $now);

            if ($session = $this->workSessions->currentSession($attendance)) {
                $this->workSessions->closeSession($session, $now, source: 'logout');
            }

            $attendance->logout_at = $now;
            $attendance->last_activity_at = $now;
            $attendance->metrics_snapshot = $this->mergeMetricsSnapshot($attendance->metrics_snapshot ?? [], $payload, [
                'last_logout_payload' => Arr::except($payload, ['token']),
            ]);
            $attendance->save();

            $this->cacheHeartbeat($user, $now, $payload);

            return $attendance->fresh(['workSessions']);
        });
    }

    public function recordActivity(User $user, CarbonInterface $timestamp, array $metrics = []): WorkSession
    {
        $moment = method_exists($timestamp, 'copy')
            ? $timestamp->copy()->setTimezone($this->resolveTimezone($user))
            : $timestamp->setTimezone($this->resolveTimezone($user));

        return DB::transaction(function () use ($user, $moment, $metrics) {
            $attendance = $this->ensureAttendanceDay($user, $moment);
            $session = $this->workSessions->ensureActiveSession($attendance, $moment, 'shift');

            if ($idle = $this->idleService->getActiveIdlePeriod($session)) {
                $this->idleService->resolveIdle($idle, $moment, notes: 'activity_resumed');
                $session->refresh();
                $attendance->refresh();
            }

            $previous = $session->last_activity_at ?? $session->started_at ?? $moment;
            $delta = max(0, $previous->diffInSeconds($moment));
            $delta = min($delta, config('services.time_tracker.max_heartbeat_delta', 300));

            if ($delta > 0) {
                $session->gross_seconds += $delta;
                $attendance->total_work_seconds += $delta;
            }

            $session->last_activity_at = $moment;
            $session->save();

            $attendance->last_activity_at = $moment;
            $attendance->first_activity_at ??= $moment;
            $attendance->metrics_snapshot = $this->mergeMetricsSnapshot($attendance->metrics_snapshot ?? [], $metrics, [
                'last_heartbeat_at' => $moment->toIso8601String(),
            ]);
            $attendance->save();

            $this->cacheHeartbeat($user, $moment, $metrics);

            return $session->fresh();
        });
    }

    public function ensureAttendanceDay(User $user, CarbonInterface $moment): AttendanceDay
    {
        $date = $moment->toDateString();

        return AttendanceDay::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'work_date' => $date,
        ]);
    }

    protected function resolveTimezone(User $user): string
    {
        return $user->employeeProfile?->timezone ?? config('app.timezone');
    }

    protected function resolveOfficeLocationId(?array $locationPayload, User $user): ?int
    {
        if (! $locationPayload) {
            return $user->employeeProfile?->default_office_location_id;
        }

        if (isset($locationPayload['id'])) {
            return (int) $locationPayload['id'];
        }

        if (isset($locationPayload['code'])) {
            return OfficeLocation::query()->where('code', $locationPayload['code'])->value('id');
        }

        return $user->employeeProfile?->default_office_location_id;
    }

    protected function mergeMetricsSnapshot(array $snapshot, array $payload, array $overrides = []): array
    {
        $merged = array_merge($snapshot, Arr::except($payload, ['token']));

        foreach ($overrides as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    protected function cacheHeartbeat(User $user, CarbonInterface $moment, array $payload): void
    {
        $key = sprintf('attendance:last-heartbeat:%s', $user->getKey());

        $this->cache->put($key, [
            'timestamp' => $moment->toIso8601String(),
            'payload' => Arr::except($payload, ['token']),
        ], now()->addMinutes(10));
    }
}
