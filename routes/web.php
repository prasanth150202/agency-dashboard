<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EarningsController;
use App\Http\Controllers\Internal\AgencyShopifyWebhookController;
use App\Http\Controllers\Internal\ShopifyWebhookController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganisationController;
use App\Http\Controllers\PayoutController;
use App\Http\Controllers\PayoutSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Public\StoreConnectionSuccessController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StoreConnectionController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StoreModuleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

Route::middleware(['auth', 'set.organisation'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/stores', [StoreController::class, 'index'])->name('stores.index');
    Route::get('/stores/{store}', [StoreController::class, 'show'])->name('stores.show');
    Route::post('/stores/{store}/modules/{module}/toggle', [StoreModuleController::class, 'toggle'])->name('stores.modules.toggle');

    Route::post('/stores/connect/start', [StoreConnectionController::class, 'start'])->name('stores.connect.start');
    Route::get('/stores/connect/wait/{token}', [StoreConnectionController::class, 'wait'])->name('stores.connect.wait');
    Route::get('/stores/connect/status/{token}', [StoreConnectionController::class, 'status'])->name('stores.connect.status');
    Route::get('/stores/connect/install/{token}', [StoreConnectionController::class, 'install'])->name('stores.connect.install');
    Route::post('/stores/connect/cancel/{onboarding}', [StoreConnectionController::class, 'cancel'])->name('stores.connect.cancel');
    Route::post('/stores/{store}/authorize', [StoreConnectionController::class, 'authorize'])->name('stores.authorize');
    Route::post('/stores/{store}/activate', [StoreConnectionController::class, 'activate'])->name('stores.activate');
    Route::post('/stores/{store}/reconnect', [StoreConnectionController::class, 'reconnect'])->name('stores.reconnect');

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // This is the agency's "Commissions" page — kept on the existing
    // /earnings route/name to avoid a churny rename; only the sidebar
    // label and page content changed. /overview (a near-duplicate
    // summary) was merged into this page rather than kept alongside it.
    Route::get('/earnings', [EarningsController::class, 'index'])->name('earnings');
    Route::get('/earnings/export', [EarningsController::class, 'export'])->name('earnings.export');

    Route::get('/payouts', [PayoutController::class, 'index'])->name('payouts');
    Route::post('/payouts/request', [PayoutController::class, 'store'])->name('payouts.request');
    Route::get('/payouts/{payout}', [PayoutController::class, 'show'])->name('payouts.show');
    Route::post('/payouts/{payout}/cancel', [PayoutController::class, 'cancel'])->name('payouts.cancel');

    Route::get('/payout-settings', [PayoutSettingsController::class, 'index'])->name('payout-settings');
    Route::post('/payout-settings', [PayoutSettingsController::class, 'store'])->name('payout-settings.store');

    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');

    // URL/route names say "partners" (user-facing) — the controller/model
    // underneath stays Organisation, this app's own login/tenant table,
    // distinct from Partner (agencies), the real system-of-record model.
    Route::get('/partners', [OrganisationController::class, 'index'])->name('partners.index');
    Route::post('/partners', [OrganisationController::class, 'store'])->name('partners.store');
    Route::post('/partners/switch', [OrganisationController::class, 'switch'])->name('partners.switch');

    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Server-to-server only — called by the Cart Ninja / BRIX Shopify app's PHP
// backend (install_shop.php / uninstall_shop.php), never by a browser.
// Deliberately outside the auth/set.organisation group above; guarded
// instead by a shared secret header checked inside the controller.
Route::prefix('internal/shopify')->name('internal.shopify.')->group(function () {
    Route::post('/store-connected', [ShopifyWebhookController::class, 'storeConnected'])->name('store-connected');
    Route::post('/store-uninstalled', [ShopifyWebhookController::class, 'storeUninstalled'])->name('store-uninstalled');

    // brix_superadmin-backed counterpart of the two routes above — see
    // Internal\AgencyShopifyWebhookController's docblock.
    Route::post('/agency/store-installed', [AgencyShopifyWebhookController::class, 'storeInstalled'])->name('agency.store-installed');
    Route::post('/agency/store-uninstalled', [AgencyShopifyWebhookController::class, 'storeUninstalled'])->name('agency.store-uninstalled');

    // Merchant self-service counterparts — called by cartdrawerv2_ui's own
    // Node backend on behalf of a merchant clicking through inside the
    // Shopify-embedded Brix app, never directly by the merchant's browser.
    Route::get('/agency/store-status', [AgencyShopifyWebhookController::class, 'storeStatus'])->name('agency.store-status');
    Route::post('/agency/store-authorize', [AgencyShopifyWebhookController::class, 'storeAuthorize'])->name('agency.store-authorize');
    Route::post('/agency/store-activate', [AgencyShopifyWebhookController::class, 'storeActivate'])->name('agency.store-activate');
});

// Public, unauthenticated — the merchant landing here has no Agency
// Dashboard login. Protected by Laravel's signed-URL mechanism instead
// (same pattern as routes/auth.php's verify-email link): only a URL
// minted by AgencyShopifyWebhookController::storeActivate() is valid,
// and only for a short window.
Route::get('/connect/success/{store}', [StoreConnectionSuccessController::class, 'show'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('public.stores.connect.success');

require __DIR__.'/auth.php';
