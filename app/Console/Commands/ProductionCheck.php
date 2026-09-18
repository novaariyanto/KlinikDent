<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProductionCheck extends Command
{
    protected $signature = 'app:production-check';

    protected $description = 'Checklist konfigurasi production (APP_DEBUG, APP_KEY, HTTPS, queue)';

    public function handle(): int
    {
        $checks = [
            'APP_ENV=production' => config('app.env') === 'production',
            'APP_DEBUG=false' => config('app.debug') === false,
            'APP_KEY terisi' => filled(config('app.key')),
            'APP_URL https' => str_starts_with((string) config('app.url'), 'https://'),
            'APP_FORCE_HTTPS=true' => (bool) config('app.force_https'),
            'QUEUE bukan sync' => config('queue.default') !== 'sync',
            'SESSION_ENCRYPT disarankan' => (bool) config('session.encrypt'),
            'LOG_LEVEL bukan debug' => ! in_array(env('LOG_LEVEL', 'debug'), ['debug', 'info'], true) || config('app.env') !== 'production',
        ];

        $failed = 0;

        foreach ($checks as $label => $ok) {
            if ($ok) {
                $this->info('[OK] '.$label);

                continue;
            }

            $failed++;
            $this->error('[FAIL] '.$label);
        }

        $this->newLine();
        $this->line('Opsional: set SENTRY_LARAVEL_DSN jika memakai Sentry.');
        $this->line('Backup: php artisan app:backup  |  Restore: php artisan app:restore {file} --force');

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
