<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryRun;
use App\Models\User;
use App\Services\SalaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class SalaryController extends Controller
{
    public function __construct(protected SalaryService $salaryService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'start' => ['required', 'date'],
            'end' => ['nullable', 'date', 'after_or_equal:start'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $start = Carbon::parse($data['start']);
        $end = isset($data['end']) ? Carbon::parse($data['end']) : $start->copy()->endOfMonth();

        $targetUser = $request->user();
        if (isset($data['user_id']) && (int) $data['user_id'] !== $targetUser->getKey()) {
            if (! $targetUser->hasRole(['super_admin', 'admin', 'hr', 'finance'])) {
                throw ValidationException::withMessages(['user_id' => 'Not authorized to view other salaries.']);
            }
            $targetUser = User::query()->findOrFail($data['user_id']);
        }

        $summary = $this->salaryService->buildSummary($targetUser, $start, $end);

        return response()->json([
            'summary' => $summary,
        ]);
    }

    public function runs(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = SalaryRun::query()->where('user_id', $user->getKey());

        if ($user->hasRole(['super_admin', 'admin', 'hr', 'finance']) && $request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $runs = $query->latest('period_end')->limit(12)->get();

        return response()->json([
            'runs' => $runs->map(fn (SalaryRun $run) => [
                'id' => $run->getKey(),
                'period_start' => $run->period_start->toDateString(),
                'period_end' => $run->period_end->toDateString(),
                'status' => $run->status,
                'net_pay' => $run->net_pay,
                'gross_pay' => $run->gross_pay,
                'idle_seconds' => $run->idle_seconds,
                'actual_work_seconds' => $run->actual_work_seconds,
            ])->all(),
        ]);
    }
}
