<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\RestDayController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Employee\ClockController;
use App\Http\Controllers\Employee\ProfileController;
use App\Http\Controllers\EmployeePhotoController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

// Auth (T-1.2)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')->name('logout');

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['ar', 'en'], true), 404);

    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale');

// Generic authenticated landing (TEAM_LEADER home until Phase 2 team dashboard)
Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

// Authorized document download: MANAGER or owning employee (T-1.6)
Route::get('/documents/{document}/download', [DocumentDownloadController::class, 'show'])
    ->middleware('auth')->name('documents.download');

// Employee personal photo: MANAGER or the employee themselves
Route::get('/employees/{user}/photo', [EmployeePhotoController::class, 'show'])
    ->middleware('auth')->name('employees.photo');

// Admin / MANAGER only (T-1.4 – T-1.7)
Route::middleware(['auth', 'role:MANAGER'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except(['show', 'destroy']);
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('employees.documents.store');
    Route::delete('employees/{employee}/documents/{document}', [EmployeeDocumentController::class, 'destroy'])
        ->name('employees.documents.destroy');
    Route::post('employees/{employee}/rest-days', [RestDayController::class, 'store'])
        ->name('employees.rest-days.store');
    Route::delete('employees/{employee}/rest-days/{restDay}', [RestDayController::class, 'destroy'])
        ->name('employees.rest-days.destroy');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

// Employee self (T-1.8 profile · T-2.3 clock)
Route::middleware(['auth', 'role:EMPLOYEE'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'showSelf'])->name('employee.profile');
    Route::get('/employees/{user}/profile', [ProfileController::class, 'show'])->name('employee.profile.show');

    Route::get('/clock', [ClockController::class, 'show'])->name('employee.clock');
    Route::post('/clock/in', [ClockController::class, 'clockIn'])->name('employee.clock.in');
    Route::post('/clock/out', [ClockController::class, 'clockOut'])->name('employee.clock.out');
});
