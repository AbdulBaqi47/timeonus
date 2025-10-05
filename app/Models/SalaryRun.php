<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalaryRun extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'expected_work_seconds' => 'int',
            'actual_work_seconds' => 'int',
            'idle_seconds' => 'int',
            'help_seconds' => 'int',
            'manual_adjustment_seconds' => 'int',
            'base_salary' => 'decimal:2',
            'gross_pay' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'total_adjustments_amount' => 'decimal:2',
            'total_deductions_amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'locked_at' => 'datetime',
            'breakdown' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('period_start', $start)->where('period_end', $end);
    }
}
