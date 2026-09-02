<?php

namespace App\Http\Requests\Employee;

use App\Models\Vacation;
use App\Services\Vacations\VacationBalanceService;
use App\Services\Vacations\VacationRequestService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVacationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (
            $user->isEmployee() || $user->isSales() || $user->isTeamLeader() || $user->isManager() || $user->isAccountant() || $user->isAssistant()
        );
    }

    protected function prepareForValidation(): void
    {
        $type = (string) $this->input('type', '');

        if (VacationBalanceService::usesBalance($type)) {
            return;
        }

        if ($this->filled('start_date')) {
            $this->merge(['end_date' => $this->input('start_date')]);
        }

        if (! $this->filled('days_requested')) {
            $this->merge(['days_requested' => 1]);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $type = (string) $this->input('type', '');

        if (VacationBalanceService::usesBalance($type)) {
            return [
                'type' => ['required', 'string', Rule::in([
                    Vacation::TYPE_ANNUAL,
                    Vacation::TYPE_SICK,
                ])],
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            ];
        }

        return [
            'type' => ['required', 'string', Rule::in([Vacation::TYPE_EMERGENCY])],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'same:start_date'],
            'days_requested' => ['required', 'numeric', 'min:0.5', 'max:1'],
            'replace_rest_day_id' => [
                'required',
                'integer',
                Rule::exists('rest_days', 'id')->where(fn ($q) => $q->where('user_id', $this->user()->id)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = (string) $this->input('type', '');

            if (! VacationBalanceService::usesBalance($type)) {
                return;
            }

            $start = (string) $this->input('start_date', '');
            $end = (string) $this->input('end_date', '');
            if ($start === '' || $end === '') {
                return;
            }

            $days = app(VacationRequestService::class)->suggestedDays($start, $end);
            if ($days < 1) {
                $validator->errors()->add('end_date', __('vacations.errors.invalid_date_range'));
            }

            if ($days > 90) {
                $validator->errors()->add('end_date', __('vacations.errors.range_too_long', ['max' => 90]));
            }
        });
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'type' => __('vacations.fields.type'),
            'start_date' => __('vacations.fields.start_date'),
            'end_date' => __('vacations.fields.end_date'),
            'days_requested' => __('vacations.fields.days_requested'),
            'replace_rest_day_id' => __('vacations.fields.replace_rest_day'),
        ];
    }
}
