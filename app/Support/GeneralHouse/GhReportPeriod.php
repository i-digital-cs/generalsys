<?php

namespace App\Support\GeneralHouse;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Dubai business-calendar period → UTC half-open interval [startUtc, endUtc).
 */
final class GhReportPeriod
{
    public const PRESET_TODAY = 'today';

    public const PRESET_YESTERDAY = 'yesterday';

    public const PRESET_LAST_7 = 'last_7';

    public const PRESET_LAST_30 = 'last_30';

    public const PRESET_CUSTOM = 'custom';

    /** @var list<string> */
    public const PRESETS = [
        self::PRESET_TODAY,
        self::PRESET_YESTERDAY,
        self::PRESET_LAST_7,
        self::PRESET_LAST_30,
        self::PRESET_CUSTOM,
    ];

    public function __construct(
        public readonly string $preset,
        public readonly Carbon $startUtc,
        public readonly Carbon $endUtc,
        public readonly string $fromDate,
        public readonly string $toDate,
        public readonly string $timezone,
    ) {}

    /**
     * @param  array{preset?: string|null, from?: string|null, to?: string|null}  $input
     */
    public static function fromInput(array $input, ?string $timezone = null): self
    {
        $tz = $timezone ?: (string) config('general_house.timezone', 'Asia/Dubai');
        $preset = (string) ($input['preset'] ?? self::PRESET_LAST_30);
        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Invalid report preset.');
        }

        $now = Carbon::now($tz);

        if ($preset === self::PRESET_TODAY) {
            $from = $now->toDateString();
            $to = $from;
        } elseif ($preset === self::PRESET_YESTERDAY) {
            $from = $now->copy()->subDay()->toDateString();
            $to = $from;
        } elseif ($preset === self::PRESET_LAST_7) {
            $to = $now->toDateString();
            $from = $now->copy()->subDays(6)->toDateString();
        } elseif ($preset === self::PRESET_LAST_30) {
            $to = $now->toDateString();
            $from = $now->copy()->subDays(29)->toDateString();
        } else {
            $from = (string) ($input['from'] ?? '');
            $to = (string) ($input['to'] ?? '');
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                throw new InvalidArgumentException('Custom range requires from/to dates.');
            }
            if ($from > $to) {
                throw new InvalidArgumentException('from must be on or before to.');
            }
            // Cap custom span at 366 days to protect shared hosting.
            $span = Carbon::parse($from, $tz)->diffInDays(Carbon::parse($to, $tz));
            if ($span > 366) {
                throw new InvalidArgumentException('Custom range cannot exceed 366 days.');
            }
        }

        $startUtc = Carbon::parse($from.' 00:00:00', $tz)->utc();
        $endUtc = Carbon::parse($to.' 00:00:00', $tz)->addDay()->utc();

        return new self($preset, $startUtc, $endUtc, $from, $to, $tz);
    }

    public function containsUtc(?Carbon $instant): bool
    {
        if ($instant === null) {
            return false;
        }
        $u = $instant->copy()->utc();

        return $u->gte($this->startUtc) && $u->lt($this->endUtc);
    }
}
