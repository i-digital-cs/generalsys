<?php

namespace App\Http\Requests\Admin;

class StoreEmployeeRequest extends EmployeeRequest
{
    protected function passwordRules(): array
    {
        return ['required', 'string', 'min:8'];
    }
}
