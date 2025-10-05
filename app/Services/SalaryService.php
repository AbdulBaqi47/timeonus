<?php

namespace App\Services;

use App\Models\SalaryAdjustment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SalaryService
{
    public function buildSummary(User $user, CarbonInterface $start, CarbonInterface $end): array
    {
        $startDate = CarbonImmutable::parse($start)->startOfDay();
        $endDate = CarbonImmutable::parse($end)->endOfDay();

        $attendanceDays = $user->attendanceDays()
            ->whereBetween('work_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->get();

        $totals = [
            'work_seconds' => $attendanceDays->sum('total_work_seconds'),
            'idle_seconds' => $attendanceDays->sum('total_idle_seconds'),
            'help_seconds' => $attendanceDays->sum('total_help_seconds'),
            'manual_adjustment_seconds' => $attendanceDays->sum('manual_adjustment_seconds'),
            'effective_seconds' => $attendanceDays->sum(fn ($day) => $day->effectiveWorkSeconds()),
            'late_minutes' => $attendanceDays->sum('late_minutes'),
        ];

        $profile = $user->employeeProfile;
        $hourlyRate = $profile?->hourly_rate;

        if (! $hourlyRate && $profile?->base_salary) {
            $hourlyRate = $profile->base_salary / 160; // default 160 working hours per month
        }

        $hourlyRate ??= 0;
        $effectiveHours = $totals['effective_seconds'] / 3600;
        $grossPay = round($effectiveHours * $hourlyRate, 2);

        $adjustments = $this->collectAdjustments($user, $startDate, $endDate);
        $adjustmentAmount = round($adjustments->sum('amount'), 2);
        $adjustmentSeconds = $adjustments->sum('adjustment_seconds');

        $netPay = $grossPay + $adjustmentAmount;

        return [
            'range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'hourly_rate' => round($hourlyRate, 2),
            'effective_hours' => round($effectiveHours, 2),
            'gross_pay' => $grossPay,
            'adjustments_amount' => $adjustmentAmount,
            'adjustments_seconds' => $adjustmentSeconds,
            'net_pay' => round($netPay, 2),
            'totals' => $totals,
            'attendance_days' => $attendanceDays->map(fn ($day) => [
                'date' => $day->work_date->toDateString(),
                'status' => $day->status,
                'login_at' => optional($day->login_at)->toIso8601String(),
                'logout_at' => optional($day->logout_at)->toIso8601String(),
                'work_seconds' => $day->total_work_seconds,
                'idle_seconds' => $day->total_idle_seconds,
                'help_seconds' => $day->total_help_seconds,
                'manual_adjustment_seconds' => $day->manual_adjustment_seconds,
                'effective_seconds' => $day->effectiveWorkSeconds(),
                'late_minutes' => $day->late_minutes,
                'late_reason' => $day->late_reason,
            ])->all(),
        ];
    }

    protected function collectAdjustments(User $user, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return SalaryAdjustment::query()
            ->where('user_id', $user->getKey())
            ->whereBetween('created_at', [
                CarbonImmutable::parse($start)->startOfDay(),
                CarbonImmutable::parse($end)->endOfDay(),
            ])
            ->get();
    }
}
