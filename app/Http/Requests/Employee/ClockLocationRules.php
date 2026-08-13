<?php

namespace App\Http\Requests\Employee;

/**
 * Required browser GPS for clock-in / clock-out (T-7.2). No manual pick — values from device.
 */
final class ClockLocationRules
{
    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }
}
