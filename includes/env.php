<?php
/**
 * Minimal .env loader for local development.
 */

if (!function_exists('loadEnv')) {
    function loadEnv($envPath = null) {
        static $loaded = false;
        if ($loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        }

        if (!is_file($envPath)) {
            $loaded = true;
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            if ($key === '') {
                continue;
            }

            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
            if (!array_key_exists($key, $_SERVER)) {
                $_SERVER[$key] = $value;
            }
            putenv($key . '=' . $value);
        }

        $loaded = true;
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        loadEnv();

        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return $default;
    }
}
