<?php

namespace App\Support;

use App\Models\User;

/**
 * Filter Product sidebar items by Mohra page permissions.
 */
final class GrossMenu
{
    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function filterForUser(?User $user, array $items): array
    {
        if ($user === null) {
            return [];
        }

        $visible = [];
        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'heading') {
                $visible[] = $item;

                continue;
            }

            $page = $item['page'] ?? null;
            if ($page !== null && $page !== '' && ! GrossAccess::mayPage($user, (string) $page)) {
                continue;
            }

            $visible[] = $item;
        }

        return self::dropEmptyHeadings($visible);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private static function dropEmptyHeadings(array $items): array
    {
        $out = [];
        $pendingHeading = null;

        foreach ($items as $item) {
            if (($item['type'] ?? '') === 'heading') {
                $pendingHeading = $item;

                continue;
            }

            if ($pendingHeading !== null) {
                $out[] = $pendingHeading;
                $pendingHeading = null;
            }
            $out[] = $item;
        }

        return $out;
    }
}
