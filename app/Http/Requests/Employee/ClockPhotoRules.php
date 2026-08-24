<?php

namespace App\Http\Requests\Employee;

use App\Models\User;

/**
 * Shared multi-image rules for clock-in / clock-out (T-6.6 / T-7.8).
 * Drivers: at least one photo; every photo requires a description.
 * Others: optional photos; descriptions optional.
 */
final class ClockPhotoRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(?User $user = null): array
    {
        $maxKb = max(1, (int) config('attendance.photos.max_kilobytes', 51200));
        $mimes = implode(',', config('attendance.photos.mimes', ['jpg', 'jpeg', 'png', 'webp']));

        if ($user?->requiresPunchPhotos()) {
            return [
                'photos' => ['required', 'array', 'min:1'],
                'photos.*' => ['required', 'file', 'mimes:'.$mimes, 'max:'.$maxKb],
                'photo_descriptions' => ['required', 'array', 'min:1'],
                'photo_descriptions.*' => ['required', 'string', 'min:1', 'max:500'],
            ];
        }

        return [
            'photos' => ['nullable', 'array'],
            'photos.*' => ['file', 'mimes:'.$mimes, 'max:'.$maxKb],
            'photo_descriptions' => ['nullable', 'array'],
            'photo_descriptions.*' => ['nullable', 'string', 'max:500'],
        ];
    }
}
