<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps()
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function employeeProfile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(AttendanceDay::class);
    }

    public function workSessions(): HasManyThrough
    {
        return $this->hasManyThrough(WorkSession::class, AttendanceDay::class);
    }

    public function idlePeriods(): HasManyThrough
    {
        return $this->hasManyThrough(IdlePeriod::class, WorkSession::class);
    }

    public function helpRequestsInitiated(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'initiator_id');
    }

    public function helpRequestsReceived(): HasMany
    {
        return $this->hasMany(HelpRequest::class, 'primary_recipient_id');
    }

    public function salaryRuns(): HasMany
    {
        return $this->hasMany(SalaryRun::class);
    }

    public function salaryAdjustments(): HasMany
    {
        return $this->hasMany(SalaryAdjustment::class);
    }

    public function hasRole(string|array $roles): bool
    {
        $needles = Arr::wrap($roles);

        return $this->roles
            ->pluck('slug')
            ->intersect($needles)
            ->isNotEmpty();
    }

    public function assignRole(string|Role $role): void
    {
        $roleId = $role instanceof Role
            ? $role->getKey()
            : Role::query()->where('slug', $role)->value('id');

        if (! $roleId) {
            return;
        }

        $this->roles()->syncWithoutDetaching([
            $roleId => ['assigned_at' => now()],
        ]);
    }
}
