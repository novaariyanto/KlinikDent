<?php

namespace App\Console\Commands;

use App\Support\Saas\AddonService;
use Illuminate\Console\Command;

class ExpireAddonSubscriptions extends Command
{
    protected $signature = 'saas:expire-addons';

    protected $description = 'Tandai Add-on expired ketika ends_at sudah lewat';

    public function handle(AddonService $addons): int
    {
        $count = $addons->expireOverdue();
        $this->info('Add-on kedaluwarsa: '.$count);

        return self::SUCCESS;
    }
}
