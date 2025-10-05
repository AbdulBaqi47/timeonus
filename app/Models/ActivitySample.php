<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ActivitySample extends Model
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
            'recorded_at' => 'datetime',
            'keyboard_events' => 'int',
            'mouse_events' => 'int',
            'touch_events' => 'int',
            'is_suspected' => 'bool',
            'payload' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workSession(): BelongsTo
    {
        return $this->belongsTo(WorkSession::class);
    }

    public function suspiciousEvents(): HasMany
    {
        return $this->hasMany(SuspiciousEvent::class);
    }

    public function scopeSuspicious($query)
    {
        return $query->where('is_suspected', true);
    }
}
