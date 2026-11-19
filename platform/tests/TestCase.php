<?php

namespace Tests;

use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PragmaRX\Google2FA\Google2FA;

abstract class TestCase extends BaseTestCase
{
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
