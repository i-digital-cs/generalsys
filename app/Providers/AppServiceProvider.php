<?php

namespace App\Providers;

use App\Models\Advance;
use App\Models\AttendancePhoto;
use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossSupplier;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\ValuationAssignment;
use App\Models\ValuationSheet;
use App\Policies\AdvancePolicy;
use App\Policies\AttendancePhotoPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\Gross\GrossCustomerPolicy;
use App\Policies\Gross\GrossExpenseTypePolicy;
use App\Policies\Gross\GrossItemPolicy;
use App\Policies\Gross\GrossPurchaseInvoicePolicy;
use App\Policies\Gross\GrossSalesInvoicePolicy;
use App\Policies\Gross\GrossShippingCompanyPolicy;
use App\Policies\Gross\GrossSupplierPolicy;
use App\Policies\Gross\GrossTreasuryVoucherPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PenaltyRulePolicy;
use App\Policies\ShiftAssignmentPolicy;
use App\Policies\ValuationAssignmentPolicy;
use App\Policies\ValuationSheetPolicy;
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
        Gate::policy(ValuationSheet::class, ValuationSheetPolicy::class);
        Gate::policy(ValuationAssignment::class, ValuationAssignmentPolicy::class);
        Gate::policy(GrossItem::class, GrossItemPolicy::class);
        Gate::policy(GrossShippingCompany::class, GrossShippingCompanyPolicy::class);
        Gate::policy(GrossCustomer::class, GrossCustomerPolicy::class);
        Gate::policy(GrossSupplier::class, GrossSupplierPolicy::class);
        Gate::policy(GrossSalesInvoice::class, GrossSalesInvoicePolicy::class);
        Gate::policy(GrossPurchaseInvoice::class, GrossPurchaseInvoicePolicy::class);
        Gate::policy(GrossTreasuryVoucher::class, GrossTreasuryVoucherPolicy::class);
        Gate::policy(GrossExpenseType::class, GrossExpenseTypePolicy::class);

        View::composer('layouts.app', function (IlluminateView $view): void {
            $service = app(ImpersonationService::class);
            $view->with('impersonationBanner', $service->bannerState(auth()->user()));
        });
    }
}
