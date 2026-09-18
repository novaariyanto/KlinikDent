<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class RestoreDatabase extends Command
{
    protected $signature = 'app:restore {file : Path file backup (.sql / .sqlite)} {--force : Lewati konfirmasi}';

    protected $description = 'Restore database dari file backup di storage/app/backups';

    public function handle(): int
    {
        $file = $this->argument('file');
        $path = File::exists($file) ? $file : storage_path('app/backups/'.$file);

        if (! File::exists($path)) {
            $this->error('File backup tidak ditemukan: '.$path);

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Restore akan menimpa database aktif. Lanjutkan?')) {
            return self::SUCCESS;
        }

        $connection = config('database.default');

        try {
            match ($connection) {
                'sqlite' => $this->restoreSqlite($path),
                'mysql', 'mariadb' => $this->restoreMysql($path),
                default => throw new \RuntimeException('Restore belum didukung untuk koneksi '.$connection.'.'),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Restore selesai dari '.$path);

        return self::SUCCESS;
    }

    protected function restoreSqlite(string $path): void
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === '' || $database === ':memory:') {
            throw new \RuntimeException('Target SQLite tidak valid.');
        }

        File::copy($path, $database);
    }

    protected function restoreMysql(string $path): void
    {
        $config = config('database.connections.'.config('database.default'));

        $process = Process::fromShellCommandline(
            'mysql --user=${:USER} --password=${:PASSWORD} --host=${:HOST} --port=${:PORT} ${:DATABASE} < ${:FILE}'
        );

        $process->run(null, [
            'USER' => (string) ($config['username'] ?? ''),
            'PASSWORD' => (string) ($config['password'] ?? ''),
            'HOST' => (string) ($config['host'] ?? '127.0.0.1'),
            'PORT' => (string) ($config['port'] ?? '3306'),
            'DATABASE' => (string) ($config['database'] ?? ''),
            'FILE' => $path,
        ]);

        if (! $process->isSuccessful()) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysql restore gagal.');
        }
    }
}
