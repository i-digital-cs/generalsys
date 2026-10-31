<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * GH operational instants: store/read as UTC wall-clock strings.
 *
 * Independent of config('app.timezone') so Attendance/Payroll/Gross casting is untouched.
 * MySQL TIMESTAMP columns on this host use session SYSTEM=UTC, so the stored string is UTC.
 */
class GhUtcDateTime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        return Carbon::parse((string) $value, 'UTC')->utc();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->utc()->format('Y-m-d H:i:s');
        }

        if (is_string($value) || is_numeric($value)) {
            return Carbon::parse((string) $value)->utc()->format('Y-m-d H:i:s');
        }

        throw new InvalidArgumentException("Invalid datetime value for {$key}");
    }
}
