<?php

namespace App\Services\Gross;

use App\Models\Gross\GrossUserPage;
use App\Models\User;
use App\Support\GrossAccess;
use Illuminate\Support\Facades\DB;

class GrossUserPageService
{
    /**
     * @return list<string>
     */
    public function allKeys(): array
    {
        return array_values(config('gross_pages.keys', []));
    }

    /**
     * @param  list<string>|null  $pageKeys
     */
    public function syncForUser(User $user, ?array $pageKeys): void
    {
        if ($user->isManager()) {
            GrossUserPage::query()->where('user_id', $user->id)->delete();
            GrossAccess::clearPageKeyCache($user);

            return;
        }

        $allowed = $this->allKeys();
        $keys = collect($pageKeys ?? [])
            ->map(fn ($k) => (string) $k)
            ->filter(fn (string $k) => in_array($k, $allowed, true))
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($user, $keys) {
            GrossUserPage::query()->where('user_id', $user->id)->delete();
            foreach ($keys as $key) {
                GrossUserPage::query()->create([
                    'user_id' => $user->id,
                    'page_key' => $key,
                ]);
            }
        });

        GrossAccess::clearPageKeyCache($user);
        $user->unsetRelation('grossPages');
    }

    /**
     * Apply role defaults when creating a user with no explicit page_keys.
     */
    public function applyRoleDefaults(User $user): void
    {
        if ($user->isManager()) {
            return;
        }

        $keys = match ($user->role) {
            User::ROLE_SALES => config('gross_pages.defaults_sales', []),
            User::ROLE_ACCOUNTANT => config('gross_pages.defaults_accountant', []),
            default => [],
        };

        if ($keys === []) {
            return;
        }

        $this->syncForUser($user, $keys);
    }
}
