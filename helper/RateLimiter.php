<?php
if (!function_exists('rateLimitCheck')) {
    function rateLimitCheck($key, $limit = 5, $windowSeconds = 60)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $key = trim((string) $key);
        if ($key === '') {
            return true;
        }

        $now = time();
        if (!isset($_SESSION['rate_limits']) || !is_array($_SESSION['rate_limits'])) {
            $_SESSION['rate_limits'] = [];
        }

        $bucket = $_SESSION['rate_limits'][$key] ?? [];
        $bucket = array_values(array_filter($bucket, function ($timestamp) use ($now, $windowSeconds) {
            return $timestamp > ($now - $windowSeconds);
        }));

        if (count($bucket) >= $limit) {
            $_SESSION['rate_limits'][$key] = $bucket;
            return false;
        }

        $bucket[] = $now;
        $_SESSION['rate_limits'][$key] = $bucket;
        return true;
    }
}
