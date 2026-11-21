<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\Account;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MfaChallengeController;
use App\Http\Controllers\Auth\MfaSetupController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\OrganizationApplicationController;
use App\Http\Controllers\OrganizationContextController;
use App\Http\Controllers\Portal;
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
Route::get('/combos', [Storefront\BundleController::class, 'index'])->name('bundles.index');
Route::get('/combo/{slug}', [Storefront\BundleController::class, 'show'])->name('bundles.show');
Route::post('/language', Storefront\LanguageController::class)->name('language');

/* Owner-supplied information pages and contact / support (messages go to the CRM queue). */
Route::get('/page/{slug}', Storefront\PageController::class)->name('pages.show');
Route::get('/contact', [Storefront\ContactController::class, 'show'])->name('contact');
Route::post('/contact', [Storefront\ContactController::class, 'store'])->middleware('throttle:quotations')->name('contact.store');

/* B2B landing pages and the single RFQ entry point (product page, /business, business portal). */
Route::get('/business', Storefront\PortalLandingController::class)->defaults('type', 'business')->name('business.landing');
Route::get('/reseller', Storefront\PortalLandingController::class)->defaults('type', 'reseller')->name('reseller.landing');
Route::post('/quotations', Storefront\QuotationRequestController::class)->middleware('throttle:quotations')->name('quotations.store');

/*
| Cart, checkout, orders (guests and customers)
*/
Route::get('/cart', [Storefront\CartController::class, 'show'])->name('cart.show');
Route::post('/cart/items', [Storefront\CartController::class, 'addVariant'])->middleware('throttle:checkout')->name('cart.items.store');
Route::post('/cart/bundles', [Storefront\CartController::class, 'addBundle'])->middleware('throttle:checkout')->name('cart.bundles.store');
Route::patch('/cart/items/{item}', [Storefront\CartController::class, 'update'])->name('cart.items.update');
Route::delete('/cart/items/{item}', [Storefront\CartController::class, 'remove'])->name('cart.items.destroy');
Route::post('/cart/coupon', [Storefront\CartController::class, 'applyCoupon'])->name('cart.coupon');

Route::get('/checkout', [Storefront\CheckoutController::class, 'show'])->name('checkout.show');
Route::post('/checkout/review', [Storefront\CheckoutController::class, 'review'])->middleware('throttle:checkout')->name('checkout.review.store');
Route::get('/checkout/review', [Storefront\CheckoutController::class, 'showReview'])->name('checkout.review');
Route::post('/checkout/place', [Storefront\CheckoutController::class, 'place'])->middleware('throttle:checkout')->name('checkout.place');

Route::get('/orders/{order}', [Storefront\OrderController::class, 'show'])->name('orders.show');
Route::post('/orders/{order}/pay', [Storefront\OrderController::class, 'pay'])->middleware('throttle:checkout')->name('orders.pay');
Route::post('/orders/{order}/cancel', [Storefront\OrderController::class, 'cancel'])->middleware('throttle:checkout')->name('orders.cancel');
Route::get('/order/track', [Storefront\OrderController::class, 'track'])->middleware('throttle:checkout')->name('orders.track');

/* TEST MODE payment simulator (signed URLs; disabled unless PAYMENTS_MODE=test). */
Route::get('/payments/test/{reference}', [Storefront\TestPaymentController::class, 'show'])->middleware('signed')->name('payments.test.show');
Route::post('/payments/test/{reference}', [Storefront\TestPaymentController::class, 'complete'])->middleware('signed')->name('payments.test.complete');

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
    Route::get('/account/addresses', [Account\AddressController::class, 'index'])->name('account.addresses');
    Route::post('/account/addresses', [Account\AddressController::class, 'store'])->name('account.addresses.store');
    Route::put('/account/addresses/{address}', [Account\AddressController::class, 'update'])->name('account.addresses.update');
    Route::delete('/account/addresses/{address}', [Account\AddressController::class, 'destroy'])->name('account.addresses.destroy');
    Route::get('/account/profile', [ProfileController::class, 'edit'])->name('account.profile');
    Route::put('/account/profile', [ProfileController::class, 'update'])->name('account.profile.update');
    Route::put('/account/password', [ProfileController::class, 'password'])->middleware('throttle:login')->name('account.password');

    Route::middleware('can:'.StaffPermission::AccessAdmin->value)->group(function (): void {
        Route::get('/account/security', [SecurityController::class, 'show'])->name('account.security');
        Route::post('/account/security/recovery-codes', [SecurityController::class, 'regenerateRecoveryCodes'])
            ->middleware('throttle:mfa')->name('account.security.recovery-codes');
        Route::post('/account/security/reset-mfa', [SecurityController::class, 'reset'])
            ->middleware('throttle:mfa')->name('account.security.reset');
    });

    Route::get('/apply/{type}', [OrganizationApplicationController::class, 'create'])->name('organizations.apply');
    Route::post('/apply', [OrganizationApplicationController::class, 'store'])->name('organizations.apply.store');

    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->withoutMiddleware('auth')->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'accept'])->middleware('throttle:mfa')->name('invitations.accept');

    /*
    | Business & reseller portals. Same building blocks, separate contexts: the organization
    | always comes from an approved membership of THAT type (EnsureOrganizationAccess).
    */
    foreach (['business', 'reseller'] as $portal) {
        Route::prefix($portal)->name($portal.'.')->middleware('organization:'.$portal)->group(function () use ($portal): void {
            Route::get('/dashboard', Portal\DashboardController::class)->name('dashboard');
            Route::get('/orders', [Portal\OrderController::class, 'index'])->name('orders');
            Route::post('/orders/{order}/reorder', [Portal\OrderController::class, 'reorder'])->middleware('throttle:checkout')->name('orders.reorder');
            Route::get('/orders/{order}/invoice', [Portal\OrderController::class, 'invoice'])->name('orders.invoice');
            Route::get('/bulk', [Portal\BulkOrderController::class, 'show'])->name('bulk');
            Route::post('/bulk', [Portal\BulkOrderController::class, 'submit'])->middleware('throttle:checkout')->name('bulk.submit');
            Route::get('/prices', [Portal\PriceController::class, 'index'])->name('prices');
            Route::get('/prices/export', [Portal\PriceController::class, 'export'])->name('prices.export');
            Route::get('/statements', Portal\StatementController::class)->name('statements');
            Route::get('/addresses', [Portal\AddressController::class, 'index'])->name('addresses');
            Route::post('/addresses', [Portal\AddressController::class, 'store'])->name('addresses.store');
            Route::put('/addresses/{address}', [Portal\AddressController::class, 'update'])->name('addresses.update');
            Route::delete('/addresses/{address}', [Portal\AddressController::class, 'destroy'])->name('addresses.destroy');
            Route::get('/team', [Portal\TeamController::class, 'index'])->name('team');
            Route::post('/team/invitations', [Portal\TeamController::class, 'invite'])->middleware('throttle:quotations')->name('team.invite');
            Route::delete('/team/invitations/{invitation}', [Portal\TeamController::class, 'revoke'])->name('team.revoke');
            Route::patch('/team/members/{member}', [Portal\TeamController::class, 'updateRole'])->name('team.role');
            Route::delete('/team/members/{member}', [Portal\TeamController::class, 'remove'])->name('team.remove');

            if ($portal === 'business') {
                Route::get('/quotations', [Portal\QuotationController::class, 'index'])->name('quotations');
                Route::get('/quotations/{quotation}', [Portal\QuotationController::class, 'show'])->name('quotations.show');
                Route::post('/quotations/{quotation}/accept', [Portal\QuotationController::class, 'accept'])->middleware('throttle:checkout')->name('quotations.accept');
                Route::post('/quotations/{quotation}/decline', [Portal\QuotationController::class, 'decline'])->name('quotations.decline');
            }
        });
    }

    /*
    | Admin
    */
    Route::prefix('admin')->name('admin.')->middleware('can:'.StaffPermission::AccessAdmin->value)->group(function (): void {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');
        Route::patch('/organizations/{organization}/status', Admin\OrganizationApprovalController::class)->name('organizations.status');
        Route::put('/users/{user}/roles', Admin\UserRoleController::class)->name('users.roles');
        Route::post('/users/{user}/mfa-reset', Admin\UserMfaResetController::class)->middleware('throttle:mfa')->name('users.mfa-reset');
        Route::middleware('can:'.StaffPermission::ManageCustomers->value)->group(function (): void {
            Route::get('/organizations', [Admin\OrganizationController::class, 'index'])->name('organizations.index');
            Route::get('/organizations/{organization}', [Admin\OrganizationController::class, 'show'])->name('organizations.show');
        });

        /* Phase 7 — real-image workflow */
        Route::middleware('can:'.StaffPermission::ManageMedia->value)->group(function (): void {
            Route::get('/products/{product}/images', [Admin\MediaController::class, 'product'])->name('media.product');
            Route::post('/products/{product}/images', [Admin\MediaController::class, 'store'])->middleware('throttle:media-uploads')->name('media.store');
            Route::put('/media/{image}', [Admin\MediaController::class, 'update'])->name('media.update');
            Route::post('/media/{image}/move', [Admin\MediaController::class, 'move'])->name('media.move');
            Route::post('/media/{image}/primary', [Admin\MediaController::class, 'primary'])->name('media.primary');
            Route::delete('/media/{image}', [Admin\MediaController::class, 'destroy'])->name('media.destroy');
        });
        Route::middleware('can:'.StaffPermission::ApproveMedia->value)->group(function (): void {
            Route::get('/media', [Admin\MediaController::class, 'queue'])->name('media.queue');
            Route::post('/media/{image}/approve', [Admin\MediaController::class, 'approve'])->name('media.approve');
            Route::post('/media/{image}/reject', [Admin\MediaController::class, 'reject'])->name('media.reject');
            Route::post('/media/{image}/withdraw', [Admin\MediaController::class, 'withdraw'])->name('media.withdraw');
        });
        Route::get('/media/{image}/preview', [Admin\MediaController::class, 'preview'])->name('media.preview');

        /* Phase 7 — CRM */
        Route::middleware('can:'.StaffPermission::AccessCrm->value)->prefix('crm')->name('crm.')->group(function (): void {
            Route::get('/', [Admin\CrmController::class, 'dashboard'])->name('dashboard');
            Route::get('/leads', [Admin\CrmController::class, 'index'])->name('leads.index');
            Route::post('/leads', [Admin\CrmController::class, 'store'])->name('leads.store');
            Route::get('/leads/{lead}', [Admin\CrmController::class, 'show'])->name('leads.show');
            Route::post('/leads/{lead}/assign', [Admin\CrmController::class, 'assign'])->name('leads.assign');
            Route::post('/leads/{lead}/stage', [Admin\CrmController::class, 'stage'])->name('leads.stage');
            Route::get('/customers', [Admin\CrmCustomerController::class, 'segments'])->name('customers.index');
            Route::get('/customers/{user}', [Admin\CrmCustomerController::class, 'customer'])->name('customers.show');
            Route::get('/organizations/{organization}', [Admin\CrmCustomerController::class, 'organization'])->name('organizations.show');
            Route::get('/tasks', [Admin\CrmCustomerController::class, 'tasks'])->name('tasks.index');
            Route::post('/tasks/{task}/complete', [Admin\CrmCustomerController::class, 'completeTask'])->name('tasks.complete');
            Route::post('/{type}/{id}/activities', [Admin\CrmCustomerController::class, 'logActivity'])->whereIn('type', ['lead', 'customer', 'organization'])->whereNumber('id')->name('activities.store');
            Route::post('/{type}/{id}/tasks', [Admin\CrmCustomerController::class, 'addTask'])->whereIn('type', ['lead', 'customer', 'organization'])->whereNumber('id')->name('tasks.store');
            Route::post('/{type}/{id}/tags', [Admin\CrmCustomerController::class, 'tag'])->whereIn('type', ['lead', 'customer', 'organization'])->whereNumber('id')->name('tags.store');
        });

        /* Phase 7 — analytics & reports */
        Route::get('/reports/{section?}', Admin\ReportController::class)->middleware('can:'.StaffPermission::ViewReports->value)->name('reports');

        Route::middleware('can:'.StaffPermission::ManageQuotations->value)->group(function (): void {
            Route::get('/quotations', [Admin\QuotationController::class, 'index'])->name('quotations.index');
            Route::get('/quotations/{quotation}', [Admin\QuotationController::class, 'show'])->name('quotations.show');
            Route::post('/quotations/{quotation}/issue', [Admin\QuotationController::class, 'issue'])->name('quotations.issue');
            Route::post('/quotations/{quotation}/close', [Admin\QuotationController::class, 'close'])->name('quotations.close');
        });

        Route::get('/audit', Admin\AuditLogController::class)
            ->middleware('can:'.StaffPermission::ViewAuditLog->value)
            ->name('audit');

        Route::middleware('can:'.StaffPermission::ManageCatalog->value)->group(function (): void {
            Route::resource('categories', Admin\CategoryController::class)->except(['show', 'destroy']);
            Route::resource('brands', Admin\BrandController::class)->only(['index', 'store', 'update']);
            Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
        });

        Route::middleware('can:'.StaffPermission::ManagePrices->value)->group(function (): void {
            Route::resource('price-lists', Admin\PriceListController::class)->except(['show', 'destroy']);
            Route::get('/scheduled-prices', [Admin\ScheduledPriceChangeController::class, 'index'])->name('scheduled-prices.index');
            Route::post('/scheduled-prices', [Admin\ScheduledPriceChangeController::class, 'store'])->name('scheduled-prices.store');
            Route::delete('/scheduled-prices/{scheduledPriceChange}', [Admin\ScheduledPriceChangeController::class, 'destroy'])->name('scheduled-prices.destroy');
            Route::get('/price-simulator', Admin\PriceSimulatorController::class)->name('price-simulator');
            Route::resource('promotions', Admin\PromotionController::class)->except(['show', 'destroy']);
            Route::resource('bundles', Admin\BundleController::class)->except(['show', 'destroy']);
        });

        Route::middleware('can:'.StaffPermission::ManageOrders->value)->group(function (): void {
            Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/reconciliation', [Admin\OrderController::class, 'reconciliation'])->name('orders.reconciliation');
            Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/status', [Admin\OrderController::class, 'transition'])->name('orders.transition');
            Route::post('/orders/{order}/cancel', [Admin\OrderController::class, 'cancel'])->name('orders.cancel');
            Route::post('/orders/{order}/allocate', [Admin\OrderController::class, 'retryAllocation'])->name('orders.allocate');
            Route::post('/orders/{order}/bank-transfer', [Admin\OrderController::class, 'confirmBankTransfer'])->name('orders.bank-transfer');
            Route::post('/orders/{order}/cash-collected', [Admin\OrderController::class, 'collectCash'])->name('orders.cash-collected');
            Route::post('/orders/{order}/refunds', [Admin\OrderController::class, 'refund'])->name('orders.refund');
            Route::resource('zones', Admin\DeliveryZoneController::class)->except(['show', 'destroy']);
        });

        Route::middleware('can:'.StaffPermission::ManageInventory->value)->prefix('inventory')->name('inventory.')->group(function (): void {
            Route::get('/', [Admin\InventoryController::class, 'index'])->name('index');
            Route::get('/verify', [Admin\InventoryController::class, 'verify'])->name('verify');
            Route::get('/{variant}', [Admin\InventoryController::class, 'show'])->name('show');
            Route::post('/{variant}/movements', [Admin\InventoryController::class, 'storeMovement'])->name('movements.store');
            Route::post('/{variant}/reconcile', [Admin\InventoryController::class, 'reconcile'])->name('reconcile');
        });

        Route::get('/staff', Admin\StaffController::class)->middleware('can:'.StaffPermission::ManageRoles->value)->name('staff');

        Route::middleware('can:'.StaffPermission::ManageCms->value)->group(function (): void {
            Route::resource('pages', Admin\PageController::class)->except(['show', 'destroy']);
            Route::get('/settings/storefront', [Admin\StorefrontSettingsController::class, 'edit'])->name('settings.storefront');
            Route::put('/settings/storefront', [Admin\StorefrontSettingsController::class, 'update'])->name('settings.storefront.update');
        });
    });
});
