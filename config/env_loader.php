<?php
/**
 * Lightweight .env loader for local configuration.
 */
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($filePath)
    {
        if (!is_readable($filePath)) {
            return;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $separatorPos = strpos($line, '=');
            if ($separatorPos === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separatorPos));
            $value = trim(substr($line, $separatorPos + 1));

            if ($key === '') {
                continue;
            }

            if (preg_match('/^"((?:\\\\.|[^"\\\\])*)"\s*(?:#.*)?$/', $value, $matches)) {
                $value = stripcslashes($matches[1]);
            } elseif (preg_match('/^\'((?:\\\\.|[^\'\\\\])*)\'\s*(?:#.*)?$/', $value, $matches)) {
                $value = stripcslashes($matches[1]);
            } else {
                $value = preg_replace('/\s+#.*$/', '', $value);
                $value = trim($value);
            }

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}
