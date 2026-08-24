<?php

namespace App\Http\Requests\Employee;

use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Clock-out with optional multi photos (T-6.6). GPS required only for FULL_TIME.
 * Drivers require ≥1 photo with a description each (T-7.8).
 */
class ClockOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->mayUseClock();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $open = app(AttendanceService::class)->openAttendance($this->user());

        return array_merge(
            ClockPhotoRules::rules($this->user()),
            ClockLocationRules::rulesForShift($open?->shift_type, $this->user()),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->user()?->requiresPunchPhotos()) {
                return;
            }

            $photos = $this->file('photos', []) ?? [];
            $descriptions = array_values(array_filter(
                array_map(
                    fn ($d) => is_string($d) ? trim($d) : '',
                    (array) $this->input('photo_descriptions', []),
                ),
                fn (string $d) => $d !== '',
            ));

            if (count($photos) > 0 && count($descriptions) < count($photos)) {
                $validator->errors()->add(
                    'photo_descriptions',
                    __('clock.photos.errors.description_required'),
                );
            }
        });
    }
}
