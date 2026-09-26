<?php

namespace App\Services\Employees;

use App\Models\EmployeeProfile;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Services\Gross\GrossUserPageService;
use App\Services\Valuation\ValuationAssignmentService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Employee account + profile + contract + qualifications + contacts,
 * always written in ONE transaction (AGENTS.md §1).
 */
class EmployeeService
{
    public function __construct(
        private readonly TeamMembershipService $membership,
        private readonly GrossUserPageService $grossPages,
        private readonly ValuationAssignmentService $valuationAssignments,
    ) {}

    /**
     * Next sequential code: EMP-001, EMP-002, … (skips non-numeric suffixes).
     */
    public function nextEmployeeCode(): string
    {
        $max = EmployeeProfile::query()
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('employee_code')
            ->map(function (string $code): int {
                return preg_match('/^EMP-(\d+)$/', $code, $matches) ? (int) $matches[1] : 0;
            })
            ->max();

        return 'EMP-'.str_pad((string) (($max ?? 0) + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $data  validated payload (see StoreEmployeeRequest)
     */
    public function create(array $data, ?UploadedFile $photo = null, ?User $assignedBy = null): User
    {
        return DB::transaction(function () use ($data, $photo, $assignedBy) {
            /** @var User $user */
            $user = User::create($this->userAttributes($data));

            $profile = $this->profileAttributes($user, $data, $photo);
            $profile['employee_code'] = $this->nextEmployeeCode();
            $user->profile()->create($profile);
            $user->contracts()->create($data['contract'] + ['is_active' => true]);

            $this->syncQualifications($user, $data['qualifications'] ?? []);
            $this->syncEmergencyContacts($user, $data['contacts'] ?? []);
            $this->syncTeamLeaders($user, $data, $assignedBy);
            $this->syncValuationModules($user, $data, $assignedBy);
            $this->syncGrossPages($user, $data, $assignedBy);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated payload (see UpdateEmployeeRequest)
     */
    public function update(User $user, array $data, ?UploadedFile $photo = null, ?User $assignedBy = null): User
    {
        return DB::transaction(function () use ($user, $data, $photo, $assignedBy) {
            $attributes = $this->userAttributes($data);

            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }

            $user->update($attributes);

            $user->profile()->updateOrCreate(
                ['user_id' => $user->id],
                $this->profileAttributes($user, $data, $photo),
            );
            $user->contracts()->updateOrCreate(
                ['user_id' => $user->id, 'is_active' => true],
                $data['contract'] + ['is_active' => true],
            );

            $this->syncQualifications($user, $data['qualifications'] ?? []);
            $this->syncEmergencyContacts($user, $data['contacts'] ?? []);
            $this->syncTeamLeaders($user, $data, $assignedBy);
            $this->syncValuationModules($user, $data, $assignedBy);
            $this->syncGrossPages($user, $data, $assignedBy);

            return $user->refresh();
        });
    }

    /**
     * Paginated, filtered employee list — aggregates/search run in SQL.
     *
     * @return LengthAwarePaginator<User>
     */
    public function paginate(?string $search, ?string $status, int $perPage = 25, ?User $actor = null)
    {
        $roles = $actor?->isAssistant()
            ? [User::ROLE_EMPLOYEE, User::ROLE_DRIVER, User::ROLE_TEAM_LEADER]
            : [User::ROLE_EMPLOYEE, User::ROLE_DRIVER, User::ROLE_TEAM_LEADER, User::ROLE_ACCOUNTANT, User::ROLE_ASSISTANT, User::ROLE_SALES];

        return User::query()
            ->with(['profile', 'teamLeaders'])
            ->whereIn('role', $roles)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhere('job_title_ar', 'like', "%{$search}%")
                        ->orWhere('job_title_en', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($profile) => $profile->where('employee_code', 'like', "%{$search}%"))
                        ->orWhereExists(function ($sub) use ($search) {
                            $sub->selectRaw('1')
                                ->from('employee_team_leaders as etl')
                                ->join('users as tls', 'tls.id', '=', 'etl.team_leader_id')
                                ->whereColumn('etl.user_id', 'users.id')
                                ->where(function ($names) use ($search) {
                                    $names->where('tls.name_ar', 'like', "%{$search}%")
                                        ->orWhere('tls.name_en', 'like', "%{$search}%");
                                });
                        });
                });
            })
            ->when($status, fn ($query) => $query->where('status', $status))
            ->orderBy('name_en')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return array<string, mixed>
     */
    private function userAttributes(array $data): array
    {
        $attributes = array_merge(
            Arr::only($data, [
                'name_ar', 'name_en', 'job_title_ar', 'job_title_en', 'email', 'role', 'currency', 'language_preference',
                'status',
                'base_salary',
            ]),
            [
                'password' => $data['password'] ?? null,
                'can_confirm_gross_loss' => ($data['role'] ?? null) === User::ROLE_SALES
                    ? (bool) ($data['can_confirm_gross_loss'] ?? false)
                    : false,
            ],
        );

        $rows = [];
        foreach (array_values($data['default_shifts'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rows[] = [
                'type' => $row['shift_type'] ?? $row['type'] ?? null,
                'start' => $row['start'] ?? null,
                'end' => $row['end'] ?? null,
            ];
        }

        $profiles = User::normalizeDefaultShiftProfiles(
            $rows,
            $data['shift_type_default'] ?? null,
        );
        $attributes['shift_types_default'] = $profiles;
        $attributes['shift_type_default'] = $profiles[0]['type'];
        if (count($profiles) === 1) {
            $attributes['work_start_time'] = $profiles[0]['start'];
            $attributes['work_end_time'] = $profiles[0]['end'];
        } else {
            $attributes['work_start_time'] = collect($profiles)->min('start');
            $attributes['work_end_time'] = collect($profiles)->max('end');
        }

        return $this->applyJobTitles($attributes);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncTeamLeaders(User $user, array $data, ?User $assignedBy = null): void
    {
        if (! $user->isFieldStaff()) {
            $this->membership->syncLeaders($user, [], $assignedBy);

            return;
        }

        $ids = [];
        foreach ($data['team_leader_ids'] ?? [] as $raw) {
            if ($raw === null || $raw === '') {
                continue;
            }
            $ids[] = (int) $raw;
        }
        $this->membership->syncLeaders($user, $ids, $assignedBy);
    }

    /**
     * Blank titles fall back to the role default so every user has a job title.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function applyJobTitles(array $attributes): array
    {
        $ar = trim((string) ($attributes['job_title_ar'] ?? ''));
        $en = trim((string) ($attributes['job_title_en'] ?? ''));

        if ($ar === '' && $en === '') {
            $defaults = User::defaultJobTitles($attributes['role'] ?? User::ROLE_EMPLOYEE);
            $attributes['job_title_ar'] = $defaults['ar'];
            $attributes['job_title_en'] = $defaults['en'];

            return $attributes;
        }

        $attributes['job_title_ar'] = $ar !== '' ? $ar : null;
        $attributes['job_title_en'] = $en !== '' ? $en : null;

        return $attributes;
    }

    /**
     * Profile payload; stores the personal photo on the `local` disk when present.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function profileAttributes(User $user, array $data, ?UploadedFile $photo): array
    {
        $profile = $data['profile'] ?? [];

        // Code is system-assigned on create and never overwritten from the form.
        unset($profile['employee_code']);

        if ($photo !== null) {
            $profile['personal_photo_path'] = $photo->storeAs(
                "employee-photos/{$user->id}",
                Str::random(40).'.'.$photo->extension(),
                'local',
            );
        }

        return $profile;
    }

    /**
     * Blank rows from the form are dropped; the rest is replaced wholesale.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncQualifications(User $user, array $rows): void
    {
        $user->qualifications()->delete();

        foreach ($rows as $row) {
            if ($this->rowIsBlank($row, ['degree', 'specialization', 'graduation_year'])) {
                continue;
            }

            $user->qualifications()->create($row);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function syncEmergencyContacts(User $user, array $rows): void
    {
        $user->emergencyContacts()->delete();

        foreach ($rows as $row) {
            if ($this->rowIsBlank($row, ['name', 'phone', 'relationship'])) {
                continue;
            }

            $user->emergencyContacts()->create($row);
        }
    }

    /**
     * A dynamic-form row is blank when every one of its fields is empty.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $fields
     */
    private function rowIsBlank(array $row, array $fields): bool
    {
        foreach ($fields as $field) {
            $value = $row[$field] ?? null;

            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncValuationModules(User $user, array $data, ?User $assignedBy = null): void
    {
        if ($user->isManager()) {
            return;
        }

        // Align with ValuationAssignmentPolicy: Manager-only (Assistants manage HR, not modules).
        if ($assignedBy === null || ! $assignedBy->isManager()) {
            return;
        }

        if (! array_key_exists('valuation_modules', $data)) {
            return;
        }

        $modules = $data['valuation_modules'] ?? [];
        $this->valuationAssignments->syncForUser(
            $assignedBy,
            $user,
            is_array($modules) ? $modules : [],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncGrossPages(User $user, array $data, ?User $assignedBy = null): void
    {
        if ($user->isManager()) {
            $this->grossPages->syncForUser($user, []);

            return;
        }

        // Mohra page ACL is Manager-only (same gate as valuation modules).
        if ($assignedBy === null || ! $assignedBy->isManager()) {
            return;
        }

        if (array_key_exists('gross_page_keys', $data)) {
            $keys = $data['gross_page_keys'] ?? [];
            $this->grossPages->syncForUser($user, is_array($keys) ? $keys : []);
            $this->ensureProductModuleWhenPagesAssigned($user, is_array($keys) ? $keys : [], $assignedBy);

            return;
        }

        // Create without checklist: apply role defaults for Sales/Accountant.
        if (! $user->wasRecentlyCreated) {
            return;
        }

        $this->grossPages->applyRoleDefaults($user);
        $this->ensureProductModuleWhenPagesAssigned(
            $user,
            $user->grossPages()->pluck('page_key')->all(),
            $assignedBy,
        );
    }

    /**
     * Mohra pages require Product module for portal + GrossAccess::mayPage.
     *
     * @param  list<string>  $keys
     */
    private function ensureProductModuleWhenPagesAssigned(User $user, array $keys, ?User $assignedBy = null): void
    {
        if ($keys === []) {
            return;
        }

        $exists = ValuationAssignment::query()
            ->where('user_id', $user->id)
            ->where('module', ValuationAssignment::MODULE_PRODUCT)
            ->exists();

        if ($exists) {
            return;
        }

        ValuationAssignment::query()->create([
            'user_id' => $user->id,
            'assigned_by' => $assignedBy?->id ?? $user->id,
            'module' => ValuationAssignment::MODULE_PRODUCT,
        ]);
    }
}
