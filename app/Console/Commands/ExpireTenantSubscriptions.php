<?php

namespace App\Console\Commands;

use App\Support\Saas\SubscriptionService;
use Illuminate\Console\Command;

class ExpireTenantSubscriptions extends Command
{
    protected $signature = 'saas:expire-subscriptions';

    protected $description = 'Suspend tenant ketika langganan trial/aktif sudah habis';

    public function handle(SubscriptionService $subscriptions): int
    {
        $count = $subscriptions->expireOverdue();
        $this->info('Langganan kedaluwarsa: '.$count);

        return self::SUCCESS;
    }
}
