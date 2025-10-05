<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivitySample;
use App\Services\AntiCheatService;
use App\Services\AttendanceService;
use App\Services\WorkSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class ActivityController extends Controller
{
    public function __construct(
        protected AttendanceService $attendance,
        protected WorkSessionService $workSessions,
        protected AntiCheatService $antiCheat,
    ) {
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'timestamp' => ['nullable', 'date'],
            'metrics' => ['nullable', 'array'],
            'metrics.keyboard_events' => ['nullable', 'integer', 'min:0'],
            'metrics.mouse_events' => ['nullable', 'integer', 'min:0'],
            'metrics.touch_events' => ['nullable', 'integer', 'min:0'],
            'metrics.active_window' => ['nullable', 'string'],
            'metrics.active_process' => ['nullable', 'string'],
            'device' => ['nullable', 'array'],
        ]);

        $timestamp = isset($data['timestamp'])
            ? Carbon::parse($data['timestamp'])
            : now();

        $session = $this->attendance->recordActivity($request->user(), $timestamp, $data);

        return response()->json([
            'session' => [
                'id' => $session->getKey(),
                'started_at' => optional($session->started_at)->toIso8601String(),
                'last_activity_at' => optional($session->last_activity_at)->toIso8601String(),
                'gross_seconds' => $session->gross_seconds,
                'idle_seconds' => $session->idle_seconds,
            ],
        ]);
    }

    public function storeSamples(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'samples' => ['required', 'array'],
            'samples.*.timestamp' => ['required', 'date'],
            'samples.*.keyboard_events' => ['nullable', 'integer', 'min:0'],
            'samples.*.mouse_events' => ['nullable', 'integer', 'min:0'],
            'samples.*.touch_events' => ['nullable', 'integer', 'min:0'],
            'samples.*.active_window' => ['nullable', 'string'],
            'samples.*.active_process' => ['nullable', 'string'],
            'samples.*.payload' => ['nullable', 'array'],
        ]);

        $user = $request->user();
        $created = [];

        foreach ($data['samples'] as $sampleData) {
            $timestamp = Carbon::parse($sampleData['timestamp']);
            $attendance = $this->attendance->ensureAttendanceDay($user, $timestamp);
            $session = $this->workSessions->ensureActiveSession($attendance, $timestamp, 'shift');

            $sample = ActivitySample::query()->create([
                'user_id' => $user->getKey(),
                'work_session_id' => $session->getKey(),
                'recorded_at' => $timestamp,
                'keyboard_events' => $sampleData['keyboard_events'] ?? 0,
                'mouse_events' => $sampleData['mouse_events'] ?? 0,
                'touch_events' => $sampleData['touch_events'] ?? 0,
                'active_window' => $sampleData['active_window'] ?? null,
                'active_process' => $sampleData['active_process'] ?? null,
                'payload' => Arr::except($sampleData['payload'] ?? [], ['token']),
            ]);

            if ($event = $this->antiCheat->evaluateSample($sample)) {
                $sample->is_suspected = true;
                $sample->save();
            }

            $created[] = [
                'id' => $sample->getKey(),
                'recorded_at' => $sample->recorded_at->toIso8601String(),
                'is_suspected' => $sample->is_suspected,
            ];
        }

        return response()->json([
            'samples' => $created,
        ], 201);
    }
}
