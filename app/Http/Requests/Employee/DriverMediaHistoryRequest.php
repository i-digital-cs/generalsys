<?php

namespace App\Http\Requests\Employee;

use App\Services\Attendance\DriverMediaHistoryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters for the driver's own media history list.
 */
class DriverMediaHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isDriver() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'from' => $this->input('from') ?: now()->startOfMonth()->toDateString(),
            'to' => $this->input('to') ?: now()->toDateString(),
            'kind' => $this->input('kind') ?: 'all',
            'location' => $this->input('location') ?: 'all',
            'q' => $this->filled('q') ? trim((string) $this->input('q')) : null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'kind' => ['required', Rule::in(DriverMediaHistoryService::KINDS)],
            'location' => ['required', Rule::in(DriverMediaHistoryService::LOCATION)],
            'q' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $from = $this->date('from');
            $to = $this->date('to');
            if ($from === null || $to === null) {
                return;
            }
            if ($from->diffInDays($to) > DriverMediaHistoryService::MAX_RANGE_DAYS) {
                $validator->errors()->add('to', __('media.history.errors.range'));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'from' => __('media.history.from'),
            'to' => __('media.history.to'),
            'kind' => __('media.history.kind'),
            'location' => __('media.history.location'),
            'q' => __('media.history.search'),
        ];
    }
}
