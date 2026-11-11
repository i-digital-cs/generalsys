<?php

namespace App\Support\Ui;

use App\Models\User;
use App\Services\Admin\SettingService;
use Illuminate\Support\Facades\Session;

/**
 * Global per-user ERP display mode (Classic tables vs Modern cards).
 *
 * Default: classic/legacy. One preference applies to all eligible pages.
 * Persistence: session + settings key/value (no new schema).
 * Legacy per-page keys `ui.list_view.u{id}` are left unused (not deleted).
 */
final class ListViewMode
{
    public const LEGACY = 'legacy';

    public const CARDS = 'cards';

    /** @deprecated Kept for call-site compatibility; global mode ignores page keys. */
    public const PAGES = [];

    public static function isValidMode(string $mode): bool
    {
        return in_array($mode, [self::LEGACY, self::CARDS], true);
    }

    /** Current global mode for the user (default classic). */
    public static function current(?User $user): string
    {
        if ($user === null) {
            return self::LEGACY;
        }

        $sessionKey = self::sessionKey($user);
        $fromSession = Session::get($sessionKey);
        if (is_string($fromSession) && self::isValidMode($fromSession)) {
            return $fromSession;
        }

        $raw = app(SettingService::class)->get(self::settingKey($user));
        $mode = is_string($raw) && self::isValidMode($raw) ? $raw : self::LEGACY;
        Session::put($sessionKey, $mode);

        return $mode;
    }

    /**
     * @param  string|null  $page  Ignored — retained so existing wrappers keep compiling.
     */
    public static function for(?User $user, ?string $page = null): string
    {
        return self::current($user);
    }

    /**
     * @param  string|null  $page  Ignored — retained for BC.
     */
    public static function isCards(?User $user, ?string $page = null): bool
    {
        return self::current($user) === self::CARDS;
    }

    /**
     * Persist global mode.
     *
     * @param  string|null  $pageOrMode  If $mode is null, treated as mode (BC with put($user, $mode)).
     */
    public static function put(User $user, string $pageOrMode, ?string $mode = null): void
    {
        $resolved = $mode ?? $pageOrMode;
        if (! self::isValidMode($resolved)) {
            return;
        }

        Session::put(self::sessionKey($user), $resolved);
        app(SettingService::class)->set(self::settingKey($user), $resolved);
    }

    /** @deprecated Pages are no longer validated; always true for non-empty strings. */
    public static function isValidPage(string $page): bool
    {
        return $page !== '';
    }

    private static function sessionKey(User $user): string
    {
        return 'ui.display_mode.'.$user->id;
    }

    private static function settingKey(User $user): string
    {
        return 'ui.display_mode.u'.$user->id;
    }
}
