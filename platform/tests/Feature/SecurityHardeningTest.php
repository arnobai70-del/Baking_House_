<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Phase 9: security headers / CSP, staging access gate, forced HTTPS and the readiness preflight.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_response_carries_security_headers_and_a_strict_script_policy(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $response->assertHeaderMissing('Strict-Transport-Security');

        $this->get('https://localhost/robots.txt')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $this->getJson('/api/v1/products')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_views_contain_no_inline_scripts_so_the_policy_can_forbid_them(): void
    {
        $offenders = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));
        foreach ($iterator as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (preg_match_all('/<script(?![^>]*\bsrc=)(?![^>]*application\/ld\+json)[^>]*>/i', $source) > 0 || preg_match('/\son[a-z]+="/i', $source) === 1) {
                $offenders[] = str_replace(resource_path('views'), '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, 'Inline scripts or on* handlers break the Content-Security-Policy.');
    }

    public function test_csp_is_skipped_in_debug_and_private_previews_keep_their_own(): void
    {
        config(['app.debug' => true]);
        $this->get('/')->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_staging_gate_requires_tester_credentials_and_fails_closed(): void
    {
        config(['security.staging_gate' => ['enabled' => true, 'user' => 'tester', 'password_hash' => Hash::make('correct horse'), 'open_paths' => ['up', 'api/v1/webhooks/*']]]);

        $this->get('/')->assertStatus(401)->assertHeader('WWW-Authenticate')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->get('/', ['PHP_AUTH_USER' => 'tester', 'PHP_AUTH_PW' => 'wrong'])->assertStatus(401);
        $this->withServerVariables(['PHP_AUTH_USER' => 'tester', 'PHP_AUTH_PW' => 'correct horse'])->get('/')->assertOk();
        $this->withServerVariables([])->get('/up')->assertOk();
        /* Provider webhooks are not behind the tester password (their own signature check still applies). */
        $this->withServerVariables([])->postJson('/api/v1/webhooks/payments/bkash', [])->assertHeaderMissing('WWW-Authenticate');

        config(['security.staging_gate.password_hash' => null]);
        $this->withServerVariables(['PHP_AUTH_USER' => 'tester', 'PHP_AUTH_PW' => ''])->get('/')->assertStatus(401);
    }

    public function test_force_https_generates_secure_urls(): void
    {
        config(['security.force_https' => true]);
        $this->refreshApplicationWithForcedHttps();

        $this->assertStringStartsWith('https://', route('catalog.shop'));
    }

    public function test_readiness_preflight_fails_unsafe_staging_and_passes_locally(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        config(['app.debug' => true, 'security.staging_gate.enabled' => false, 'seo.indexing_enabled' => true, 'commerce.payments.mode' => 'live']);

        $this->artisan('app:readiness', ['--target' => 'staging'])
            ->expectsOutputToContain('failure(s)')
            ->assertFailed();

        config(['commerce.payments.mode' => 'test']);
        $this->artisan('app:readiness')->doesntExpectOutputToContain('| FAIL')->assertSuccessful();
    }

    public function test_product_pages_never_render_unapproved_photos_under_csp(): void
    {
        config(['app.debug' => false]);
        $product = Product::factory()->withFixedVariant()->create();

        $csp = (string) $this->get($product->url())->assertOk()->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("img-src 'self' data:", $csp, 'Images only from this site: no hot-linked or third-party photos.');
    }

    private function refreshApplicationWithForcedHttps(): void
    {
        $this->app->make(UrlGenerator::class)->forceScheme('https');
    }
}
