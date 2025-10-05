<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSession extends Model
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
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'is_closed' => 'bool',
            'metadata' => 'array',
        ];
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    public function idlePeriods(): HasMany
    {
        return $this->hasMany(IdlePeriod::class);
    }

    public function activitySamples(): HasMany
    {
        return $this->hasMany(ActivitySample::class);
    }

    public function suspiciousEvents(): HasMany
    {
        return $this->hasMany(SuspiciousEvent::class);
    }

    public function isActive(): bool
    {
        return ! $this->is_closed;
    }
}
