<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('crm:send-reminders')]
#[Description('Remind staff about CRM follow-up tasks that are now due (log driver until a provider is configured)')]
class SendCrmReminders extends Command
{
    public function handle(CrmService $crm): int
    {
        $count = $crm->sendDueReminders();
        $this->info("Sent {$count} reminder(s).");

        return self::SUCCESS;
    }
}
