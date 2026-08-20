<?php

namespace App\Http\Requests\Employee;

/**
 * Shared optional multi-image rules for clock-in / clock-out (T-6.6).
 * No count limit — only mime + per-file size.
 */
final class ClockPhotoRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        $maxKb = max(1, (int) config('attendance.photos.max_kilobytes', 51200));
        $mimes = implode(',', config('attendance.photos.mimes', ['jpg', 'jpeg', 'png', 'webp']));

        return [
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'photo_descriptions' => ['nullable', 'array'],
            'photo_descriptions.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
