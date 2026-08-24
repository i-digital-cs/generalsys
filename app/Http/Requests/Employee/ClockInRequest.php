<?php

namespace App\Http\Requests\Employee;

use App\Services\Attendance\AttendanceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClockInRequest extends FormRequest
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
        /** @var AttendanceService $attendance */
        $attendance = app(AttendanceService::class);
        $allowed = $attendance->availableShiftTypes($this->user());

        return array_merge([
            'shift_type' => ['required', 'string', Rule::in($allowed)],
        ], ClockPhotoRules::rules($this->user()), ClockLocationRules::rulesForShift($this->input('shift_type'), $this->user()));
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
