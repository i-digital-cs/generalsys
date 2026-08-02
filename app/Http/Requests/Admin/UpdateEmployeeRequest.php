<?php

namespace App\Http\Requests\Admin;

class UpdateEmployeeRequest extends EmployeeRequest
{
    protected function passwordRules(): array
    {
        // Leave blank to keep the current password.
        return ['nullable', 'string', 'min:8'];
    }
}
