<?php

namespace App\Providers;

use App\Models\Advance;
use App\Models\AttendancePhoto;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Policies\AdvancePolicy;
use App\Policies\AttendancePhotoPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PenaltyRulePolicy;
use App\Policies\ShiftAssignmentPolicy;
use App\Services\Auth\ImpersonationService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as IlluminateView;

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
        Paginator::useBootstrapFive();

        Gate::policy(User::class, EmployeePolicy::class);
        Gate::policy(ShiftAssignment::class, ShiftAssignmentPolicy::class);
        Gate::policy(PenaltyRule::class, PenaltyRulePolicy::class);
        Gate::policy(PayrollRecord::class, PayrollPolicy::class);
        Gate::policy(Advance::class, AdvancePolicy::class);
        Gate::policy(AttendancePhoto::class, AttendancePhotoPolicy::class);

        View::composer('layouts.app', function (IlluminateView $view): void {
            $service = app(ImpersonationService::class);
            $view->with('impersonationBanner', $service->bannerState(auth()->user()));
        });
    }
}
