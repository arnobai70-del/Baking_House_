<?php

namespace App\Providers;

use App\Enums\StaffPermission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->registerStaffGates();
        $this->registerRateLimiters();
    }

    /**
     * Every StaffPermission becomes a Gate ability. Super admin holds all permissions through
     * seeded role data (no Gate::before bypass), so every check stays explicit and auditable.
     */
    private function registerStaffGates(): void
    {
        foreach (StaffPermission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->is_active && $user->hasPermission($permission));
        }
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $identifier = Str::lower((string) $request->input('identifier'));

            return [
                Limit::perMinute(5)->by($identifier.'|'.$request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

        RateLimiter::for('register', fn (Request $request): Limit => Limit::perHour(10)->by($request->ip()));
    }
}
