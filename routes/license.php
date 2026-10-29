<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Nafiswatsiq\SubbasePayment\Http\Controllers\LicenseMarketplaceController;

// Real Fenix login/OIDC and organization selection belong to the host app.
Route::middleware(['web', 'auth', 'throttle:20,1'])
    ->prefix('subbase-payment/licenses')
    ->name('subbase-payment.')
    ->group(function (): void {
        Route::get('/offers', [LicenseMarketplaceController::class, 'offers'])->name('offers');
        Route::post('/providers', [LicenseMarketplaceController::class, 'apply'])->name('providers.apply');
        Route::get('/providers/prices', [LicenseMarketplaceController::class, 'wholesalePrices'])->name('providers.prices');
        Route::post('/orders', [LicenseMarketplaceController::class, 'buy'])->name('orders.buy');
        Route::get('/orders/{order}', [LicenseMarketplaceController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/keys', [LicenseMarketplaceController::class, 'reveal'])->name('orders.keys');
        Route::post('/keys/{key}/resale', [LicenseMarketplaceController::class, 'resale'])->name('keys.resale');
    });

Route::middleware(['web', 'auth', 'throttle:8,1'])
    ->prefix('subbase-payment/licenses')
    ->name('subbase-payment.licenses.')
    ->group(function (): void {
        Route::post('/redeem', [LicenseMarketplaceController::class, 'redeem'])->name('redeem');
    });
