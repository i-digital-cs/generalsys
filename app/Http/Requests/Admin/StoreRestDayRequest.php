<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreRestDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isManager() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User $employee */
        $employee = $this->route('employee');

        return [
            'date' => [
                'required', 'date',
                // whereDate: the date cast may store "Y-m-d H:i:s" (sqlite), so a
                // plain Rule::unique string comparison can miss duplicates.
                function (string $attribute, mixed $value, \Closure $fail) use ($employee) {
                    if ($employee->restDays()->whereDate('date', $value)->exists()) {
                        $fail('validation.unique')->translate();
                    }
                },
            ],
        ];
    }
}
