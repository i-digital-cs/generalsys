<?php

namespace Tests\Support;

use App\Models\User;
use App\Services\Gross\GrossUserPageService;

trait AssignsGrossPages
{
    /**
     * @param  list<string>|null  $keys  null = role defaults (sales/accountant) or empty
     */
    protected function assignGrossPages(User $user, ?array $keys = null): void
    {
        $service = app(GrossUserPageService::class);
        if ($keys === null) {
            $service->applyRoleDefaults($user);

            return;
        }

        $service->syncForUser($user, $keys);
    }
}
