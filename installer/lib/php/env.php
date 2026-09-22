<?php

/**
 * Parser .env ringan untuk installer (tanpa bootstrap Laravel).
 *
 * @return array<string, string>
 */
function installer_load_env(string $path): array
{
    if (! is_file($path)) {
        return [];
    }

    $vars = [];

    foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (! str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);

        if ($key === '') {
            continue;
        }

        $value = trim($value);

        if ($value !== '' && (str_starts_with($value, '"') && str_ends_with($value, '"') || str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        $vars[$key] = $value;
    }

    foreach ($vars as $key => $value) {
        $vars[$key] = preg_replace_callback(
            '/\$\{([A-Z0-9_]+)\}/',
            static fn (array $match): string => $vars[$match[1]] ?? '',
            $value
        ) ?? $value;
    }

    return $vars;
}

function installer_env_get(array $vars, string $key, string $default = ''): string
{
    $value = $vars[$key] ?? $default;

    return is_string($value) ? $value : $default;
}

function installer_is_secret_key(string $key): bool
{
    $upper = strtoupper($key);

    foreach (['PASSWORD', 'SECRET', 'TOKEN', 'KEY', 'DSN', 'PRIVATE'] as $needle) {
        if (str_contains($upper, $needle)) {
            return true;
        }
    }

    return false;
}

function installer_mask(string $value): string
{
    if ($value === '' || strcasecmp($value, 'null') === 0) {
        return $value === '' ? '(empty)' : 'null';
    }

    return str_repeat('*', min(8, max(4, strlen($value))));
}
