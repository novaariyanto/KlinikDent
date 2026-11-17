<?php

namespace Database\Seeders;

use App\Enums\AddonBillingInterval;
use App\Enums\AddonCode;
use App\Models\SaasAddon;
use Illuminate\Database\Seeder;

class SaasAddonSeeder extends Seeder
{
    public function run(): void
    {
        SaasAddon::query()->updateOrCreate(
            ['code' => AddonCode::WhatsApp->value],
            [
                'name' => 'WhatsApp Integration',
                'description' => 'Pengingat jadwal, promosi, dan otomasi WhatsApp untuk klinik.',
                'benefits' => [
                    'Pengingat kunjungan otomatis',
                    'Promosi ke pasien consent',
                    'Template & riwayat pesan',
                ],
                'price' => 0,
                'interval' => AddonBillingInterval::Monthly,
                'trial_days' => 0,
                'terms' => 'Dengan mengaktifkan, klinik menyetujui ketentuan penggunaan WhatsApp Integration.',
                'is_active' => true,
            ]
        );
    }
}
