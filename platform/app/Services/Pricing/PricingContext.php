<?php

namespace App\Services\Pricing;

use App\Enums\CustomerAudience;
use App\Models\Organization;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Who is buying and when. Always built on the server (PricingContextResolver) from the
 * authenticated user and an approved organization membership — never from client input.
 */
final readonly class PricingContext
{
    public DateTimeImmutable $at;

    public function __construct(
        public CustomerAudience $audience = CustomerAudience::Retail,
        public ?Organization $organization = null,
        public ?int $userId = null,
        ?DateTimeInterface $at = null,
    ) {
        $this->at = $at === null ? new DateTimeImmutable : DateTimeImmutable::createFromInterface($at);
    }

    public static function retail(?int $userId = null, ?DateTimeInterface $at = null): self
    {
        return new self(CustomerAudience::Retail, null, $userId, $at);
    }

    public static function forOrganization(Organization $organization, ?int $userId = null, ?DateTimeInterface $at = null): self
    {
        return new self(CustomerAudience::fromOrganizationType($organization->type), $organization, $userId, $at);
    }
}
