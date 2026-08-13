<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_MANAGER = 'MANAGER';

    public const ROLE_TEAM_LEADER = 'TEAM_LEADER';

    public const ROLE_EMPLOYEE = 'EMPLOYEE';

    public const ROLE_ACCOUNTANT = 'ACCOUNTANT';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name_ar',
        'name_en',
        'email',
        'password',
        'role',
        'currency',
        'language_preference',
        'team_leader_id',
        'base_salary',
        'online_half_day_rate',
        'online_full_day_rate',
        'overtime_hourly_rate',
        'shift_type_default',
        'work_start_time',
        'work_end_time',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'base_salary' => 'decimal:2',
            'online_half_day_rate' => 'decimal:2',
            'online_full_day_rate' => 'decimal:2',
            'overtime_hourly_rate' => 'decimal:2',
        ];
    }

    /**
     * TIME columns come back as "H:i:s" — normalize to "H:i" for forms/API.
     */
    public function getWorkStartTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function getWorkEndTimeAttribute(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isTeamLeader(): bool
    {
        return $this->role === self::ROLE_TEAM_LEADER;
    }

    public function isEmployee(): bool
    {
        return $this->role === self::ROLE_EMPLOYEE;
    }

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    /** Admin or Accountant — may see/manage payroll money (approve stays Manager-only). */
    public function canAccessPayroll(): bool
    {
        return $this->isManager() || $this->isAccountant();
    }

    /** Admin or Accountant — advances CRUD. */
    public function canAccessAdvances(): bool
    {
        return $this->isManager() || $this->isAccountant();
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function profile(): HasOne
    {
        return $this->hasOne(EmployeeProfile::class);
    }

    public function qualifications(): HasMany
    {
        return $this->hasMany(EmployeeQualification::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function emergencyContacts(): HasMany
    {
        return $this->hasMany(EmergencyContact::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('is_active', true);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    public function vacations(): HasMany
    {
        return $this->hasMany(Vacation::class);
    }

    public function restDays(): HasMany
    {
        return $this->hasMany(RestDay::class);
    }

    public function vacationBalances(): HasMany
    {
        return $this->hasMany(VacationBalance::class);
    }

    public function shiftAssignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    public function payrollRecords(): HasMany
    {
        return $this->hasMany(PayrollRecord::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(User::class, 'team_leader_id');
    }
}
