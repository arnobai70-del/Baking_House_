<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BusinessPortalController;
use App\Http\Controllers\OrganizationApplicationController;
use App\Http\Controllers\OrganizationContextController;
use App\Http\Controllers\ResellerPortalController;
use App\Http\Controllers\Storefront;
use Illuminate\Support\Facades\Route;

/*
| Storefront (public)
*/
Route::get('/', Storefront\HomeController::class)->name('home');
Route::get('/shop', [Storefront\ShopController::class, 'index'])->name('catalog.shop');
Route::get('/category/{slug}', [Storefront\ShopController::class, 'category'])->name('catalog.category');
Route::get('/search', [Storefront\ShopController::class, 'search'])->name('catalog.search');
Route::get('/product/{slug}', [Storefront\ProductController::class, 'show'])->name('catalog.product');
Route::get('/sitemap.xml', Storefront\SitemapController::class)->name('sitemap');
Route::get('/robots.txt', Storefront\RobotsController::class)->name('robots');

/*
| Authentication
*/
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');

    /* Staff MFA — reachable only with a pending (password-verified) sign-in in the session. */
    Route::get('/mfa/challenge', [MfaChallengeController::class, 'show'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store'])->middleware('throttle:mfa');
    Route::get('/mfa/setup', [MfaSetupController::class, 'show'])->name('mfa.setup');
    Route::post('/mfa/setup', [MfaSetupController::class, 'store'])->middleware('throttle:mfa');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', LogoutController::class)->name('logout');

    Route::get('/account', AccountController::class)->name('account');
    Route::post('/account/context', OrganizationContextController::class)->name('account.context');

    Route::middleware('can:'.StaffPermission::AccessAdmin->value)->group(function (): void {
        Route::get('/account/security', [SecurityController::class, 'show'])->name('account.security');
        Route::post('/account/security/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:mfa')->name('account.security.recovery-codes');
        Route::post('/account/security/reset-mfa', [SecurityController::class, 'reset'])
            ->middleware('throttle:mfa')->name('account.security.reset');
    });

    Route::get('/apply/{type}', [OrganizationApplicationController::class, 'create'])->name('organizations.apply');
    Route::post('/apply', [OrganizationApplicationController::class, 'store'])->name('organizations.apply.store');

    Route::get('/business/dashboard', BusinessPortalController::class)
        ->middleware('organization:business')
        ->name('business.dashboard');

    Route::get('/reseller/dashboard', ResellerPortalController::class)
        ->middleware('organization:reseller')
        ->name('reseller.dashboard');

    /*
    | Admin
    */
    Route::prefix('admin')->name('admin.')->middleware('can:'.StaffPermission::AccessAdmin->value)->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::patch('/organizations/{organization}/status', Admin\OrganizationApprovalController::class)->name('organizations.status');
        Route::put('/users/{user}/roles', Admin\UserRoleController::class)->name('users.roles');
        Route::post('/users/{user}/mfa-reset', Admin\UserMfaResetController::class)->middleware('throttle:mfa')->name('users.mfa-reset');
        Route::get('/audit', Admin\AuditLogController::class)
            ->middleware('can:'.StaffPermission::ViewAuditLog->value)
            ->name('audit');

        Route::middleware('can:'.StaffPermission::ManageCatalog->value)->group(function (): void {
            Route::resource('categories', Admin\CategoryController::class)->except(['show', 'destroy']);
            Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update']);
            Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
        });
    });
});
