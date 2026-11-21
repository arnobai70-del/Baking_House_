<?php

namespace App\Console\Commands;

use App\Enums\StaffPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deployment preflight: reports PASS / WARN / FAIL for every setting that must be right before a
 * staging or production release, and exits non-zero on any FAIL so a deploy script can stop.
 * Read-only: it never changes configuration, data or payment mode.
 */
#[Signature('app:readiness {--target= : Check against "staging" or "production" rules instead of APP_ENV}')]
#[Description('Check that this environment is safe and complete for a staging or production release')]
class CheckReadiness extends Command
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        $this->rows = [];
        $target = (string) ($this->option('target') ?: app()->environment());
        $live = in_array($target, ['staging', 'production'], true);
        $production = $target === 'production';

        $this->check('APP_DEBUG off', ! config('app.debug'), $live, 'Debug pages leak code and configuration.');
        $this->check('APP_KEY set', filled(config('app.key')), true, 'Run php artisan key:generate once per environment.');
        $this->check('APP_URL uses https', str_starts_with((string) config('app.url'), 'https://'), $live, 'Serve the site over HTTPS only.');
        $this->check('HTTPS links forced', (bool) config('security.force_https'), $live, 'Set FORCE_HTTPS=true behind TLS.');
        $this->check('Secure session cookie', (bool) config('session.secure'), $live, 'Set SESSION_SECURE_COOKIE=true.');
        $this->check('Database is not SQLite', config('database.default') !== 'sqlite', $production, 'Use MySQL / MariaDB for production.');

        $this->check('Payments stay in TEST mode', config('commerce.payments.mode', 'test') === 'test', true, 'Live payments are not authorised; set PAYMENTS_MODE=test.');
        if ($target === 'staging') {
            $this->check('Search indexing OFF on staging', ! config('seo.indexing_enabled'), true, 'Set SEO_INDEXING_ENABLED=false on staging.');
            $this->check('Staging access gate ON', (bool) config('security.staging_gate.enabled') && filled(config('security.staging_gate.password_hash')), true, 'Set STAGING_GATE_ENABLED=true with a user and password hash.');
        }
        if ($production) {
            $this->check('Search indexing decision recorded', true, false, config('seo.indexing_enabled') ? 'Indexing is ON.' : 'Indexing is OFF until the launch is approved.');
        }

        $this->check('Public storage link', is_link(public_path('storage')) || is_dir(public_path('storage')), $live, 'Run php artisan storage:link (approved product photos are served from it).');
        $this->check('GD with WebP', function_exists('imagewebp'), $live, 'Install PHP GD with WebP for photo renditions.');
        $this->check('GD with AVIF (optional)', function_exists('imageavif'), false, 'AVIF renditions are skipped without it; JPEG / WebP still work.');
        $this->check('OPcache enabled (web PHP)', (bool) ini_get('opcache.enable') || (bool) ini_get('opcache.enable_cli'), false, 'Enable OPcache in PHP-FPM; this CLI check can differ from the web server.');
        $this->check('Config cached', app()->configurationIsCached(), false, 'Run php artisan optimize on deploy.');
        $this->check('Routes cached', app()->routesAreCached(), false, 'Run php artisan optimize on deploy.');

        $lastBeat = Cache::get('scheduler:heartbeat');
        $this->check('Scheduler running (last 5 min)', $lastBeat !== null && Carbon::parse($lastBeat)->gt(now()->subMinutes(5)), $live,
            'Add the cron entry: * * * * * php artisan schedule:run (reservations, quotes, reminders, licence expiry depend on it).');

        $this->check('Mail is not the log driver', config('mail.default') !== 'log', false, 'Customer emails are only logged until a mail provider is configured.');
        $this->check('SMS provider configured', config('commerce.notifications.sms_driver', 'log') !== 'log', false, 'SMS is log-only until a provider is contracted.');
        $this->check('Seller legal details', ! str_contains((string) config('commerce.seller.address'), 'pending') && filled(config('commerce.seller.vat_registration')), false, 'Invoices say "not a VAT invoice" until BIN / address are supplied.');
        $this->check('Bank transfer details', ! str_contains((string) config('commerce.bank_transfer.account_number'), 'Pending'), false, 'Bank transfer instructions show placeholders.');

        $this->databaseChecks($live, $production);

        $this->table(['Result', 'Check', 'Note'], $this->rows);
        $failures = count(array_filter($this->rows, fn (array $row) => $row[0] === 'FAIL'));
        $warnings = count(array_filter($this->rows, fn (array $row) => $row[0] === 'WARN'));
        $this->line("Target: {$target}. {$failures} failure(s), {$warnings} warning(s).");

        return $failures > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function databaseChecks(bool $live, bool $production): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->rows[] = ['FAIL', 'Database connection', mb_substr($e->getMessage(), 0, 120)];

            return;
        }

        $this->check('Roles & permissions seeded', Permission::query()->count() >= count(StaffPermission::cases()), $live, 'Run php artisan db:seed --class=RolesAndPermissionsSeeder --force.');
        $superAdmins = User::query()->whereHas('roles', fn ($roles) => $roles->where('name', Role::SuperAdmin))->get();
        $this->check('A super admin exists', $superAdmins->isNotEmpty(), $live, 'Create the owner account and give it the super admin role.');
        $this->check('Every super admin has MFA', $superAdmins->every(fn (User $user) => $user->hasMfaEnrolled()), $live, 'Staff must enrol TOTP before using admin.');
        $this->check('No demo accounts', ! User::query()->where('email', 'like', '%@example.test')->exists(), $production, 'LocalDemoSeeder data must never reach production.');
    }

    private function check(string $name, bool $ok, bool $blocking, string $note): void
    {
        $this->rows[] = [$ok ? 'PASS' : ($blocking ? 'FAIL' : 'WARN'), $name, $ok ? '' : $note];
    }
}
