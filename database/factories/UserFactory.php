<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name_ar' => fake()->name(),
            'name_en' => fake()->name(),
            'job_title_ar' => 'موظف',
            'job_title_en' => 'Employee',
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_EMPLOYEE,
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'base_salary' => 0,
            'online_half_day_rate' => 0,
            'online_full_day_rate' => 0,
            'overtime_hourly_rate' => 0,
            'shift_type_default' => 'FULL_TIME',
            'work_start_time' => null,
            'work_end_time' => null,
            'status' => User::STATUS_ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_MANAGER,
            'job_title_ar' => 'مدير',
            'job_title_en' => 'Manager',
        ]);
    }

    public function teamLeader(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_TEAM_LEADER,
            'job_title_ar' => 'قائد فريق',
            'job_title_en' => 'Team Leader',
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_EMPLOYEE,
            'job_title_ar' => 'موظف',
            'job_title_en' => 'Employee',
        ]);
    }

    public function accountant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ACCOUNTANT,
            'job_title_ar' => 'محاسب',
            'job_title_en' => 'Accountant',
        ]);
    }

    public function assistant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ASSISTANT,
            'job_title_ar' => 'مساعد إداري',
            'job_title_en' => 'Admin Assistant',
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }
}
