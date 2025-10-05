<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProfile extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'employee_code',
        'job_title',
        'primary_role_id',
        'base_salary',
        'hourly_rate',
        'timezone',
        'default_office_location_id',
        'expected_start_time',
        'expected_end_time',
        'daily_idle_allowance_minutes',
        'date_hired',
        'date_left',
        'metadata',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'expected_start_time' => 'datetime:H:i:s',
            'expected_end_time' => 'datetime:H:i:s',
            'daily_idle_allowance_minutes' => 'int',
            'date_hired' => 'date',
            'date_left' => 'date',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'primary_role_id');
    }

    public function defaultOfficeLocation(): BelongsTo
    {
        return $this->belongsTo(OfficeLocation::class, 'default_office_location_id');
    }
}
