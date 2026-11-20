<?php

namespace App\Enums;

/**
 * CRM communication history / timeline entries. "system" entries are written by the application.
 */
enum ActivityType: string
{
    case Note = 'note';
    case Call = 'call';
    case Email = 'email';
    case Sms = 'sms';
    case Meeting = 'meeting';
    case System = 'system';

    public function label(): string
    {
        return $this === self::Sms ? 'SMS' : ucfirst($this->value);
    }

    /**
     * @return list<self>
     */
    public static function loggable(): array
    {
        return [self::Note, self::Call, self::Email, self::Sms, self::Meeting];
    }
}
