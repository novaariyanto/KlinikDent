<?php

require __DIR__.'/env.php';

$root = dirname(__DIR__, 3);
$envPath = $root.'/.env';
$action = $argv[1] ?? 'ping';
$vars = installer_load_env($envPath);

$connection = installer_env_get($vars, 'DB_CONNECTION', 'mysql');
$host = installer_env_get($vars, 'DB_HOST', '127.0.0.1');
$port = installer_env_get($vars, 'DB_PORT', '3306');
$database = installer_env_get($vars, 'DB_DATABASE', '');
$username = installer_env_get($vars, 'DB_USERNAME', '');
$password = installer_env_get($vars, 'DB_PASSWORD', '');
$socket = installer_env_get($vars, 'DB_SOCKET', '');

if (! in_array($connection, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "Installer mendukung MySQL/MariaDB. DB_CONNECTION={$connection}\n");
    exit(2);
}

function installer_pdo(string $dsn, string $username, string $password): PDO
{
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 8,
    ]);
}

function installer_mysql_dsn(string $host, string $port, ?string $database, string $socket): string
{
    if ($socket !== '') {
        $dsn = 'mysql:unix_socket='.$socket;
    } else {
        $dsn = 'mysql:host='.$host.';port='.$port;
    }

    if ($database !== null && $database !== '') {
        $dsn .= ';dbname='.$database;
    }

    return $dsn.';charset=utf8mb4';
}

try {
    if ($action === 'ping') {
        $pdo = installer_pdo(installer_mysql_dsn($host, $port, $database, $socket), $username, $password);
        $pdo->query('SELECT 1');
        echo "OK\n";
        exit(0);
    }

    if ($action === 'server') {
        $pdo = installer_pdo(installer_mysql_dsn($host, $port, null, $socket), $username, $password);
        $pdo->query('SELECT 1');
        echo "OK\n";
        exit(0);
    }

    if ($action === 'exists') {
        $pdo = installer_pdo(installer_mysql_dsn($host, $port, null, $socket), $username, $password);
        $stmt = $pdo->prepare('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?');
        $stmt->execute([$database]);
        echo $stmt->fetchColumn() ? "yes\n" : "no\n";
        exit(0);
    }

    if ($action === 'create') {
        if ($database === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $database)) {
            fwrite(STDERR, "Nama database tidak valid.\n");
            exit(1);
        }

        $pdo = installer_pdo(installer_mysql_dsn($host, $port, null, $socket), $username, $password);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        echo "OK\n";
        exit(0);
    }

    if ($action === 'users') {
        $pdo = installer_pdo(installer_mysql_dsn($host, $port, $database, $socket), $username, $password);
        $exists = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();

        if (! $exists) {
            echo "0\n";
            exit(0);
        }

        echo (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn()."\n";
        exit(0);
    }

    if ($action === 'migrations') {
        $pdo = installer_pdo(installer_mysql_dsn($host, $port, $database, $socket), $username, $password);
        $exists = $pdo->query("SHOW TABLES LIKE 'migrations'")->fetchColumn();
        echo $exists ? "yes\n" : "no\n";
        exit(0);
    }

    if ($action === 'table') {
        $table = $argv[2] ?? '';

        if ($table === '' || ! preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            fwrite(STDERR, "Nama tabel tidak valid.\n");
            exit(1);
        }

        $pdo = installer_pdo(installer_mysql_dsn($host, $port, $database, $socket), $username, $password);
        $stmt = $pdo->query("SHOW TABLES LIKE ".$pdo->quote($table));
        echo $stmt->fetchColumn() ? "yes\n" : "no\n";
        exit(0);
    }

    if ($action === 'meta') {
        echo 'host='.$host."\n";
        echo 'port='.$port."\n";
        echo 'database='.$database."\n";
        echo 'username='.$username."\n";
        echo 'connection='.$connection."\n";
        exit(0);
    }

    fwrite(STDERR, "Unknown action: {$action}\n");
    exit(1);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}
