<?php

namespace App\Models;

use App\Models\Gross\GrossUserPage;
use App\Services\Attendance\ShiftConfig;
use App\Services\Employees\TeamMembershipService;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    public const ROLE_ASSISTANT = 'ASSISTANT';

    public const ROLE_DRIVER = 'DRIVER';

    public const ROLE_SALES = 'SALES';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    private ?int $pendingTeamLeaderId = null;

    private bool $hasPendingTeamLeader = false;

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
        'can_confirm_gross_loss',
        'currency',
        'language_preference',
        'team_leader_id',
        'base_salary',
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
            'shift_types_default' => 'array',
            'can_confirm_gross_loss' => 'boolean',
        ];
    }

    /** Base types allowed as employee defaults (T-7.3 / T-7.7). */
    public const DEFAULT_SHIFT_OPTIONS = [
        Attendance::SHIFT_FULL_TIME,
        Attendance::SHIFT_ONLINE_HALF,
        Attendance::SHIFT_ONLINE_FULL,
    ];

    /**
     * Default shifts with windows (like TL assign rows, but every workday).
     *
     * @return list<array{type: string, start: string, end: string, expected_hours: float}>
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
     * @return array{type: string, start: string, end: string, expected_hours: float}|null
     */
    public function defaultProfileFor(string $shiftType): ?array
    {
        foreach ($this->defaultShiftProfiles() as $profile) {
            if ($profile['type'] === $shiftType) {
                return $profile;
            }
        }

        return null;
    }

    public function plannedHoursFor(string $shiftType): float
    {
        $profile = $this->defaultProfileFor($shiftType);
        if ($profile !== null) {
            return (float) $profile['expected_hours'];
        }

        return app(ShiftConfig::class)->plannedHours($shiftType);
    }

    /**
     * Accepts form rows, stored profiles, or legacy type string lists.
     *
     * @param  list<mixed>|array<int, mixed>  $rows
     * @return list<array{type: string, start: string, end: string, expected_hours: float}>
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
            $expectedHours = self::hoursBetweenTimes($start, $end);
            if ($expectedHours <= 0) {
                $expectedHours = ShiftConfig::BASE_PLANNED_HOURS[$type] ?? 8.0;
            }
            $out[] = [
                'type' => $type,
                'start' => $start,
                'end' => $end,
                'expected_hours' => $expectedHours,
            ];
        }

        if ($out !== []) {
            return $out;
        }

        $legacy = is_string($legacy) ? $legacy : Attendance::SHIFT_FULL_TIME;
        $types = match ($legacy) {
            Attendance::SHIFT_ONLINE_HALF => [Attendance::SHIFT_ONLINE_HALF],
            Attendance::SHIFT_ONLINE_FULL => [Attendance::SHIFT_ONLINE_FULL],
            default => [Attendance::SHIFT_FULL_TIME],
        };

        foreach ($types as $type) {
            $fallback = ShiftConfig::DEFAULT_TIME_WINDOWS[$type] ?? ['10:00', '18:00'];
            $start = $fallback[0];
            $end = $fallback[1];
            $out[] = [
                'type' => $type,
                'start' => $start,
                'end' => $end,
                'expected_hours' => self::hoursBetweenTimes($start, $end),
            ];
        }

        return $out;
    }

    private static function hoursBetweenTimes(string $start, string $end): float
    {
        $from = Carbon::parse('2000-01-01 '.substr($start, 0, 5));
        $to = Carbon::parse('2000-01-01 '.substr($end, 0, 5));
        if ($to->lte($from)) {
            $to->addDay();
        }

        return round($from->floatDiffInHours($to), 2);
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
            self::ROLE_ASSISTANT => ['ar' => 'مساعد إداري', 'en' => 'Admin Assistant'],
            self::ROLE_DRIVER => ['ar' => 'سائق', 'en' => 'Driver'],
            self::ROLE_SALES => ['ar' => 'مبيعات', 'en' => 'Sales'],
            default => ['ar' => 'موظف', 'en' => 'Employee'],
        };
    }

    /**
     * Field staff under team leaders (employees + drivers).
     *
     * @return list<string>
     */
    public static function fieldStaffRoles(): array
    {
        return [self::ROLE_EMPLOYEE, self::ROLE_DRIVER];
    }

    /**
     * Ops visibility: field staff + team leaders.
     *
     * @return list<string>
     */
    public static function operationsVisibleRoles(): array
    {
        return [self::ROLE_EMPLOYEE, self::ROLE_TEAM_LEADER, self::ROLE_DRIVER];
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

    public function isDriver(): bool
    {
        return $this->role === self::ROLE_DRIVER;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    public function canConfirmGrossLoss(): bool
    {
        return (bool) $this->can_confirm_gross_loss;
    }

    /** Manager/Accountant always; Sales only with can_confirm_gross_loss flag. */
    public function mayConfirmGrossLoss(): bool
    {
        if ($this->isManager() || $this->isAccountant()) {
            return true;
        }

        return $this->isSales() && $this->canConfirmGrossLoss();
    }

    /** EMPLOYEE or DRIVER — TL-led field staff. */
    public function isFieldStaff(): bool
    {
        return $this->isEmployee() || $this->isDriver();
    }

    /** EMPLOYEE and DRIVER accounts are assigned team leaders. */
    public static function roleMayHaveTeamLeaders(?string $role): bool
    {
        return in_array($role, self::fieldStaffRoles(), true);
    }

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    public function isAssistant(): bool
    {
        return $this->role === self::ROLE_ASSISTANT;
    }

    /** Punch photos required on every clock-in and clock-out. */
    public function requiresPunchPhotos(): bool
    {
        return $this->isDriver();
    }

    /** GPS required on every punch (any shift). */
    public function requiresPunchLocation(): bool
    {
        return $this->isDriver();
    }

    /** Daily report gate + self-service report (same as employees). */
    public function mustSubmitDailyReports(): bool
    {
        return true;
    }

    public function mayUseClock(): bool
    {
        return $this->isEmployee()
            || $this->isDriver()
            || $this->isSales()
            || $this->isTeamLeader()
            || $this->isManager()
            || $this->isAccountant()
            || $this->isAssistant();
    }

    /**
     * Company-wide HR operations: Manager or Assistant.
     * Employee data entry, shifts, rest days, report follow, absences.
     */
    public function canManageOperations(): bool
    {
        return $this->isManager() || $this->isAssistant();
    }

    /** Employee salary/rates on staff records — Manager or Assistant (not TL). */
    public function canManageRates(): bool
    {
        return $this->isManager() || $this->isAssistant();
    }

    /**
     * Roles this actor may assign on create/edit staff.
     *
     * @return list<string>
     */
    public function staffRolesTheyMayAssign(): array
    {
        if ($this->isManager()) {
            return [
                self::ROLE_EMPLOYEE,
                self::ROLE_DRIVER,
                self::ROLE_SALES,
                self::ROLE_TEAM_LEADER,
                self::ROLE_ACCOUNTANT,
                self::ROLE_ASSISTANT,
            ];
        }

        if ($this->isAssistant()) {
            return [self::ROLE_EMPLOYEE, self::ROLE_DRIVER, self::ROLE_TEAM_LEADER];
        }

        return [];
    }

    public function mayEditStaff(User $target): bool
    {
        if ($this->isManager()) {
            return ! $target->isManager();
        }

        if ($this->isAssistant()) {
            return $target->isFieldStaff() || $target->isTeamLeader();
        }

        return false;
    }

    public function localizedName(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $preferred = app()->getLocale() === 'ar' ? $ar : $en;
        $fallback = app()->getLocale() === 'ar' ? $en : $ar;

        if ($preferred !== '') {
            return $preferred;
        }

        return $fallback !== '' ? $fallback : (string) $this->email;
    }

    public function teamLeaderDisplayNames(): string
    {
        $leaders = $this->relationLoaded('teamLeaders')
            ? $this->teamLeaders
            : $this->teamLeaders()->get();

        return $leaders
            ->map(fn (User $leader): string => $leader->localizedName())
            ->filter()
            ->implode(' · ');
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

        static::saved(function (User $user): void {
            if (! $user->hasPendingTeamLeader) {
                return;
            }

            $ids = $user->pendingTeamLeaderId !== null ? [$user->pendingTeamLeaderId] : [];
            $user->hasPendingTeamLeader = false;
            $user->pendingTeamLeaderId = null;
            app(TeamMembershipService::class)->syncLeaders($user, $ids);
        });
    }

    public function setTeamLeaderIdAttribute(mixed $value): void
    {
        $this->pendingTeamLeaderId = ($value === null || $value === '') ? null : (int) $value;
        $this->hasPendingTeamLeader = true;
    }

    public function getTeamLeaderIdAttribute(): ?int
    {
        if ($this->hasPendingTeamLeader && ! $this->exists) {
            return $this->pendingTeamLeaderId;
        }

        $id = $this->relationLoaded('teamLeaders')
            ? $this->teamLeaders->first()?->id
            : $this->teamLeaders()->orderBy('employee_team_leaders.id')->value('users.id');

        return $id === null ? null : (int) $id;
    }

    public function getTeamLeaderAttribute(): ?User
    {
        if ($this->relationLoaded('teamLeaders')) {
            $first = $this->teamLeaders->first();

            return $first instanceof User ? $first : null;
        }

        $first = $this->teamLeaders()->orderBy('employee_team_leaders.id')->first();

        return $first instanceof User ? $first : null;
    }

    /**
     * Employees this team leader is assigned to (T-8.1 pivot).
     */
    public function scopeLedBy(Builder $query, int $leaderId): void
    {
        $query->whereExists(function ($sub) use ($leaderId): void {
            $sub->selectRaw('1')
                ->from('employee_team_leaders')
                ->whereColumn('employee_team_leaders.user_id', 'users.id')
                ->where('employee_team_leaders.team_leader_id', $leaderId);
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

    public function valuationAssignments(): HasMany
    {
        return $this->hasMany(ValuationAssignment::class);
    }

    public function grossPages(): HasMany
    {
        return $this->hasMany(GrossUserPage::class);
    }

    public function valuationSheets(): HasMany
    {
        return $this->hasMany(ValuationSheet::class);
    }

    public function teamLeaders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_team_leaders', 'user_id', 'team_leader_id')
            ->withPivot('assigned_by')
            ->withTimestamps()
            ->orderBy('employee_team_leaders.id');
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'employee_team_leaders', 'team_leader_id', 'user_id')
            ->withPivot('assigned_by')
            ->withTimestamps();
    }
}
