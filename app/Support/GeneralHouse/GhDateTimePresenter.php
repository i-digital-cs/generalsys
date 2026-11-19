<?php

namespace App\Support\GeneralHouse;

use DateTimeInterface;
use Illuminate\Support\Carbon;

/**
 * GH-only Dubai wall-clock presentation (12-hour + UAE badge copy).
 *
 * Does not change config('app.timezone'), storage, or non-GH modules.
 * Use only for timestamps verified as UTC operational instants (GhUtcDateTime)
 * or inputs already parsed in config('general_house.timezone').
 */
final class GhDateTimePresenter
{
    public const MODE_DATETIME = 'datetime';

    public const MODE_TIME = 'time';

    public const MODE_DATE = 'date';

    public const MODE_DATETIME_SHORT = 'datetime_short';

    /** Business zone for GH ops display/parse — never app.timezone. */
    public static function timezone(): string
    {
        return (string) config('general_house.timezone', 'Asia/Dubai');
    }

    public static function isDubaiBusinessZone(?string $timezone): bool
    {
        $tz = trim((string) $timezone);

        return $tz === '' || strcasecmp($tz, self::timezone()) === 0 || strcasecmp($tz, 'Asia/Dubai') === 0;
    }

    /**
     * Format a verified instant in Asia/Dubai, 12-hour clock.
     * Returns null when $at is null (caller chooses empty placeholder).
     */
    public static function format(DateTimeInterface|string|null $at, string $mode = self::MODE_DATETIME): ?string
    {
        $local = self::toDubai($at);
        if ($local === null) {
            return null;
        }

        return match ($mode) {
            self::MODE_DATE => $local->format('Y-m-d'),
            self::MODE_TIME => self::formatTime12($local),
            self::MODE_DATETIME_SHORT => $local->format('d/m').' '.self::formatTime12($local),
            default => $local->translatedFormat('d M Y').' '.self::formatTime12($local),
        };
    }

    /**
     * Board/card bundle: short date, full datetime, time-only — all Dubai 12h.
     *
     * @return array{short: string, full: string, time: string}
     */
    public static function parts(DateTimeInterface|string|null $at, string $empty = '—'): array
    {
        $local = self::toDubai($at);
        if ($local === null) {
            return ['short' => $empty, 'full' => $empty, 'time' => $empty];
        }

        return [
            'short' => $local->format('d/m'),
            'full' => $local->translatedFormat('d M Y').' '.self::formatTime12($local),
            'time' => self::formatTime12($local),
        ];
    }

    /** Localized “🇦🇪 UAE” / “🇦🇪 الإمارات” badge text. */
    public static function uaeBadge(): string
    {
        return (string) __('gh.time.uae_badge');
    }

    /** Non-blocking notice for pickup/delivery/reschedule inputs. */
    public static function selectionNotice(): string
    {
        return (string) __('gh.time.selection_notice', ['tz' => self::timezone()]);
    }

    public static function toDubai(DateTimeInterface|string|null $at): ?Carbon
    {
        if ($at === null || $at === '') {
            return null;
        }

        $carbon = $at instanceof Carbon
            ? $at->copy()
            : Carbon::parse($at instanceof DateTimeInterface ? $at : (string) $at);

        return $carbon->timezone(self::timezone());
    }

    private static function formatTime12(Carbon $local): string
    {
        $clock = $local->format('g:i');
        $hour = (int) $local->format('G');
        $meridiem = app()->getLocale() === 'ar'
            ? ($hour < 12 ? 'ص' : 'م')
            : ($hour < 12 ? 'AM' : 'PM');

        return $clock.' '.$meridiem;
    }
}
