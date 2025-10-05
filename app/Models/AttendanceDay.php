<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class AttendanceDay extends Model
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
            'work_date' => 'date',
            'login_at' => 'datetime',
            'logout_at' => 'datetime',
            'first_activity_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'locked_at' => 'datetime',
            'approved_at' => 'datetime',
            'metrics_snapshot' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function officeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class);
    }

    public function workSessions(): HasMany
    {
        return $this->hasMany(WorkSession::class);
    }

    public function idlePeriods(): HasManyThrough
    {
        return $this->hasManyThrough(IdlePeriod::class, WorkSession::class);
    }

    public function helpRequests(): HasMany
    {
        return $this->hasMany(HelpRequest::class);
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    public function effectiveWorkSeconds(): int
    {
        return max(0, $this->total_work_seconds - $this->total_idle_seconds + $this->manual_adjustment_seconds);
    }
}
