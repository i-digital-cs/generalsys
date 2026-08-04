<?php

namespace App\Providers;

use App\Models\Advance;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Policies\AdvancePolicy;
use App\Policies\EmployeePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PenaltyRulePolicy;
use App\Policies\ShiftAssignmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, EmployeePolicy::class);
        Gate::policy(ShiftAssignment::class, ShiftAssignmentPolicy::class);
        Gate::policy(PenaltyRule::class, PenaltyRulePolicy::class);
        Gate::policy(PayrollRecord::class, PayrollPolicy::class);
        Gate::policy(Advance::class, AdvancePolicy::class);
    }
}
