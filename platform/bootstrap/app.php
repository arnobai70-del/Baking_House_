<?php

use App\Http\Middleware\EnsureOrganizationAccess;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\LocalizeUrlPrefix;
use App\Http\Middleware\RequireStaffMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SendRobotsHeader;
use App\Http\Middleware\SetStorefrontLocale;
use App\Http\Middleware\StagingGate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([StagingGate::class, LocalizeUrlPrefix::class]);
        $middleware->append([SendRobotsHeader::class, SecurityHeaders::class]);
        $middleware->web(append: [SetStorefrontLocale::class, EnsureUserIsActive::class, RequireStaffMfa::class]);
        $middleware->alias([
            'organization' => EnsureOrganizationAccess::class,
            'active' => EnsureUserIsActive::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
        $middleware->redirectUsersTo(fn () => route('account'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
