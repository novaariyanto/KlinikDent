<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'app:backup {--keep= : Jumlah file backup yang dipertahankan}';

    protected $description = 'Backup database ke storage/app/backups';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd_His');
        $connection = config('database.default');
        $keep = (int) ($this->option('keep') ?: env('BACKUP_KEEP_DAYS', 14));

        try {
            $path = match ($connection) {
                'sqlite' => $this->backupSqlite($dir, $stamp),
                'mysql', 'mariadb' => $this->backupMysql($dir, $stamp),
                default => throw new \RuntimeException('Backup belum didukung untuk koneksi '.$connection.'.'),
            };
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->prune($dir, $keep);
        $this->info('Backup tersimpan: '.$path);

        return self::SUCCESS;
    }

    protected function backupSqlite(string $dir, string $stamp): string
    {
        $database = config('database.connections.sqlite.database');

        if (! is_string($database) || $database === '' || $database === ':memory:' || ! File::exists($database)) {
            throw new \RuntimeException('File SQLite tidak ditemukan.');
        }

        $path = $dir.DIRECTORY_SEPARATOR.'klinikdent_'.$stamp.'.sqlite';
        File::copy($database, $path);

        return $path;
    }

    protected function backupMysql(string $dir, string $stamp): string
    {
        $config = config('database.connections.'.config('database.default'));
        $path = $dir.DIRECTORY_SEPARATOR.'klinikdent_'.$stamp.'.sql';

        $process = Process::fromShellCommandline(
            'mysqldump --user=${:USER} --password=${:PASSWORD} --host=${:HOST} --port=${:PORT} --single-transaction --routines --triggers ${:DATABASE} > ${:FILE}'
        );

        $process->run(null, [
            'USER' => (string) ($config['username'] ?? ''),
            'PASSWORD' => (string) ($config['password'] ?? ''),
            'HOST' => (string) ($config['host'] ?? '127.0.0.1'),
            'PORT' => (string) ($config['port'] ?? '3306'),
            'DATABASE' => (string) ($config['database'] ?? ''),
            'FILE' => $path,
        ]);

        if (! $process->isSuccessful() || ! File::exists($path) || File::size($path) === 0) {
            throw new \RuntimeException(trim($process->getErrorOutput()) ?: 'mysqldump gagal.');
        }

        return $path;
    }

    protected function prune(string $dir, int $keep): void
    {
        $files = collect(File::files($dir))
            ->sortByDesc(fn ($file) => $file->getMTime())
            ->values();

        foreach ($files->slice(max(1, $keep)) as $file) {
            File::delete($file->getPathname());
        }
    }
}
