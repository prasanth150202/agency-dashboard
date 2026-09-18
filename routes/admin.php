<?php

use App\Http\Controllers\Admin\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PartnerController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StoreController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin routes — BRIX's own internal dashboard.
|--------------------------------------------------------------------------
|
| Entirely separate account system from the partner-facing app above
| (admin_users, guarded by the 'admin' auth guard) — never the same
| login as a partner's organisations/users.
*/

Route::get('admin/login', [AuthenticatedSessionController::class, 'create'])->name('admin.login');
Route::post('admin/login', [AuthenticatedSessionController::class, 'store'])->name('admin.login.store');

Route::middleware('admin.auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('partners', [PartnerController::class, 'index'])->name('partners.index');
    Route::get('partners/{partner}', [PartnerController::class, 'show'])->name('partners.show');
    Route::put('partners/{partner}', [PartnerController::class, 'update'])->middleware('admin.finance')->name('partners.update');

    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');
    Route::post('stores/{store}/recheck', [StoreController::class, 'recheck'])->name('stores.recheck');

    Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
    Route::get('payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
    Route::post('payouts/{payout}/approve', [PayoutController::class, 'approve'])->middleware('admin.finance')->name('payouts.approve');
    Route::post('payouts/{payout}/reject', [PayoutController::class, 'reject'])->middleware('admin.finance')->name('payouts.reject');
    Route::post('payouts/{payout}/mark-paid', [PayoutController::class, 'markPaid'])->middleware('admin.finance')->name('payouts.mark-paid');

    Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::put('settings', [SettingsController::class, 'update'])->middleware('admin.finance')->name('settings.update');
});
