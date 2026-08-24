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
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\TeamLeader\ApprovalController;
use App\Http\Controllers\TeamLeader\AttendanceHistoryController;
use App\Http\Controllers\TeamLeader\AttendanceReviewController;
use App\Http\Controllers\TeamLeader\EvaluationController;
use App\Http\Controllers\TeamLeader\ReportReviewController;
use App\Http\Controllers\TeamLeader\RestDayController;
use App\Http\Controllers\TeamLeader\ShiftAssignmentController;
use App\Http\Controllers\Valuation\ValuationAssignmentController;
use App\Http\Controllers\Valuation\ValuationSheetController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Auth (T-1.2)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::post('/impersonation/leave', [ImpersonationController::class, 'destroy'])
    ->middleware('auth')->name('impersonation.leave');

Route::middleware('auth')->group(function () {
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
    Route::get('/{module}/valuations/{sheet}', [ValuationSheetController::class, 'show'])
        ->whereIn('module', ['service', 'product'])
        ->name('valuation.sheets.show');

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

// Employee + Accountant self home (profile / salary / my advances)
Route::middleware(['auth', 'role:EMPLOYEE,ACCOUNTANT,ASSISTANT'])->group(function () {
    Route::get('/employee/dashboard', EmployeeDashboardController::class)->name('employee.dashboard');
    Route::get('/profile', [ProfileController::class, 'showSelf'])->name('employee.profile');
    Route::get('/employees/{user}/profile', [ProfileController::class, 'show'])->name('employee.profile.show');
    Route::get('/salary', [SalaryController::class, 'index'])->name('employee.salary');
    Route::get('/salary/{payroll}', [SalaryController::class, 'show'])->name('employee.salary.show');
    Route::get('/salary/{payroll}/export.pdf', [SalaryController::class, 'exportPdf'])->name('employee.salary.export.pdf');
    Route::get('/advances/my', [EmployeeAdvanceController::class, 'index'])->name('employee.advances');
});

// Clock — all staff roles including DRIVER (T-7.8)
Route::middleware(['auth', 'role:EMPLOYEE,TEAM_LEADER,MANAGER,ACCOUNTANT,ASSISTANT,DRIVER'])->group(function () {
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
Route::middleware(['auth', 'role:EMPLOYEE,TEAM_LEADER,MANAGER,ACCOUNTANT,ASSISTANT'])->group(function () {
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

    Route::get('/attendance', [AttendanceHistoryController::class, 'index'])->name('attendance');

    Route::get('/reports', [ReportReviewController::class, 'index'])->name('reports');
    Route::get('/reports/{attendance}', [ReportReviewController::class, 'show'])->name('reports.show');

    // Assistant: view approvals + finalize mid-shift gaps and overtime (no leave/vacation decide).
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::post('/approvals/gaps/{gap}/decide', [ApprovalController::class, 'decideGap'])
        ->name('approvals.gaps.decide');
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
