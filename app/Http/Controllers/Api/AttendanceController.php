<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Services\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'location' => ['nullable', 'array'],
            'location.id' => ['nullable', 'integer'],
            'location.code' => ['nullable', 'string'],
            'location.latitude' => ['nullable', 'numeric'],
            'location.longitude' => ['nullable', 'numeric'],
            'location.accuracy' => ['nullable', 'numeric'],
            'location.source' => ['nullable', 'string'],
            'device' => ['nullable', 'array'],
            'device.os' => ['nullable', 'string'],
            'device.name' => ['nullable', 'string'],
            'device.ip' => ['nullable', 'string'],
        ]);

        $attendance = $this->attendance->registerLogin($request->user(), $data);

        return response()->json([
            'attendance' => $this->transformAttendance($attendance),
        ], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'device' => ['nullable', 'array'],
            'device.os' => ['nullable', 'string'],
            'device.name' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1024'],
        ]);

        $attendance = $this->attendance->registerLogout($request->user(), $data);

        return response()->json([
            'attendance' => $this->transformAttendance($attendance),
        ]);
    }

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            throw ValidationException::withMessages(['auth' => 'Unauthenticated']);
        }

        /** @var AttendanceDay|null $attendance */
        $attendance = $user->attendanceDays()
            ->latest('work_date')
            ->with(['workSessions' => fn ($query) => $query->orderByDesc('started_at')])
            ->first();

        return response()->json([
            'attendance' => $attendance ? $this->transformAttendance($attendance) : null,
        ]);
    }

    protected function transformAttendance(AttendanceDay $attendance): array
    {
        return [
            'id' => $attendance->getKey(),
            'date' => $attendance->work_date->toDateString(),
            'status' => $attendance->status,
            'login_at' => optional($attendance->login_at)->toIso8601String(),
            'logout_at' => optional($attendance->logout_at)->toIso8601String(),
            'total_work_seconds' => $attendance->total_work_seconds,
            'total_idle_seconds' => $attendance->total_idle_seconds,
            'total_help_seconds' => $attendance->total_help_seconds,
            'manual_adjustment_seconds' => $attendance->manual_adjustment_seconds,
            'effective_work_seconds' => $attendance->effectiveWorkSeconds(),
            'metrics_snapshot' => $attendance->metrics_snapshot,
            'latest_session' => optional($attendance->workSessions->first(), function ($session) {
                return [
                    'id' => $session->getKey(),
                    'started_at' => optional($session->started_at)->toIso8601String(),
                    'ended_at' => optional($session->ended_at)->toIso8601String(),
                    'gross_seconds' => $session->gross_seconds,
                    'idle_seconds' => $session->idle_seconds,
                    'is_closed' => $session->is_closed,
                ];
            }),
        ];
    }
}
