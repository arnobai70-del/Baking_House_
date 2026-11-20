<?php

namespace App\Enums;

enum LeadSource: string
{
    case Rfq = 'rfq';
    case Application = 'application';
    case Phone = 'phone';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rfq => 'Quotation request (RFQ)',
            self::Application => 'Business / reseller application',
            self::Phone => 'Phone call',
            self::WalkIn => 'Walk-in',
            self::Referral => 'Referral',
            self::Other => 'Other',
        };
    }

    /**
     * Sources staff can pick when creating a lead by hand.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Phone, self::WalkIn, self::Referral, self::Other];
    }
}
