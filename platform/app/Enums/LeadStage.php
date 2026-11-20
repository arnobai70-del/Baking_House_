<?php

namespace App\Enums;

/**
 * CRM pipeline stages. Won / lost are closed stages.
 */
enum LeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Qualified = 'qualified';
    case Quoted = 'quoted';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Won, self::Lost], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::New => 'warn',
            self::Contacted, self::Qualified, self::Quoted => 'info',
            self::Won => 'ok',
            self::Lost => '',
        };
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::New, self::Contacted, self::Qualified, self::Quoted];
    }
}
