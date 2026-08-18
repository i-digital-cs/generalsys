<?php

namespace App\Models;

use App\Services\Attendance\ShiftConfig;
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
        'job_title_ar',
        'job_title_en',
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
        'shift_types_default',
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
            'shift_types_default' => 'array',
        ];
    }

    /** Base types allowed as employee defaults (T-7.3). */
    public const DEFAULT_SHIFT_OPTIONS = [
        Attendance::SHIFT_FULL_TIME,
        Attendance::SHIFT_ONLINE_HALF,
    ];

    /**
     * Default shifts with windows (like TL assign rows, but every workday).
     *
     * @return list<array{type: string, start: string, end: string}>
     */
    public function defaultShiftProfiles(): array
    {
        return self::normalizeDefaultShiftProfiles(
            is_array($this->shift_types_default) ? $this->shift_types_default : [],
            $this->attributes['shift_type_default'] ?? null,
        );
    }

    /**
     * @return list<string>
     */
    public function defaultShiftTypes(): array
    {
        return array_values(array_map(
            fn (array $row) => $row['type'],
            $this->defaultShiftProfiles(),
        ));
    }

    public function hasDefaultShift(string $shiftType): bool
    {
        return in_array($shiftType, $this->defaultShiftTypes(), true);
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    public function defaultWindowFor(string $shiftType): ?array
    {
        foreach ($this->defaultShiftProfiles() as $profile) {
            if ($profile['type'] === $shiftType) {
                return [$profile['start'], $profile['end']];
            }
        }

        return null;
    }

    /**
     * Accepts form rows, stored profiles, or legacy type string lists.
     *
     * @param  list<mixed>|array<int, mixed>  $rows
     * @return list<array{type: string, start: string, end: string}>
     */
    public static function normalizeDefaultShiftProfiles(array $rows, mixed $legacy = null): array
    {
        $out = [];
        $seen = [];

        foreach ($rows as $row) {
            $type = null;
            $start = null;
            $end = null;

            if (is_string($row) && $row !== '') {
                $type = $row;
            } elseif (is_array($row)) {
                $type = $row['type'] ?? $row['shift_type'] ?? null;
                $start = isset($row['start']) ? substr((string) $row['start'], 0, 5) : null;
                $end = isset($row['end']) ? substr((string) $row['end'], 0, 5) : null;
                if (($type === null || $type === '') && ($start === null || $start === '') && ($end === null || $end === '')) {
                    continue;
                }
            } else {
                continue;
            }

            if (! is_string($type) || ! in_array($type, self::DEFAULT_SHIFT_OPTIONS, true)) {
                continue;
            }

            if (isset($seen[$type])) {
                continue;
            }

            $fallback = ShiftConfig::DEFAULT_TIME_WINDOWS[$type] ?? ['10:00', '18:00'];
            if ($start === null || $start === '') {
                $start = $fallback[0];
            }
            if ($end === null || $end === '') {
                $end = $fallback[1];
            }

            $seen[$type] = true;
            $out[] = [
                'type' => $type,
                'start' => $start,
                'end' => $end,
            ];
        }

        if ($out !== []) {
            return $out;
        }

        $legacy = is_string($legacy) ? $legacy : Attendance::SHIFT_FULL_TIME;
        $types = match ($legacy) {
            Attendance::SHIFT_ONLINE_HALF => [Attendance::SHIFT_ONLINE_HALF],
            Attendance::SHIFT_ONLINE_FULL => [
                Attendance::SHIFT_FULL_TIME,
                Attendance::SHIFT_ONLINE_HALF,
            ],
            default => [Attendance::SHIFT_FULL_TIME],
        };

        foreach ($types as $type) {
            $fallback = ShiftConfig::DEFAULT_TIME_WINDOWS[$type] ?? ['10:00', '18:00'];
            $out[] = [
                'type' => $type,
                'start' => $fallback[0],
                'end' => $fallback[1],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>|array<int, mixed>  $types
     * @return list<string>
     */
    public static function normalizeDefaultShiftTypes(array $types, mixed $legacy = null): array
    {
        return array_values(array_map(
            fn (array $row) => $row['type'],
            self::normalizeDefaultShiftProfiles($types, $legacy),
        ));
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

    /**
     * @return array{ar: string, en: string}
     */
    public static function defaultJobTitles(string $role): array
    {
        return match ($role) {
            self::ROLE_MANAGER => ['ar' => 'مدير', 'en' => 'Manager'],
            self::ROLE_TEAM_LEADER => ['ar' => 'قائد فريق', 'en' => 'Team Leader'],
            self::ROLE_ACCOUNTANT => ['ar' => 'محاسب', 'en' => 'Accountant'],
            default => ['ar' => 'موظف', 'en' => 'Employee'],
        };
    }

    public function localizedJobTitle(): ?string
    {
        $ar = trim((string) $this->job_title_ar);
        $en = trim((string) $this->job_title_en);
        $preferred = app()->getLocale() === 'ar' ? $ar : $en;
        $fallback = app()->getLocale() === 'ar' ? $en : $ar;

        if ($preferred !== '') {
            return $preferred;
        }

        return $fallback !== '' ? $fallback : null;
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

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            $typesDirty = $user->isDirty('shift_types_default');
            $scalarDirty = $user->isDirty('shift_type_default');
            $hasTypes = is_array($user->shift_types_default) && $user->shift_types_default !== [];

            if ($typesDirty && $hasTypes) {
                $profiles = self::normalizeDefaultShiftProfiles(
                    $user->shift_types_default,
                    $user->shift_type_default,
                );
            } elseif ($scalarDirty || ! $hasTypes) {
                $legacy = $user->shift_type_default;
                $profiles = self::normalizeDefaultShiftProfiles([], $legacy);

                // Legacy custom work_start/end apply only to a single FULL_TIME
                // default (overnight / custom FT). Online½ keeps ShiftConfig 06–10.
                $start = $user->work_start_time;
                $end = $user->work_end_time;
                if (
                    count($profiles) === 1
                    && $profiles[0]['type'] === Attendance::SHIFT_FULL_TIME
                    && $start !== null && $start !== ''
                    && $end !== null && $end !== ''
                ) {
                    $profiles[0]['start'] = substr((string) $start, 0, 5);
                    $profiles[0]['end'] = substr((string) $end, 0, 5);
                }
            } else {
                return;
            }

            $user->shift_types_default = $profiles;
            $user->shift_type_default = $profiles[0]['type'];

            // Overall span for roster prefill; overnight single windows keep start>end.
            if (count($profiles) === 1) {
                $user->work_start_time = $profiles[0]['start'];
                $user->work_end_time = $profiles[0]['end'];
            } else {
                $user->work_start_time = collect($profiles)->min('start');
                $user->work_end_time = collect($profiles)->max('end');
            }
        });
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
