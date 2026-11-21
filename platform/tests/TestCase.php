<?php

namespace Tests;

use App\Models\Setting;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
    /**
     * Settings are memoised in a static; never let one test's language settings leak into the next.
     */
    protected function setUp(): void
    {
        parent::setUp();
        Setting::flushMemo();
    }

    /**
     * Each real request starts in a fresh process with the default locale; without this the test
     * client would build the next request URL in the previous page's language (/bn prefix).
     *
     * @param  string  $uri
     */
    protected function prepareUrlForRequest($uri): string
    {
        $this->app->setLocale((string) config('app.fallback_locale', 'en'));

        return parent::prepareUrlForRequest($uri);
    }

    /**
     * Act as a staff member who has passed MFA in this session.
     */
    protected function actingAsStaff(User $user): static
    {
        return $this->actingAs($user)->withSession(['mfa' => ['verified_at' => time()]]);
    }

    /**
     * Current TOTP code for the factory test secret, optionally offset by time-steps.
     */
    protected function totp(int $stepOffset = 0): string
    {
        $google2fa = new Google2FA;

        return $google2fa->oathTotp(UserFactory::TEST_MFA_SECRET, $google2fa->getTimestamp() + $stepOffset);
    }
}
