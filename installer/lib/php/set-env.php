<?php

require __DIR__.'/env.php';

$root = dirname(__DIR__, 3);
$envPath = $root.'/.env';
$key = $argv[1] ?? '';
$value = $argv[2] ?? '';

if ($key === '' || ! preg_match('/^[A-Z][A-Z0-9_]*$/', $key)) {
    fwrite(STDERR, "Invalid env key.\n");
    exit(1);
}

if (! is_file($envPath)) {
    fwrite(STDERR, ".env not found.\n");
    exit(1);
}

$needsQuotes = (bool) preg_match('/[\s#\'"]/', $value);
$encoded = $needsQuotes ? '"'.str_replace(['\\', '"'], ['\\\\', '\"'], $value).'"' : $value;
$contents = file_get_contents($envPath);

if ($contents === false) {
    fwrite(STDERR, "Unable to read .env.\n");
    exit(1);
}

$pattern = '/^'.preg_quote($key, '/').'\s*=.*$/m';
$line = $key.'='.$encoded;

if (preg_match($pattern, $contents)) {
    $contents = preg_replace($pattern, $line, $contents, 1);
} else {
    $contents = rtrim($contents)."\n".$line."\n";
}

if (file_put_contents($envPath, $contents) === false) {
    fwrite(STDERR, "Unable to write .env.\n");
    exit(1);
}

echo "OK\n";
