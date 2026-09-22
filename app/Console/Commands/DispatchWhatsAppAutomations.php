<?php

namespace App\Console\Commands;

use App\Support\WhatsApp\WhatsAppSchedulerService;
use Illuminate\Console\Command;

class DispatchWhatsAppAutomations extends Command
{
    protected $signature = 'whatsapp:dispatch';

    protected $description = 'Kirim automation WhatsApp yang sudah jatuh tempo';

    public function handle(WhatsAppSchedulerService $scheduler): int
    {
        $count = $scheduler->dispatchDue();
        $this->info('WhatsApp jobs disiapkan: '.$count);

        return self::SUCCESS;
    }
}
