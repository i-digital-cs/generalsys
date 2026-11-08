<?php

namespace App\Providers;

use App\Models\Advance;
use App\Models\AttendancePhoto;
use App\Models\GeneralHouse\GhB2bContract;
use App\Models\GeneralHouse\GhCreditNote;
use App\Models\GeneralHouse\GhCustomer;
use App\Models\GeneralHouse\GhFinancialCorrection;
use App\Models\GeneralHouse\GhInvoice;
use App\Models\GeneralHouse\GhOrder;
use App\Models\GeneralHouse\GhPayment;
use App\Models\GeneralHouse\GhProduct;
use App\Models\GeneralHouse\GhProductOffering;
use App\Models\GeneralHouse\GhRefund;
use App\Models\GeneralHouse\GhServiceCategory;
use App\Models\GeneralHouse\GhTreatment;
use App\Models\GeneralHouse\GhZone;
use App\Models\Gross\GrossCustomer;
use App\Models\Gross\GrossExpenseType;
use App\Models\Gross\GrossItem;
use App\Models\Gross\GrossPurchaseInvoice;
use App\Models\Gross\GrossReceiptPurpose;
use App\Models\Gross\GrossSalesInvoice;
use App\Models\Gross\GrossShippingCompany;
use App\Models\Gross\GrossSupplier;
use App\Models\Gross\GrossTreasuryVoucher;
use App\Models\PayrollRecord;
use App\Models\PenaltyRule;
use App\Models\Permission;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Models\Vacation;
use App\Models\ValuationAssignment;
use App\Models\ValuationSheet;
use App\Observers\ShiftAssignmentPlannedHoursObserver;
use App\Observers\UserShiftProfileObserver;
use App\Policies\AdvancePolicy;
use App\Policies\AttendancePhotoPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\GeneralHouse\GhB2bContractPolicy;
use App\Policies\GeneralHouse\GhCreditNotePolicy;
use App\Policies\GeneralHouse\GhCustomerPolicy;
use App\Policies\GeneralHouse\GhFinancialCorrectionPolicy;
use App\Policies\GeneralHouse\GhInvoicePolicy;
use App\Policies\GeneralHouse\GhOrderPolicy;
use App\Policies\GeneralHouse\GhPaymentPolicy;
use App\Policies\GeneralHouse\GhProductOfferingPolicy;
use App\Policies\GeneralHouse\GhProductPolicy;
use App\Policies\GeneralHouse\GhRefundPolicy;
use App\Policies\GeneralHouse\GhServiceCategoryPolicy;
use App\Policies\GeneralHouse\GhTreatmentPolicy;
use App\Policies\GeneralHouse\GhZonePolicy;
use App\Policies\Gross\GrossCustomerPolicy;
use App\Policies\Gross\GrossExpenseTypePolicy;
use App\Policies\Gross\GrossItemPolicy;
use App\Policies\Gross\GrossPurchaseInvoicePolicy;
use App\Policies\Gross\GrossReceiptPurposePolicy;
use App\Policies\Gross\GrossSalesInvoicePolicy;
use App\Policies\Gross\GrossShippingCompanyPolicy;
use App\Policies\Gross\GrossSupplierPolicy;
use App\Policies\Gross\GrossTreasuryVoucherPolicy;
use App\Policies\PayrollPolicy;
use App\Policies\PenaltyRulePolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ShiftAssignmentPolicy;
use App\Policies\VacationPolicy;
use App\Policies\ValuationAssignmentPolicy;
use App\Policies\ValuationSheetPolicy;
use App\Services\Auth\ImpersonationService;
use App\Services\GeneralHouse\App\GhAppVersionPolicyService;
use App\Services\Gross\LowStockAlertService;
use App\Support\GnrlAppAccess;
use App\Support\GrossAccess;
use Illuminate\Database\Eloquent\Model;
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

        // Dev-only N+1 detection (AGENTS §4). Never enable in production.
        Model::preventLazyLoading($this->app->environment('local'));

        User::observe(UserShiftProfileObserver::class);
        ShiftAssignment::observe(ShiftAssignmentPlannedHoursObserver::class);

        Gate::policy(User::class, EmployeePolicy::class);
        Gate::policy(ShiftAssignment::class, ShiftAssignmentPolicy::class);
        Gate::policy(PenaltyRule::class, PenaltyRulePolicy::class);
        Gate::policy(PayrollRecord::class, PayrollPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(Vacation::class, VacationPolicy::class);
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
        Gate::policy(GrossReceiptPurpose::class, GrossReceiptPurposePolicy::class);
        Gate::policy(GhServiceCategory::class, GhServiceCategoryPolicy::class);
        Gate::policy(GhTreatment::class, GhTreatmentPolicy::class);
        Gate::policy(GhProduct::class, GhProductPolicy::class);
        Gate::policy(GhProductOffering::class, GhProductOfferingPolicy::class);
        Gate::policy(GhZone::class, GhZonePolicy::class);
        Gate::policy(GhCustomer::class, GhCustomerPolicy::class);
        Gate::policy(GhB2bContract::class, GhB2bContractPolicy::class);
        Gate::policy(GhOrder::class, GhOrderPolicy::class);
        Gate::policy(GhInvoice::class, GhInvoicePolicy::class);
        Gate::policy(GhPayment::class, GhPaymentPolicy::class);
        Gate::policy(GhCreditNote::class, GhCreditNotePolicy::class);
        Gate::policy(GhRefund::class, GhRefundPolicy::class);
        Gate::policy(GhFinancialCorrection::class, GhFinancialCorrectionPolicy::class);

        View::composer('layouts.app', function (IlluminateView $view): void {
            $service = app(ImpersonationService::class);
            $view->with('impersonationBanner', $service->bannerState(auth()->user()));
        });

        View::composer('layouts.partials.sidebar', function (IlluminateView $view): void {
            $user = auth()->user();
            $count = 0;

            if (GrossAccess::mayAccessGross($user)) {
                $count = app(LowStockAlertService::class)->count();
            }

            $gnrlDownloads = [
                'visible' => false,
                'android_url' => null,
                'ios_url' => null,
            ];

            if (GnrlAppAccess::mayDownload($user)) {
                $policies = app(GhAppVersionPolicyService::class);
                $androidReady = false;
                try {
                    $androidReady = $policies->androidDownloadAvailable();
                } catch (\Throwable) {
                    $androidReady = false;
                }

                $iosUrl = null;
                try {
                    $iosUrl = $policies->iosDownloadUrlForTrustedChannel();
                } catch (\Throwable) {
                    $iosUrl = null;
                }

                // Footer for every authenticated GeneralSys user (global sidebar).
                $gnrlDownloads = [
                    'visible' => true,
                    'android_url' => $androidReady ? route('app.download.file') : null,
                    'ios_url' => $iosUrl,
                ];
            }

            $view->with('grossLowStockCount', $count);
            $view->with('gnrlAppDownloads', $gnrlDownloads);
        });
    }
}
