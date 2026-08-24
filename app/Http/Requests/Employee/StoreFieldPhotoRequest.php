<?php

namespace App\Http\Requests\Employee;

use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Driver mid-day field uploads during an open shift (T-7.10 / R6b).
 * Requires ≥1 photo + title each + GPS (applies to the whole batch).
 */
class StoreFieldPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->requiresPunchPhotos()
            && app(AttendanceService::class)->openAttendance($user) !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            ClockPhotoRules::rules($this->user()),
            ClockLocationRules::rules($this->user()),
        );
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->user()?->requiresPunchPhotos()) {
                return;
            }

            $photos = array_values(array_filter(
                $this->file('photos', []) ?? [],
                fn ($file) => $file !== null,
            ));
            $descriptions = array_values(array_map(
                fn ($d) => is_string($d) ? trim($d) : '',
                (array) $this->input('photo_descriptions', []),
            ));

            if ($photos === []) {
                return;
            }

            if (count($descriptions) < count($photos)) {
                $validator->errors()->add(
                    'photo_descriptions',
                    __('clock.photos.errors.description_required'),
                );

                return;
            }

            foreach ($photos as $index => $file) {
                if (($descriptions[$index] ?? '') === '') {
                    $validator->errors()->add(
                        'photo_descriptions.'.$index,
                        __('clock.photos.errors.description_required'),
                    );
                }
            }
        });
    }
}
