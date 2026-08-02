<?php

namespace App\Services\Employees;

use App\Models\RestDay;
use App\Models\User;

/**
 * Rest-day management — monthly quota marked per date (schema doc §20).
 */
class RestDayService
{
    public function add(User $employee, string $date, User $creator): RestDay
    {
        return $employee->restDays()->create([
            'date' => $date,
            'created_by' => $creator->id,
        ]);
    }

    public function remove(RestDay $restDay): void
    {
        $restDay->delete();
    }
}
