<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_EMPLOYEE,
            'currency' => 'EGP',
            'language_preference' => 'ar',
            'team_leader_id' => null,
            'base_salary' => 0,
            'online_half_day_rate' => 0,
            'online_full_day_rate' => 0,
            'overtime_hourly_rate' => 0,
            'shift_type_default' => 'FULL_TIME',
            'dynamic_day_off' => null,
            'status' => User::STATUS_ACTIVE,
            'remember_token' => Str::random(10),
        ];
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_MANAGER,
            'team_leader_id' => null,
        ]);
    }

    public function teamLeader(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_TEAM_LEADER,
            'team_leader_id' => null,
        ]);
    }

    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_EMPLOYEE,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }
}
