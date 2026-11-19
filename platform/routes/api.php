<?php

use App\Enums\StaffPermission;
use App\Http\Controllers\Api\V1;
use Illuminate\Support\Facades\Route;

/*
| Versioned REST API (blueprint §10). All authenticated routes need a Sanctum token
| holding the named ability AND pass the same membership / permission checks as the web.
*/

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware('throttle:catalog')->group(function (): void {
        Route::get('/categories', [V1\CatalogController::class, 'categories'])->name('categories.index');
        Route::get('/products', [V1\CatalogController::class, 'products'])->name('products.index');
        Route::get('/products/{slug}', [V1\CatalogController::class, 'product'])->name('products.show');
        Route::get('/search', [V1\CatalogController::class, 'search'])->name('search');
    });

    Route::post('/auth/tokens', [V1\TokenController::class, 'store'])->middleware('throttle:login')->name('tokens.store');

    Route::middleware(['auth:sanctum', 'active', 'throttle:api'])->group(function (): void {
        Route::get('/auth/tokens', [V1\TokenController::class, 'index'])->name('tokens.index');
        Route::delete('/auth/tokens/current', [V1\TokenController::class, 'destroyCurrent'])->name('tokens.destroy-current');
        Route::delete('/auth/tokens/{tokenId}', [V1\TokenController::class, 'destroy'])->whereNumber('tokenId')->name('tokens.destroy');

        Route::get('/me', V1\MeController::class)->middleware('abilities:account:read')->name('me');

        Route::get('/business/organization', V1\OrganizationPortalController::class)
            ->middleware(['abilities:business:read', 'organization:business'])
            ->name('business.organization');

        Route::get('/reseller/organization', V1\OrganizationPortalController::class)
            ->middleware(['abilities:reseller:read', 'organization:reseller'])
            ->name('reseller.organization');

        Route::prefix('admin')->name('admin.')->middleware(['abilities:admin:read', 'can:'.StaffPermission::AccessAdmin->value])->group(function (): void {
            Route::get('/organizations', [V1\Admin\OrganizationController::class, 'index'])
                ->middleware('can:'.StaffPermission::ManageCustomers->value)
                ->name('organizations.index');
        });
    });
});
