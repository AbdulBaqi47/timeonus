<?php

namespace App\Services;

use App\Models\HelpRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class HelpDeskService
{
    public function __construct(
        protected AttendanceService $attendanceService,
        protected WorkSessionService $workSessions,
        protected IdleService $idleService,
    ) {
    }

    public function createRequest(User $initiator, array $payload): HelpRequest
    {
        $timestamp = $this->resolveTimestamp($initiator, $payload['requested_at'] ?? null);
        $attendance = $this->attendanceService->ensureAttendanceDay($initiator, $timestamp);

        return DB::transaction(function () use ($initiator, $payload, $attendance, $timestamp) {
            $request = HelpRequest::query()->create([
                'attendance_day_id' => $attendance->getKey(),
                'initiator_id' => $initiator->getKey(),
                'primary_recipient_id' => $payload['primary_recipient_id'] ?? null,
                'team_lead_id' => $payload['team_lead_id'] ?? null,
                'topic' => $payload['topic'],
                'status' => 'pending',
                'channel' => $payload['channel'] ?? 'desktop',
                'requested_at' => $timestamp,
                'metadata' => Arr::except($payload, ['topic', 'primary_recipient_id', 'team_lead_id', 'requested_at', 'participants']),
            ]);

            $participants = collect($payload['participants'] ?? [])
                ->map(fn (array $participant) => [
                    'user_id' => $participant['user_id'],
                    'role' => $participant['role'] ?? 'participant',
                    'status' => $participant['status'] ?? 'invited',
                ])
                ->push([
                    'user_id' => $initiator->getKey(),
                    'role' => 'initiator',
                    'status' => 'accepted',
                    'joined_at' => $timestamp,
                ])
                ->unique('user_id');

            foreach ($participants as $data) {
                $request->participants()->create($data);
            }

            return $request->fresh('participants');
        });
    }

    public function acceptRequest(HelpRequest $request, User $participant, CarbonInterface $timestamp): HelpRequest
    {
        $request->participants()
            ->where('user_id', $participant->getKey())
            ->update([
                'status' => 'accepted',
                'joined_at' => $timestamp,
            ]);

        if (! $request->accepted_at) {
            $request->accepted_at = $timestamp;
            $request->status = 'accepted';
            $request->save();
        }

        return $request->fresh('participants');
    }

    public function startRequest(HelpRequest $request, CarbonInterface $timestamp): HelpRequest
    {
        if ($request->status === 'in_progress') {
            return $request;
        }

        $request->update([
            'started_at' => $timestamp,
            'status' => 'in_progress',
        ]);

        return $request->fresh();
    }

    public function finishRequest(HelpRequest $request, CarbonInterface $endedAt, bool $countAsIdle = false): HelpRequest
    {
        if ($request->ended_at) {
            return $request;
        }

        $started = $request->started_at
            ?? $request->accepted_at
            ?? $request->requested_at
            ?? $endedAt;

        $duration = max(0, $started->diffInSeconds($endedAt));

        $request->fill([
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'status' => 'resolved',
            'count_as_idle' => $countAsIdle,
        ])->save();

        $attendance = $request->attendanceDay;
        if ($attendance) {
            $attendance->total_help_seconds += $duration;
            if ($countAsIdle) {
                $attendance->total_idle_seconds += $duration;
            }
            $attendance->save();

            if ($countAsIdle && ($session = $this->workSessions->currentSession($attendance))) {
                $start = method_exists($endedAt, 'copy')
                    ? $endedAt->copy()->subSeconds($duration)
                    : Carbon::parse($endedAt->toIso8601String())->subSeconds($duration);

                $idle = $this->idleService->startIdle($session, $start, 'help_timeout', 'system', [
                    'help_request_id' => $request->getKey(),
                ]);

                $this->idleService->resolveIdle($idle, $endedAt, notes: 'help_marked_idle');
            }
        }

        $request->participants()
            ->whereNull('left_at')
            ->update([
                'left_at' => $endedAt,
                'status' => 'completed',
            ]);

        return $request->fresh('participants');
    }

    public function escalateRequest(HelpRequest $request, ?User $teamLead, CarbonInterface $timestamp): HelpRequest
    {
        $request->update([
            'team_lead_id' => $teamLead?->getKey() ?? $request->team_lead_id,
            'escalated_at' => $timestamp,
            'status' => 'escalated',
        ]);

        return $request->fresh();
    }

    public function cancelRequest(HelpRequest $request, CarbonInterface $timestamp, ?string $reason = null): HelpRequest
    {
        $request->update([
            'cancelled_at' => $timestamp,
            'status' => 'cancelled',
            'notes' => trim(($request->notes ?? '').' '.($reason ?? '')),
        ]);

        $request->participants()
            ->whereNull('left_at')
            ->update([
                'left_at' => $timestamp,
                'status' => 'cancelled',
            ]);

        return $request->fresh('participants');
    }

    protected function resolveTimestamp(User $user, ?string $value): CarbonInterface
    {
        $timezone = $user->employeeProfile?->timezone ?? config('app.timezone');

        return $value
            ? Carbon::parse($value, $timezone)
            : now($timezone);
    }
}
