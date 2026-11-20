<?php

namespace App\Services\Notifications;

/**
 * Provider-neutral SMS interface. A real provider adapter is added only after a contract and
 * verified API documentation exist; until then the "log" driver is used.
 */
interface SmsGateway
{
    public function name(): string;

    public function send(string $phone, string $message): void;
}
