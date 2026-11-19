<?php

namespace App\Services;

use App\Enums\ApiAbility;
use App\Enums\OrganizationType;
use App\Models\User;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\NewAccessToken;

/**
 * Issues Sanctum personal access tokens with abilities clamped to the owner's account types.
 */
class ApiTokenIssuer
{
    public const CUSTOMER_TTL_MINUTES = 60 * 24 * 30;

    public const STAFF_TTL_MINUTES = 60 * 8;

    /**
     * Every ability this user is entitled to right now.
     *
     * @return list<string>
     */
    public function allowedAbilities(User $user): array
    {
        $abilities = ApiAbility::customerDefaults();

        foreach (OrganizationType::cases() as $type) {
            if ($user->approvedOrganizations($type)->isNotEmpty()) {
                array_push($abilities, ...ApiAbility::forOrganizationType($type));
            }
        }

        if ($user->isStaff()) {
            array_push($abilities, ...ApiAbility::staff());
        }

        return array_map(fn (ApiAbility $ability) => $ability->value, $abilities);
    }

    /**
     * Requested abilities are intersected with the allowed set; an empty request means "all allowed".
     * Staff-capable tokens get the short staff lifetime.
     *
     * @param  list<string>  $requested
     */
    public function issue(User $user, string $deviceName, array $requested = []): NewAccessToken
    {
        $allowed = $this->allowedAbilities($user);
        $granted = $requested === [] ? $allowed : array_values(array_intersect($allowed, $requested));

        $isStaffToken = array_intersect($granted, [ApiAbility::AdminRead->value, ApiAbility::AdminWrite->value]) !== [];
        $expiresAt = Carbon::now()->addMinutes($isStaffToken ? self::STAFF_TTL_MINUTES : self::CUSTOMER_TTL_MINUTES);

        return $user->createToken($deviceName, $granted, $expiresAt);
    }
}
