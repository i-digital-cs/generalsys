<?php

namespace Database\Factories;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Permission> */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->employee(),
            'type' => Permission::TYPE_LEAVE,
            'request_date' => now()->toDateString(),
            'from_time' => '10:00',
            'to_time' => '12:00',
            'status' => Permission::STATUS_PENDING,
        ];
    }
}
