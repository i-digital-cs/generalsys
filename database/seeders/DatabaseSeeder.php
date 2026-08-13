<?php

namespace Database\Seeders;

use App\Models\Contract;
use App\Models\EmployeeProfile;
use App\Models\OfficialHoliday;
use App\Models\RestDay;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PenaltyRuleSeeder::class);

        $this->seedEmployee(User::factory()->manager(), [
            'name_ar' => 'مدير النظام',
            'name_en' => 'System Admin',
            'email' => 'admin@hrpro.test',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => 'FULL_TIME',
        ]);

        $leader = $this->seedEmployee(User::factory()->teamLeader(), [
            'name_ar' => 'قائد الفريق',
            'name_en' => 'Team Leader',
            'email' => 'leader@hrpro.test',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => 'FULL_TIME',
        ]);

        $this->seedEmployee(User::factory()->accountant(), [
            'name_ar' => 'المحاسب',
            'name_en' => 'Accountant',
            'email' => 'accountant@hrpro.test',
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
            'shift_type_default' => 'FULL_TIME',
            'base_salary' => 7000,
            'online_half_day_rate' => 120,
            'online_full_day_rate' => 240,
            'overtime_hourly_rate' => 55,
            'team_leader_id' => null,
        ]);

        $radwa = $this->seedEmployee(User::factory()->employee(), [
            'name_ar' => 'رضوى',
            'name_en' => 'Radwa',
            'email' => 'radwa@hrpro.test',
            'currency' => 'EGP',
            'team_leader_id' => $leader->id,
            'base_salary' => 6000,
            'online_half_day_rate' => 100,
            'online_full_day_rate' => 200,
            'overtime_hourly_rate' => 50,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        $mona = $this->seedEmployee(User::factory()->employee(), [
            'name_ar' => 'منى',
            'name_en' => 'Mona',
            'email' => 'mona@hrpro.test',
            'currency' => 'AED',
            'team_leader_id' => $leader->id,
            'base_salary' => 8000,
            'online_half_day_rate' => 150,
            'online_full_day_rate' => 300,
            'overtime_hourly_rate' => 60,
            'work_start_time' => '10:00',
            'work_end_time' => '18:00',
        ]);

        // Sample date-based rest days (Fridays in Aug 2026; Mona also Saturdays)
        foreach (['2026-08-07', '2026-08-14', '2026-08-21', '2026-08-28'] as $friday) {
            RestDay::query()->create([
                'user_id' => $radwa->id,
                'date' => $friday,
                'created_by' => $leader->id,
            ]);
            RestDay::query()->create([
                'user_id' => $mona->id,
                'date' => $friday,
                'created_by' => $leader->id,
            ]);
        }
        foreach (['2026-08-08', '2026-08-15', '2026-08-22', '2026-08-29'] as $saturday) {
            RestDay::query()->create([
                'user_id' => $mona->id,
                'date' => $saturday,
                'created_by' => $leader->id,
            ]);
        }

        OfficialHoliday::insert([
            ['date' => '2026-01-07', 'name_ar' => 'عيد الميلاد المجيد', 'name_en' => 'Coptic Christmas', 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-01-25', 'name_ar' => 'عيد الشرطة', 'name_en' => 'Police Day', 'created_at' => now(), 'updated_at' => now()],
            ['date' => '2026-04-25', 'name_ar' => 'عيد تحرير سيناء', 'name_en' => 'Sinai Liberation Day', 'created_at' => now(), 'updated_at' => now()],
        ]);

        Setting::insert([
            ['key' => 'default_language', 'value' => 'ar', 'created_at' => now(), 'updated_at' => now()],
            ['key' => 'default_currency', 'value' => 'EGP', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Create a user (password: "password") with a profile and an active contract.
     */
    private function seedEmployee($factory, array $attributes): User
    {
        /** @var User $user */
        $user = $factory->create(array_merge(['password' => 'password'], $attributes));

        EmployeeProfile::factory()->create(['user_id' => $user->id]);
        Contract::factory()->create(['user_id' => $user->id]);

        return $user;
    }
}
