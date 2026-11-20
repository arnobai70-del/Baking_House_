<?php

namespace App\Providers;

use App\Enums\StaffPermission;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Notifications\LogSmsGateway;
use App\Services\Notifications\SmsGateway;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsGateway::class, fn () => match (config('commerce.notifications.sms_driver')) {
            'log' => new LogSmsGateway,
            default => throw new RuntimeException('No SMS provider is configured. Only the "log" driver is available until a provider is contracted.'),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        $this->registerStaffGates();
        $this->registerRateLimiters();

        /* Bring the guest cart along when a customer signs in. */
        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User && $this->app->bound('request')) {
                app(CartService::class)->mergeGuestCart(request()->cookie(CartService::COOKIE), $event->user);
            }
        });
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

        RateLimiter::for('mfa', function (Request $request): array {
            $subject = $request->session()->get('mfa.pending.user_id') ?? $request->user()?->getKey() ?? 'guest';

            return [
                Limit::perMinute(5)->by('mfa:'.$subject.'|'.$request->ip()),
                Limit::perMinute(20)->by('mfa-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('checkout', fn (Request $request): Limit => Limit::perMinute(10)->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()));

        /* Public RFQ form and team invitations: low volume, abuse would spam staff / inboxes. */
        RateLimiter::for('quotations', fn (Request $request): array => [
            Limit::perMinute(5)->by(($request->user()?->getKey() ?? 'guest').'|'.$request->ip()),
            Limit::perDay(40)->by($request->ip()),
        ]);

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('catalog', fn (Request $request): Limit => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)->by($request->user()?->getKey() ?: $request->ip()));
    }
}
