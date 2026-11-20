<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Development / test SMS driver: writes the message to the application log instead of sending.
 */
class LogSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'log';
    }

    public function send(string $phone, string $message): void
    {
        Log::info('[SMS:log] to '.OrderNotifier::mask($phone).': '.$message);
    }
}
