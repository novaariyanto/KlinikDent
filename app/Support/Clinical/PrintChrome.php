<?php

namespace App\Support\Clinical;

use App\Models\Branch;
use App\Support\ClinicSettings;

final class PrintChrome
{
    /**
     * Data kop surat yang dipakai semua dokumen cetak.
     *
     * @return array<string, mixed>
     */
    public static function variables(?int $tenantId, ?Branch $branch = null): array
    {
        $settings = ClinicSettings::current($tenantId);
        $printedAt = now();

        return [
            'clinicName' => ClinicSettings::name($tenantId),
            'clinicTagline' => $settings['tagline'] ?: null,
            'clinicLegalName' => $settings['legal_name'] ?: null,
            'clinicAddressLines' => ClinicSettings::addressLines($tenantId) ?: array_values(array_filter([
                $branch?->address,
            ])),
            'clinicPhone' => $settings['phone'] ?: $branch?->phone,
            'clinicEmail' => $settings['email'] ?: null,
            'clinicWebsite' => $settings['website'] ?: null,
            'clinicLicense' => $settings['license_number'] ?: null,
            'clinicNpwp' => $settings['npwp'] ?: null,
            'picName' => $settings['pic_name'] ?: null,
            'picSip' => $settings['pic_sip'] ?: null,
            'printCity' => $settings['print_city'] ?: ($settings['city'] ?: $branch?->name),
            'printFooter' => $settings['print_footer'] ?: null,
            'logoSrc' => ClinicSettings::printLogoSrc($tenantId),
            'printedAt' => $printedAt,
            'printedDateLabel' => $printedAt->format('d-M-Y'),
        ];
    }
}
