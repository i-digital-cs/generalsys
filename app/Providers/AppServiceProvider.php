<?php

namespace App\Providers;

use App\Models\ShiftAssignment;
use App\Models\User;
use App\Policies\EmployeePolicy;
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
    }
}
