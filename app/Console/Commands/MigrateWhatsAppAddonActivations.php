<?php

namespace App\Console\Commands;

use App\Support\Saas\AddonService;
use Illuminate\Console\Command;

class MigrateWhatsAppAddonActivations extends Command
{
    protected $signature = 'saas:migrate-whatsapp-addons';

    protected $description = 'Aktifkan Add-on WhatsApp untuk tenant yang sudah punya whatsapp_settings';

    public function handle(AddonService $addons): int
    {
        $count = $addons->migrateFromWhatsAppSettings();
        $this->info('Tenant dimigrasi ke Add-on WhatsApp aktif: '.$count);

        return self::SUCCESS;
    }
}
