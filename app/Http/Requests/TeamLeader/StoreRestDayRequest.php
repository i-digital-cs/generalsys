<?php

namespace App\Http\Requests\TeamLeader;

use App\Models\User;
use App\Services\Employees\RestDayService;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestDayRequest extends FormRequest
{
    /** Max separate dates in one submit. */
    public const MAX_DATES = 31;

    public function authorize(): bool
    {
        /** @var User $employee */
        $employee = $this->route('employee');

        return $this->user() !== null
            && app(RestDayService::class)->actorMayManage($this->user(), $employee);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dates' => ['required', 'array', 'min:1', 'max:'.self::MAX_DATES],
            'dates.*' => ['required', 'date', 'distinct'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'dates' => __('employees.rest_days.fields.dates'),
            'dates.*' => __('employees.rest_days.fields.date'),
        ];
    }
}
