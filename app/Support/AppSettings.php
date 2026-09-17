<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppSettings
{
    public static function logoUrl(string $variant = 'dark'): string
    {
        $path = Setting::getValue('logo');

        if ($path && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return $variant === 'light'
            ? theme('images/logo-light.png')
            : theme('images/logo-dark.png');
    }

    public static function hasCustomLogo(): bool
    {
        $path = Setting::getValue('logo');

        return (bool) ($path && Storage::disk('public')->exists($path));
    }

    public static function applyMailConfig(): void
    {
        $mailer = Setting::getValue('mail_mailer');

        if (! $mailer) {
            return;
        }

        $encryption = strtolower((string) Setting::getValue('mail_encryption', ''));
        $port = (int) Setting::getValue('mail_port', config('mail.mailers.smtp.port'));
        $scheme = match (true) {
            $encryption === 'ssl', $port === 465 => 'smtps',
            default => 'smtp',
        };

        config([
            'mail.default' => $mailer,
            'mail.from.address' => Setting::getValue('mail_from_address', config('mail.from.address')),
            'mail.from.name' => Setting::getValue('mail_from_name', config('mail.from.name')),
            'mail.mailers.smtp.host' => Setting::getValue('mail_host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => Setting::getValue('mail_username'),
            'mail.mailers.smtp.password' => static::mailPassword(),
            'mail.mailers.smtp.scheme' => $scheme,
            'mail.mailers.smtp.timeout' => 30,
            'mail.mailers.smtp.local_domain' => static::ehloDomain(),
        ]);

        if (app()->bound('mail.manager')) {
            $manager = app('mail.manager');
            $manager->purge();
            $manager->purge($mailer);
            $manager->forgetMailers();
        }
    }

    public static function mailPassword(): ?string
    {
        $value = Setting::getValue('mail_password');

        if (! $value) {
            return null;
        }

        try {
            $value = Crypt::decryptString($value);
        } catch (\Throwable) {
            // Stored value may still be plaintext from older saves.
        }

        return preg_replace('/\s+/', '', trim((string) $value)) ?: null;
    }

    protected static function ehloDomain(): string
    {
        $domain = (string) config('mail.mailers.smtp.local_domain');

        if ($domain === '' || in_array(strtolower($domain), ['localhost', '::1'], true)) {
            return '127.0.0.1';
        }

        return $domain;
    }

    public static function createSmtpTransport(array $config): EsmtpTransport
    {
        $factory = new EsmtpTransportFactory;

        $scheme = $config['scheme'] ?? null;

        if (! $scheme) {
            $scheme = (($config['port'] ?? null) == 465) ? 'smtps' : 'smtp';
        }

        /** @var EsmtpTransport $transport */
        $transport = $factory->create(new Dsn(
            $scheme,
            $config['host'],
            $config['username'] ?? null,
            $config['password'] ?? null,
            $config['port'] ?? null,
            $config
        ));

        $stream = $transport->getStream();

        if ($stream instanceof SocketStream) {
            if (isset($config['timeout'])) {
                $stream->setTimeout((float) $config['timeout']);
            }

            $cafile = static::certificateBundlePath();

            if ($cafile) {
                $options = $stream->getStreamOptions();
                $options['ssl']['cafile'] = $cafile;
                $options['ssl']['verify_peer'] = true;
                $options['ssl']['verify_peer_name'] = true;
                $stream->setStreamOptions($options);
            }
        }

        return $transport;
    }

    protected static function certificateBundlePath(): ?string
    {
        $configured = (string) ini_get('openssl.cafile');

        $candidates = array_filter([
            $configured !== '' ? $configured : null,
            getenv('SSL_CERT_FILE') ?: null,
            'C:/laragon/etc/ssl/cacert.pem',
            dirname(PHP_BINARY).'/extras/ssl/cacert.pem',
            dirname(PHP_BINARY).'/ssl/cacert.pem',
            'C:/Program Files/Common Files/SSL/cert.pem',
            storage_path('certs/cacert.pem'),
        ]);

        if (function_exists('openssl_get_cert_locations')) {
            $locations = openssl_get_cert_locations();
            if (! empty($locations['default_cert_file'])) {
                $candidates[] = $locations['default_cert_file'];
            }
        }

        foreach ($candidates as $path) {
            if (is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
