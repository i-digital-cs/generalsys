<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\EmployeeController;
use App\Http\Controllers\Admin\EmployeeDocumentController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\Employee\ProfileController;
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

// Admin / MANAGER only (T-1.4 – T-1.7)
Route::middleware(['auth', 'role:MANAGER'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');

    Route::resource('employees', EmployeeController::class)->except(['show', 'destroy']);
    Route::post('employees/{employee}/documents', [EmployeeDocumentController::class, 'store'])
        ->name('employees.documents.store');

    Route::get('/settings', [SettingController::class, 'edit'])->name('settings');
    Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
});

// Employee self profile (T-1.8) — own data only, enforced by EmployeePolicy
Route::get('/employees/{user?}/profile', [ProfileController::class, 'show'])
    ->middleware(['auth', 'role:EMPLOYEE'])->name('employee.profile');
