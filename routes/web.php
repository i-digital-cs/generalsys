<?php

use App\Http\Controllers\Admin\AdvanceController as AdminAdvanceController;
use App\Http\Controllers\Admin\AnnualReportController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\ImpersonationController;
use App\Http\Controllers\Admin\PayrollController;
use App\Http\Controllers\Admin\PenaltyRuleController;
use App\Http\Controllers\Admin\RestDayController as AdminRestDayController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\AttendancePhotoController;
use App\Http\Controllers\Auth\AccountInactiveController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Employee\AdvanceController as EmployeeAdvanceController;
use App\Http\Controllers\Employee\ClockController;
use App\Http\Controllers\Employee\DailyReportController;
use App\Http\Controllers\Employee\DashboardController as EmployeeDashboardController;
use App\Http\Controllers\Employee\DriverMediaController;
use App\Http\Controllers\Employee\EvaluationController as EmployeeEvaluationController;
use App\Http\Controllers\Employee\ProfileController;
use App\Http\Controllers\Employee\RequestController;
use App\Http\Controllers\Employee\SalaryController;
use App\Http\Controllers\EmployeePhotoController;
use App\Http\Controllers\Gross\CategoryController as GrossCategoryController;
use App\Http\Controllers\Gross\CustomerController as GrossCustomerController;
use App\Http\Controllers\Gross\ExpenseTypeController as GrossExpenseTypeController;
use App\Http\Controllers\Gross\ItemAttributeController as GrossItemAttributeController;
use App\Http\Controllers\Gross\ItemController as GrossItemController;
use App\Http\Controllers\Gross\ItemImageController as GrossItemImageController;
use App\Http\Controllers\Gross\PurchaseInvoiceController as GrossPurchaseInvoiceController;
use App\Http\Controllers\Gross\ReceiptPurposeController as GrossReceiptPurposeController;
use App\Http\Controllers\Gross\SalesInvoiceController as GrossSalesInvoiceController;
use App\Http\Controllers\Gross\SalesPersonStatsController as GrossSalesPersonStatsController;
use App\Http\Controllers\Gross\ShippingCompanyController as GrossShippingCompanyController;
use App\Http\Controllers\Gross\StockController as GrossStockController;
use App\Http\Controllers\Gross\SupplierController as GrossSupplierController;
use App\Http\Controllers\Gross\TreasuryController as GrossTreasuryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProductExpense\ProductExpenseController;
use App\Http\Controllers\TeamLeader\ApprovalController;
use App\Http\Controllers\TeamLeader\AttendanceHistoryController;
use App\Http\Controllers\TeamLeader\AttendanceReviewController;
use App\Http\Controllers\TeamLeader\EvaluationController;
use App\Http\Controllers\TeamLeader\ReportReviewController;
use App\Http\Controllers\TeamLeader\RestDayController;
use App\Http\Controllers\TeamLeader\ShiftAssignmentController;
use App\Http\Controllers\TeamLeader\VacationCreditController;
use App\Http\Controllers\Valuation\ValuationAssignmentController;
use App\Http\Controllers\Valuation\ValuationSheetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Auth (T-1.2)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::get('/csrf-token', [AuthenticatedSessionController::class, 'csrfToken'])
    ->middleware('web')
    ->name('csrf.token');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::post('/impersonation/leave', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')->name('impersonation.leave');

Route::middleware('auth')->group(function () {
    Route::get('/account/inactive', AccountInactiveController::class)->name('account.inactive');

    Route::get('/home', [PortalController::class, 'home'])->name('portal.home');
    Route::get('/hr', [PortalController::class, 'enterHr'])->name('portal.hr');
    Route::get('/service', [PortalController::class, 'service'])->name('portal.service');
    Route::get('/product', [PortalController::class, 'product'])->name('portal.product');
    Route::redirect('/production', '/product');

    Route::get('/valuation/assignments', [ValuationAssignmentController::class, 'index'])->name('valuation.assignments.index');
    Route::put('/valuation/assignments/{user}', [ValuationAssignmentController::class, 'update'])->name('valuation.assignments.update');

    Route::get('/{module}/valuations', [ValuationSheetController::class, 'index'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.index');
    Route::get('/{module}/valuations/form', [ValuationSheetController::class, 'form'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.form');
    Route::post('/{module}/valuations', [ValuationSheetController::class, 'store'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.store');
    Route::get('/{module}/valuations/{sheet}/export.pdf', [ValuationSheetController::class, 'exportPdf'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.export.pdf');
    Route::delete('/{module}/valuations/{sheet}', [ValuationSheetController::class, 'destroy'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.destroy');
    Route::get('/{module}/valuations/{sheet}', [ValuationSheetController::class, 'show'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.show');

    Route::prefix('product/expenses')->name('product.expenses.')->group(function () {
        Route::get('/{expenseType}', [ProductExpenseController::class, 'show'])->name('show');
        Route::put('/{expenseType}/budget', [ProductExpenseController::class, 'upsertBudget'])->name('budget');
        Route::post('/{expenseType}/payments', [ProductExpenseController::class, 'storePayment'])->name('payments.store');
    });

    Route::prefix('product/gross')->name('gross.')->group(function () {
        Route::get('/items', [GrossItemController::class, 'index'])->name('items.index');
        Route::get('/items/search', [GrossItemController::class, 'search'])->name('items.search');
        Route::get('/items/create', [GrossItemController::class, 'create'])->name('items.create');
        Route::post('/items', [GrossItemController::class, 'store'])->name('items.store');
        Route::get('/items/template', [GrossItemController::class, 'template'])->name('items.template');
        Route::post('/items/import', [GrossItemController::class, 'import'])->name('items.import');
        Route::get('/items/{item}', [GrossItemController::class, 'show'])->name('items.show');
        Route::get('/items/{item}/edit', [GrossItemController::class, 'edit'])->name('items.edit');
        Route::put('/items/{item}', [GrossItemController::class, 'update'])->name('items.update');
        Route::get('/items/{item}/images/{image}', [GrossItemImageController::class, 'show'])->name('items.images.show');

        Route::get('/categories', [GrossCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [GrossCategoryController::class, 'store'])->name('categories.store');
        Route::get('/categories/template', [GrossCategoryController::class, 'template'])->name('categories.template');
        Route::post('/categories/import', [GrossCategoryController::class, 'import'])->name('categories.import');
        Route::put('/categories/{category}', [GrossCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [GrossCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/attributes', [GrossItemAttributeController::class, 'index'])->name('attributes.index');
        Route::post('/attributes', [GrossItemAttributeController::class, 'store'])->name('attributes.store');
        Route::get('/attributes/template', [GrossItemAttributeController::class, 'template'])->name('attributes.template');
        Route::post('/attributes/import', [GrossItemAttributeController::class, 'import'])->name('attributes.import');
        Route::put('/attributes/{attribute}', [GrossItemAttributeController::class, 'update'])->name('attributes.update');
        Route::delete('/attributes/{attribute}', [GrossItemAttributeController::class, 'destroy'])->name('attributes.destroy');

        Route::get('/stock', [GrossStockController::class, 'index'])->name('stock.index');
        Route::get('/stock/movements', [GrossStockController::class, 'movements'])->name('stock.movements');
        Route::post('/stock/adjust', [GrossStockController::class, 'adjust'])->name('stock.adjust');

        Route::get('/shipping', [GrossShippingCompanyController::class, 'index'])->name('shipping.index');
        Route::get('/shipping/template', [GrossShippingCompanyController::class, 'template'])->name('shipping.template');
        Route::post('/shipping/import', [GrossShippingCompanyController::class, 'import'])->name('shipping.import');
        Route::get('/shipping/create', [GrossShippingCompanyController::class, 'create'])->name('shipping.create');
        Route::post('/shipping', [GrossShippingCompanyController::class, 'store'])->name('shipping.store');
        Route::get('/shipping/{shipping}/returns', [GrossShippingCompanyController::class, 'returnsReport'])->name('shipping.returns');
        Route::get('/shipping/{shipping}/statement', [GrossShippingCompanyController::class, 'statement'])->name('shipping.statement');
        Route::get('/shipping/{shipping}', [GrossShippingCompanyController::class, 'show'])->name('shipping.show');
        Route::get('/shipping/{shipping}/edit', [GrossShippingCompanyController::class, 'edit'])->name('shipping.edit');
        Route::put('/shipping/{shipping}', [GrossShippingCompanyController::class, 'update'])->name('shipping.update');
        Route::delete('/shipping/{shipping}', [GrossShippingCompanyController::class, 'destroy'])->name('shipping.destroy');

        Route::get('/customers', [GrossCustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/search', [GrossCustomerController::class, 'search'])->name('customers.search');
        Route::get('/customers/template', [GrossCustomerController::class, 'template'])->name('customers.template');
        Route::post('/customers/import', [GrossCustomerController::class, 'import'])->name('customers.import');
        Route::get('/customers/create', [GrossCustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [GrossCustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [GrossCustomerController::class, 'show'])->name('customers.show');
        Route::get('/customers/{customer}/edit', [GrossCustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/customers/{customer}', [GrossCustomerController::class, 'update'])->name('customers.update');
        Route::delete('/customers/{customer}', [GrossCustomerController::class, 'destroy'])->name('customers.destroy');

        Route::get('/suppliers', [GrossSupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/template', [GrossSupplierController::class, 'template'])->name('suppliers.template');
        Route::post('/suppliers/import', [GrossSupplierController::class, 'import'])->name('suppliers.import');
        Route::get('/suppliers/create', [GrossSupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/suppliers', [GrossSupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/suppliers/{supplier}', [GrossSupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/suppliers/{supplier}/statement', [GrossSupplierController::class, 'statement'])->name('suppliers.statement');
        Route::get('/suppliers/{supplier}/edit', [GrossSupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/suppliers/{supplier}', [GrossSupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/suppliers/{supplier}', [GrossSupplierController::class, 'destroy'])->name('suppliers.destroy');

        Route::get('/sales', [GrossSalesInvoiceController::class, 'index'])->name('sales.index');
        Route::get('/sales/collections', [GrossSalesInvoiceController::class, 'collections'])->name('sales.collections');
        Route::get('/sales/fulfillment/{queue}', [GrossSalesInvoiceController::class, 'fulfillmentQueue'])
            ->whereIn('queue', ['preparing', 'ready', 'with_courier', 'delivered', 'returned'])
            ->name('sales.fulfillment');
        Route::get('/sales/stats', [GrossSalesPersonStatsController::class, 'index'])->name('sales.stats');
        Route::get('/sales/stats/unassigned', [GrossSalesPersonStatsController::class, 'unassigned'])->name('sales.stats.unassigned');
        Route::get('/sales/stats/{user}', [GrossSalesPersonStatsController::class, 'show'])->name('sales.stats.show');
        Route::get('/sales/template', [GrossSalesInvoiceController::class, 'template'])->name('sales.template');
        Route::post('/sales/import', [GrossSalesInvoiceController::class, 'import'])->name('sales.import');
        Route::get('/sales/create', [GrossSalesInvoiceController::class, 'create'])->name('sales.create');
        Route::get('/sales/cashier', [GrossSalesInvoiceController::class, 'cashier'])->name('sales.cashier');
        Route::get('/sales/cashier/categories', [GrossSalesInvoiceController::class, 'cashierCategories'])->name('sales.cashier.categories');
        Route::get('/sales/cashier/subcategories', [GrossSalesInvoiceController::class, 'cashierSubcategories'])->name('sales.cashier.subcategories');
        Route::get('/sales/cashier/products', [GrossSalesInvoiceController::class, 'cashierProducts'])->name('sales.cashier.products');
        Route::get('/sales/cashier/variants', [GrossSalesInvoiceController::class, 'cashierVariants'])->name('sales.cashier.variants');
        Route::post('/sales', [GrossSalesInvoiceController::class, 'store'])->name('sales.store');
        Route::get('/sales/{invoice}', [GrossSalesInvoiceController::class, 'show'])->name('sales.show');
        Route::get('/sales/{invoice}/pdf', [GrossSalesInvoiceController::class, 'pdf'])->name('sales.pdf');
        Route::get('/sales/{invoice}/excel', [GrossSalesInvoiceController::class, 'excel'])->name('sales.excel');
        Route::get('/sales/{invoice}/edit', [GrossSalesInvoiceController::class, 'edit'])->name('sales.edit');
        Route::put('/sales/{invoice}', [GrossSalesInvoiceController::class, 'update'])->name('sales.update');
        Route::delete('/sales/{invoice}', [GrossSalesInvoiceController::class, 'destroy'])->name('sales.destroy');
        Route::post('/sales/{invoice}/confirm', [GrossSalesInvoiceController::class, 'confirm'])->name('sales.confirm');
        Route::post('/sales/{invoice}/packing-image', [GrossSalesInvoiceController::class, 'storePackingImage'])->name('sales.packing-image.store');
        Route::get('/sales/{invoice}/packing-image', [GrossSalesInvoiceController::class, 'packingImage'])->name('sales.packing-image.show');
        Route::post('/sales/{invoice}/media', [GrossSalesInvoiceController::class, 'storeMedia'])->name('sales.media.store');
        Route::get('/sales/{invoice}/media/{media}', [GrossSalesInvoiceController::class, 'showMedia'])->name('sales.media.show');
        Route::delete('/sales/{invoice}/media/{media}', [GrossSalesInvoiceController::class, 'destroyMedia'])->name('sales.media.destroy');
        Route::post('/sales/{invoice}/mark-ready', [GrossSalesInvoiceController::class, 'markReadyInWarehouse'])->name('sales.mark-ready');
        Route::post('/sales/{invoice}/hand-to-courier', [GrossSalesInvoiceController::class, 'handToCourier'])->name('sales.hand-to-courier');
        Route::post('/sales/{invoice}/cancel', [GrossSalesInvoiceController::class, 'cancel'])->name('sales.cancel');
        Route::post('/sales/{invoice}/refuse', [GrossSalesInvoiceController::class, 'refuse'])->name('sales.refuse');
        Route::post('/sales/{invoice}/receive-returned', [GrossSalesInvoiceController::class, 'receiveReturnedPieces'])->name('sales.receive-returned');
        Route::post('/sales/{invoice}/receive-returned-line', [GrossSalesInvoiceController::class, 'receiveReturnedLine'])->name('sales.receive-returned-line');
        Route::post('/sales/{invoice}/receive-refused', [GrossSalesInvoiceController::class, 'receiveRefused'])->name('sales.receive-refused');
        Route::post('/sales/{invoice}/return', [GrossSalesInvoiceController::class, 'returnLine'])->name('sales.return');
        Route::post('/sales/{invoice}/loss', [GrossSalesInvoiceController::class, 'lossLine'])->name('sales.loss');
        Route::post('/sales/{invoice}/collect-cod', [GrossSalesInvoiceController::class, 'collectCod'])->name('sales.collect-cod');
        Route::post('/sales/{invoice}/deliver', [GrossSalesInvoiceController::class, 'markDelivered'])->name('sales.deliver');

        Route::get('/purchases', [GrossPurchaseInvoiceController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/template', [GrossPurchaseInvoiceController::class, 'template'])->name('purchases.template');
        Route::post('/purchases/import', [GrossPurchaseInvoiceController::class, 'import'])->name('purchases.import');
        Route::get('/purchases/create', [GrossPurchaseInvoiceController::class, 'create'])->name('purchases.create');
        Route::post('/purchases', [GrossPurchaseInvoiceController::class, 'store'])->name('purchases.store');
        Route::get('/purchases/{purchase}', [GrossPurchaseInvoiceController::class, 'show'])->name('purchases.show');
        Route::get('/purchases/{purchase}/pdf', [GrossPurchaseInvoiceController::class, 'pdf'])->name('purchases.pdf');
        Route::get('/purchases/{purchase}/excel', [GrossPurchaseInvoiceController::class, 'excel'])->name('purchases.excel');
        Route::get('/purchases/{purchase}/edit', [GrossPurchaseInvoiceController::class, 'edit'])->name('purchases.edit');
        Route::put('/purchases/{purchase}', [GrossPurchaseInvoiceController::class, 'update'])->name('purchases.update');
        Route::delete('/purchases/{purchase}', [GrossPurchaseInvoiceController::class, 'destroy'])->name('purchases.destroy');
        Route::post('/purchases/{purchase}/confirm', [GrossPurchaseInvoiceController::class, 'confirm'])->name('purchases.confirm');
        Route::post('/purchases/{purchase}/cancel', [GrossPurchaseInvoiceController::class, 'cancel'])->name('purchases.cancel');
        Route::post('/purchases/{purchase}/return', [GrossPurchaseInvoiceController::class, 'returnLines'])->name('purchases.return');

        Route::get('/treasury', [GrossTreasuryController::class, 'index'])->name('treasury.index');
        Route::get('/treasury/create', [GrossTreasuryController::class, 'create'])->name('treasury.create');
        Route::post('/treasury', [GrossTreasuryController::class, 'store'])->name('treasury.store');
        Route::get('/treasury/{voucher}', [GrossTreasuryController::class, 'show'])->name('treasury.show');
        Route::get('/treasury/{voucher}/attachments/{attachment}', [GrossTreasuryController::class, 'downloadAttachment'])
            ->name('treasury.attachments.download');

        Route::get('/expense-types', [GrossExpenseTypeController::class, 'index'])->name('expense-types.index');
        Route::post('/expense-types', [GrossExpenseTypeController::class, 'store'])->name('expense-types.store');
        Route::put('/expense-types/{expenseType}', [GrossExpenseTypeController::class, 'update'])->name('expense-types.update');
        Route::delete('/expense-types/{expenseType}', [GrossExpenseTypeController::class, 'destroy'])->name('expense-types.destroy');

        Route::get('/receipt-purposes', [GrossReceiptPurposeController::class, 'index'])->name('receipt-purposes.index');
        Route::post('/receipt-purposes', [GrossReceiptPurposeController::class, 'store'])->name('receipt-purposes.store');
        Route::put('/receipt-purposes/{receiptPurpose}', [GrossReceiptPurposeController::class, 'update'])->name('receipt-purposes.update');
        Route::delete('/receipt-purposes/{receiptPurpose}', [GrossReceiptPurposeController::class, 'destroy'])->name('receipt-purposes.destroy');
    });

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale');

// Generic authenticated landing (TEAM_LEADER home until Phase 2 team dashboard)
Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

// Alias so TL sidebar `team-leader.dashboard` always resolves
Route::get('/team-leader/dashboard', DashboardController::class)
    ->middleware(['auth', 'role:TEAM_LEADER,MANAGER'])
    ->name('team-leader.dashboard');

// Authorized document download: MANAGER or owning employee (T-1.6)
Route::get('/documents/{document}/download', [DocumentDownloadController::class, 'show'])
    ->middleware('auth')->name('documents.download');

// Employee personal photo: MANAGER or the employee themselves
Route::get('/employees/{user}/photo', [EmployeePhotoController::class, 'show'])
    ->middleware('auth')->name('employees.photo');

// Punch photos: owner · team TL · Manager (T-6.6)
Route::get('/attendance-photos/{photo}', [AttendancePhotoController::class, 'show'])
    ->middleware('auth')->name('attendance.photos.show');

// Admin operations — Manager + Assistant (employee CRUD includes salary/rates; no payroll)
Route::middleware(['auth', 'role:MANAGER,ASSISTANT'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except(['show', 'destroy']);
    Route::post('employees/{employee}/rest-days', [AdminRestDayController::class, 'store'])
        ->name('employees.rest-days.store');
    Route::delete('employees/{employee}/rest-days/{restDay}', [AdminRestDayController::class, 'destroy'])
        ->name('employees.rest-days.destroy');
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('employees.documents.store');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
        ->name('employees.documents.destroy');
});

// Admin / MANAGER only — money, settings, penalties
Route::middleware(['auth', 'role:MANAGER'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/impersonation/enable', [ImpersonationController::class, 'enable'])
        ->middleware('throttle:5,1')
        ->name('settings.impersonation.enable');
    Route::post('/settings/impersonation/disable', [ImpersonationController::class, 'disable'])
        ->middleware('throttle:10,1')
        ->name('settings.impersonation.disable');
    Route::post('/employees/{employee}/impersonate', [ImpersonationController::class, 'store'])
        ->name('employees.impersonate');

    Route::get('/config/penalties', [PenaltyRuleController::class, 'index'])->name('config.penalties');
    Route::get('/config/penalties/create', [PenaltyRuleController::class, 'create'])->name('config.penalties.create');
    Route::post('/config/penalties', [PenaltyRuleController::class, 'store'])->name('config.penalties.store');
    Route::get('/config/penalties/{penalty_rule}/edit', [PenaltyRuleController::class, 'edit'])->name('config.penalties.edit');
    Route::put('/config/penalties/{penalty_rule}', [PenaltyRuleController::class, 'update'])->name('config.penalties.update');

    Route::put('/payroll/{payroll}/approve', [PayrollController::class, 'approve'])->name('payroll.approve');

    Route::get('/reports/annual', [AnnualReportController::class, 'index'])->name('reports.annual');
});

// Payroll + advances — Manager and Accountant (approve stays Manager-only above)
Route::middleware(['auth', 'role:MANAGER,ACCOUNTANT'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('/payroll/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
    Route::get('/payroll/export.xlsx', [PayrollController::class, 'queueExcelExport'])->name('payroll.export.xlsx');
    Route::get('/payroll/export/download', [PayrollController::class, 'downloadExcel'])->name('payroll.export.download');
    Route::get('/payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::put('/payroll/{payroll}', [PayrollController::class, 'update'])->name('payroll.update');
    Route::post('/payroll/{payroll}/refresh-attendance', [PayrollController::class, 'refreshAttendance'])
        ->name('payroll.refresh-attendance');
    Route::post('/payroll/{payroll}/apply-suggested-late', [PayrollController::class, 'applySuggestedLate'])
        ->name('payroll.apply-suggested-late');
    Route::post('/payroll/{payroll}/adjustments', [PayrollController::class, 'storeAdjustment'])->name('payroll.adjustments.store');
    Route::delete('/payroll/{payroll}/adjustments/{adjustment}', [PayrollController::class, 'destroyAdjustment'])
        ->name('payroll.adjustments.destroy');
    Route::get('/payroll/{payroll}/export.pdf', [PayrollController::class, 'exportPdf'])->name('payroll.export.pdf');
    Route::get('/payroll/{payroll}/export-review.pdf', [PayrollController::class, 'exportReviewPdf'])
        ->name('payroll.export.review.pdf');

    Route::get('/advances', [AdminAdvanceController::class, 'index'])->name('advances.index');
    Route::get('/advances/create', [AdminAdvanceController::class, 'create'])->name('advances.create');
    Route::post('/advances', [AdminAdvanceController::class, 'store'])->name('advances.store');
    Route::get('/advances/{advance}/edit', [AdminAdvanceController::class, 'edit'])->name('advances.edit');
    Route::put('/advances/{advance}', [AdminAdvanceController::class, 'update'])->name('advances.update');
});

// Employee + Accountant + Sales self home (profile / salary / my advances)
Route::middleware(['auth', 'role:EMPLOYEE,ACCOUNTANT,ASSISTANT,SALES'])->group(function () {
    Route::get('/employee/dashboard', EmployeeDashboardController::class)->name('employee.dashboard');
    Route::get('/profile', [ProfileController::class, 'showSelf'])->name('employee.profile');
    Route::get('/employees/{user}/profile', [ProfileController::class, 'show'])->name('employee.profile.show');
    Route::get('/salary', [SalaryController::class, 'index'])->name('employee.salary');
    Route::get('/salary/{payroll}', [SalaryController::class, 'show'])->name('employee.salary.show');
    Route::get('/salary/{payroll}/export.pdf', [SalaryController::class, 'exportPdf'])->name('employee.salary.export.pdf');
    Route::get('/advances/my', [EmployeeAdvanceController::class, 'index'])->name('employee.advances');
});

// Clock — all staff roles including DRIVER + SALES (T-7.8)
Route::middleware(['auth', 'role:EMPLOYEE,TEAM_LEADER,MANAGER,ACCOUNTANT,ASSISTANT,DRIVER,SALES'])->group(function () {
    Route::get('/clock', [ClockController::class, 'show'])->name('employee.clock');
    Route::post('/clock/in', [ClockController::class, 'clockIn'])->name('employee.clock.in');
    Route::post('/clock/out', [ClockController::class, 'clockOut'])->name('employee.clock.out');
    Route::post('/clock/field-photos', [ClockController::class, 'storeFieldPhotos'])
        ->name('employee.clock.field-photos');

    Route::get('/report', [DailyReportController::class, 'show'])->name('employee.report');
    Route::post('/report', [DailyReportController::class, 'store'])->name('employee.report.store');
});

// Driver media history (own field + punch photos)
Route::middleware(['auth', 'role:DRIVER'])->group(function () {
    Route::get('/media', [DriverMediaController::class, 'index'])->name('employee.media');
});

// Self-service: my requests, evaluation — not DRIVER yet
Route::middleware(['auth', 'role:EMPLOYEE,TEAM_LEADER,MANAGER,ACCOUNTANT,ASSISTANT,SALES'])->group(function () {
    Route::get('/requests', [RequestController::class, 'index'])->name('employee.requests');
    Route::post('/requests/permissions', [RequestController::class, 'storePermission'])
        ->name('employee.requests.permissions.store');
    Route::post('/requests/permissions/{permission}/force', [RequestController::class, 'forcePermission'])
        ->name('employee.requests.permissions.force');
    Route::post('/requests/vacations', [RequestController::class, 'storeVacation'])
        ->name('employee.requests.vacations.store');
    Route::post('/requests/vacations/{vacation}/force', [RequestController::class, 'forceVacation'])
        ->name('employee.requests.vacations.force');

    Route::get('/evaluation', [EmployeeEvaluationController::class, 'show'])->name('employee.evaluation');
});

// Ops roster + report follow — TL (team) / Manager / Assistant (company-wide)
Route::middleware(['auth', 'role:TEAM_LEADER,MANAGER,ASSISTANT'])->prefix('team-leader')->name('team-leader.')->group(function () {
    Route::get('/shifts', [ShiftAssignmentController::class, 'index'])->name('shifts');
    Route::post('/shifts', [ShiftAssignmentController::class, 'store'])->name('shifts.store');
    Route::get('/shifts/{shiftAssignment}/edit', [ShiftAssignmentController::class, 'edit'])->name('shifts.edit');
    Route::put('/shifts/{shiftAssignment}', [ShiftAssignmentController::class, 'update'])->name('shifts.update');
    Route::post('/shifts/{shiftAssignment}/cancel', [ShiftAssignmentController::class, 'cancel'])->name('shifts.cancel');

    Route::get('/rest-days', [RestDayController::class, 'index'])->name('rest-days');
    Route::post('/rest-days/{employee}', [RestDayController::class, 'store'])->name('rest-days.store');
    Route::delete('/rest-days/{employee}/{restDay}', [RestDayController::class, 'destroy'])->name('rest-days.destroy');

    Route::get('/vacation-credit', [VacationCreditController::class, 'index'])->name('vacation-credit');
    Route::post('/vacation-credit/{employee}', [VacationCreditController::class, 'store'])->name('vacation-credit.store');
    Route::get('/vacation-credit/{employee}/history', [VacationCreditController::class, 'history'])->name('vacation-credit.history');

    Route::get('/attendance', [AttendanceHistoryController::class, 'index'])->name('attendance');

    Route::get('/reports', [ReportReviewController::class, 'index'])->name('reports');
    Route::get('/reports/{attendance}', [ReportReviewController::class, 'show'])->name('reports.show');

    // Assistant: view approvals + finalize mid-shift gaps and overtime (no leave/vacation decide).
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/gaps/{gap}/decide', [ApprovalController::class, 'decideGap'])
        ->name('approvals.gaps.decide');
    Route::post('/attendance/{attendance}/force-clock-out', [AttendanceReviewController::class, 'forceClockOut'])
        ->name('attendance.force-clock-out');
    Route::post('/attendance/{attendance}/overtime/admin', [AttendanceReviewController::class, 'adminDecideOvertime'])
        ->name('attendance.overtime.admin');
});

// Decisions stay TL + Manager (Assistant is view-only on reports; gaps decide above)
Route::middleware(['auth', 'role:TEAM_LEADER,MANAGER'])->prefix('team-leader')->name('team-leader.')->group(function () {
    Route::post('/reports/{attendance}/respond', [ReportReviewController::class, 'respond'])->name('reports.respond');
    Route::put('/reports/{attendance}/scores', [ReportReviewController::class, 'scores'])->name('reports.scores');

    Route::get('/evaluations', [EvaluationController::class, 'index'])->name('evaluations');
    Route::get('/evaluations/{employee}', [EvaluationController::class, 'show'])->name('evaluations.show');
    Route::post('/evaluations/{employee}', [EvaluationController::class, 'store'])->name('evaluations.store');

    Route::post('/approvals/permissions/{permission}/approve', [ApprovalController::class, 'approve'])
        ->name('approvals.permissions.approve');
    Route::post('/approvals/permissions/{permission}/reject', [ApprovalController::class, 'reject'])
        ->name('approvals.permissions.reject');
    Route::post('/approvals/permissions/{permission}/force', [ApprovalController::class, 'force'])
        ->name('approvals.permissions.force');
    Route::post('/approvals/vacations/{vacation}/approve', [ApprovalController::class, 'approveVacation'])
        ->name('approvals.vacations.approve');
    Route::post('/approvals/vacations/{vacation}/reject', [ApprovalController::class, 'rejectVacation'])
        ->name('approvals.vacations.reject');
    Route::post('/approvals/vacations/{vacation}/force', [ApprovalController::class, 'forceVacation'])
        ->name('approvals.vacations.force');
    Route::post('/approvals/gaps/{gap}/recommend', [ApprovalController::class, 'recommendGap'])
        ->name('approvals.gaps.recommend');

    Route::post('/attendance/{attendance}/time', [AttendanceReviewController::class, 'updateTime'])
        ->name('attendance.time');
    Route::post('/attendance/{attendance}/overtime/tl', [AttendanceReviewController::class, 'tlRecommendOvertime'])
        ->name('attendance.overtime.tl');
});
