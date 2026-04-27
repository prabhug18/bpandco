<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SlipController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\IncentiveReportController;
use App\Http\Controllers\Admin\MetricController;
use App\Http\Controllers\Admin\ScoringTierController;
use App\Http\Controllers\Admin\HolidayController;
use App\Http\Controllers\Admin\IncentiveConfigController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\RoleController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::redirect('/register', '/login');

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // User Roles & Management
    Route::resource('users', UserController::class);

    // Phase 3: Slips Module
    Route::resource('slips', SlipController::class)->only(['index', 'store']);
    Route::get('slips/preview-points', [SlipController::class, 'previewPoints'])->name('slips.preview-points');

    // Phase 3: Attendance Module
    Route::resource('attendance', AttendanceController::class)->only(['index', 'store']);

    // Phase 4: Approval Queue (Supervisor + Admin only)
    Route::middleware(['can:approve slips'])->group(function () {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('approvals/slips/{slip}/approve', [ApprovalController::class, 'approveSlip'])->name('approvals.slips.approve');
        Route::patch('approvals/slips/{slip}/reject', [ApprovalController::class, 'rejectSlip'])->name('approvals.slips.reject');
        Route::post('approvals/slips/bulk/approve', [ApprovalController::class, 'bulkApproveSlips'])->name('approvals.slips.bulk-approve');
        Route::post('approvals/slips/bulk/reject', [ApprovalController::class, 'bulkRejectSlips'])->name('approvals.slips.bulk-reject');

        Route::patch('approvals/attendance/{attendance}/approve', [ApprovalController::class, 'approveAttendance'])->name('approvals.attendance.approve');
        Route::patch('approvals/attendance/{attendance}/reject', [ApprovalController::class, 'rejectAttendance'])->name('approvals.attendance.reject');
        Route::post('approvals/attendance/bulk/approve', [ApprovalController::class, 'bulkApproveAttendance'])->name('approvals.attendance.bulk-approve');
        Route::post('approvals/attendance/bulk/reject', [ApprovalController::class, 'bulkRejectAttendance'])->name('approvals.attendance.bulk-reject');
    });

    // Phase 6: Reports
    Route::get('reports/individual', [ReportController::class, 'individual'])->name('reports.individual');
    Route::get('reports/team', [ReportController::class, 'team'])->name('reports.team');

    // Phase 8.4: Incentive & Increment Reports (Admin/Supervisor)
    Route::middleware(['can:configure incentives'])->group(function () {
        Route::get('reports/incentives', [IncentiveReportController::class, 'incentives'])->name('reports.incentives');
        Route::patch('reports/incentives/{incentive}/paid', [IncentiveReportController::class, 'markPaid'])->name('reports.incentives.paid');
        Route::get('reports/increments', [IncentiveReportController::class, 'increments'])->name('reports.increments');
    });

    // Admin Backend Configuration
    Route::prefix('admin')->name('admin.')->middleware(['auth'])->group(function () {
        Route::resource('roles', RoleController::class)->middleware('can:manage roles');
        Route::resource('metrics', MetricController::class)->middleware('can:configure metrics');
        
        Route::get('scoring-tiers', [ScoringTierController::class, 'index'])->name('scoring-tiers.index')->middleware('can:configure scoring tiers');
        Route::post('scoring-tiers/{metric}/daily', [ScoringTierController::class, 'storeDaily'])->name('scoring-tiers.daily.store')->middleware('can:configure scoring tiers');
        Route::delete('scoring-tiers/daily/{dailyTier}', [ScoringTierController::class, 'destroyDaily'])->name('scoring-tiers.daily.destroy')->middleware('can:configure scoring tiers');
        Route::post('scoring-tiers/{metric}/period', [ScoringTierController::class, 'storePeriod'])->name('scoring-tiers.period.store')->middleware('can:configure scoring tiers');
        Route::delete('scoring-tiers/period/{periodTarget}', [ScoringTierController::class, 'destroyPeriod'])->name('scoring-tiers.period.destroy')->middleware('can:configure scoring tiers');

        Route::resource('holidays', HolidayController::class)->middleware('can:configure holidays');
        Route::resource('incentives', IncentiveConfigController::class)->middleware('can:configure incentives');
        Route::post('increments', [IncentiveConfigController::class, 'storeIncrement'])->name('increments.store')->middleware('can:configure incentives');
        Route::delete('increments/{increment}', [IncentiveConfigController::class, 'destroyIncrement'])->name('increments.destroy')->middleware('can:configure incentives');

        Route::get('settings', [SettingController::class, 'index'])->name('settings.index')->middleware('can:manage system settings');
        Route::post('settings', [SettingController::class, 'update'])->name('settings.update')->middleware('can:manage system settings');
    });
});

require __DIR__.'/auth.php';
