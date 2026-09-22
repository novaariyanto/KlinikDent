<?php

namespace Database\Seeders;

use App\Support\WhatsApp\WhatsAppProvisioningService;
use Illuminate\Database\Seeder;

class WhatsAppTemplateSeeder extends Seeder
{
    public function run(): void
    {
        app(WhatsAppProvisioningService::class)->ensureGlobals();
    }
}
