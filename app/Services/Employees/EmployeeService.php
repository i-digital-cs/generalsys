<?php

namespace App\Services\Employees;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Employee account + profile + contract + qualifications + contacts,
 * always written in ONE transaction (AGENTS.md §1).
 */
class EmployeeService
{
    /**
     * @param  array<string, mixed>  $data  validated payload (see StoreEmployeeRequest)
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            /** @var User $user */
            $user = User::create($this->userAttributes($data));

            $user->profile()->create($data['profile']);
            $user->contracts()->create($data['contract'] + ['is_active' => true]);

            $this->syncQualifications($user, $data['qualifications'] ?? []);
            $this->syncEmergencyContacts($user, $data['contacts'] ?? []);

            return $user;
        });
    }

    /**
     * @param  array<string, mixed>  $data  validated payload (see UpdateEmployeeRequest)
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $attributes = $this->userAttributes($data);

            if (empty($attributes['password'])) {
                unset($attributes['password']);
            }

            $user->update($attributes);

            $user->profile()->updateOrCreate(['user_id' => $user->id], $data['profile']);
            $user->contracts()->updateOrCreate(
                ['user_id' => $user->id, 'is_active' => true],
                $data['contract'] + ['is_active' => true],
            );

            $this->syncQualifications($user, $data['qualifications'] ?? []);
            $this->syncEmergencyContacts($user, $data['contacts'] ?? []);

            return $user->refresh();
        });
    }

    /**
     * Paginated, filtered employee list — aggregates/search run in SQL.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<User>
     */
    public function paginate(?string $search, ?string $status, int $perPage = 25)
    {
        return User::query()
            ->with('profile')
            ->whereIn('role', [User::ROLE_EMPLOYEE, User::ROLE_TEAM_LEADER])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name_ar', 'like', "%{$search}%")
                        ->orWhere('name_en', 'like', "%{$search}%")
                        ->orWhereHas('profile', fn ($profile) => $profile->where('employee_code', 'like', "%{$search}%"));
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
        return array_merge(
            Arr::only($data, [
                'name_ar', 'name_en', 'email', 'role', 'currency', 'language_preference',
                'team_leader_id', 'shift_type_default', 'dynamic_day_off', 'status',
                'base_salary', 'online_half_day_rate', 'online_full_day_rate', 'overtime_hourly_rate',
            ]),
            ['password' => $data['password'] ?? null],
        );
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
            if (empty($row['degree'])) {
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
            if (empty($row['name'])) {
                continue;
            }

            $user->emergencyContacts()->create($row);
        }
    }
}
