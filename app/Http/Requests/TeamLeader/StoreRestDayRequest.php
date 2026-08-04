<?php

namespace App\Http\Requests\TeamLeader;

use App\Models\User;
use App\Services\Employees\RestDayService;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestDayRequest extends FormRequest
{
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
            'date' => ['required', 'date'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'date' => __('employees.rest_days.fields.date'),
        ];
    }
}
