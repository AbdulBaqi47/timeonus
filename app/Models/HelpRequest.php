<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpRequest extends Model
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
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'escalated_at' => 'datetime',
            'duration_seconds' => 'int',
            'count_as_idle' => 'bool',
            'metadata' => 'array',
        ];
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function primaryRecipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_recipient_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(HelpRequestParticipant::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('ended_at')->whereNull('cancelled_at');
    }
}
