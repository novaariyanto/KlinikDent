<?php

namespace App\Support\Clinical;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * QR tanda tangan digital: identitas penandatangan dan hash SHA-256 dokumen.
 * Isi QR sama dengan "Hash Data" yang tercetak, supaya keutuhan dokumen bisa dicek.
 */
final class SignatureQr
{
    public static function dataUri(
        string $hash,
        string $signer,
        ?string $sip = null,
        ?string $signedAt = null,
        ?string $document = null,
    ): ?string {
        $hash = strtolower(trim($hash));

        if ($hash === '') {
            return null;
        }

        $options = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'outputBase64' => true,
            'scale' => 4,
            'quietzoneSize' => 1,
        ]);

        return (new QRCode($options))->render(self::payload($hash, $signer, $sip, $signedAt, $document));
    }

    public static function payload(
        string $hash,
        string $signer,
        ?string $sip = null,
        ?string $signedAt = null,
        ?string $document = null,
    ): string {
        $lines = [
            'TTD-DIGITAL',
            'ALG=SHA-256',
            'HASH='.strtolower(trim($hash)),
        ];

        $signer = self::clean($signer);
        if ($signer !== '' && $signer !== '........................') {
            $lines[] = 'SIGNER='.$signer;
        }

        $sip = self::clean($sip);
        if ($sip !== '') {
            $lines[] = 'SIP='.$sip;
        }

        $signedAt = self::clean($signedAt);
        if ($signedAt !== '') {
            $lines[] = 'SIGNED_AT='.$signedAt;
        }

        $document = self::clean($document);
        if ($document !== '') {
            $lines[] = 'DOC='.$document;
        }

        return implode("\n", $lines);
    }

    private static function clean(?string $value): string
    {
        $value = trim((string) $value);
        $value = str_replace(["\r", "\n", '='], ' ', $value);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
