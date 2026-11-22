<?php
/**
 * Database Connection
 * Supports SQL Server locally and Supabase w PostgreSQL in production.
 */

require_once __DIR__ . '/env_loader.php';
loadEnvFile(__DIR__ . '/.env');

// Driver Configuration
$driver = strtolower(trim(getenv('DB_DRIVER') ?: 'sqlsrv'));
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

if ($driver === 'pgsql' && ($database === '' || $username === '' || $password === '')) {
    $databaseUrl = getenv('DATABASE_URL') ?: '';

    if ($databaseUrl !== '') {
        $databaseParts = parse_url($databaseUrl);

        if ($databaseParts === false || !isset($databaseParts['host'])) {
            error_log('Database configuration invalid: DATABASE_URL must be a valid PostgreSQL URL');
            die('Sorry, database configuration is incomplete. Please contact the administrator.');
        }

        $host = $databaseParts['host'];
        $port = isset($databaseParts['port']) ? (string) $databaseParts['port'] : '5432';
        $database = isset($databaseParts['path']) ? ltrim($databaseParts['path'], '/') : 'postgres';
        $username = isset($databaseParts['user']) ? urldecode($databaseParts['user']) : '';
        $password = isset($databaseParts['pass']) ? urldecode($databaseParts['pass']) : '';

        parse_str($databaseParts['query'] ?? '', $databaseQuery);
        $sslmode = isset($databaseQuery['sslmode']) ? $databaseQuery['sslmode'] : 'require';
        putenv('DB_HOST=' . $host);
        putenv('DB_PORT=' . $port);
        putenv('DB_DATABASE=' . $database);
        putenv('DB_USERNAME=' . $username);
        putenv('DB_PASSWORD=' . $password);
        putenv('DB_SSLMODE=' . $sslmode);
        $_ENV['DB_HOST'] = $host;
        $_ENV['DB_PORT'] = $port;
        $_ENV['DB_DATABASE'] = $database;
        $_ENV['DB_USERNAME'] = $username;
        $_ENV['DB_PASSWORD'] = $password;
        $_ENV['DB_SSLMODE'] = $sslmode;
    }
}

if ($database === '' || $username === '' || $password === '') {
    error_log('Database configuration missing: set DB_DATABASE, DB_USERNAME, DB_PASSWORD in config/.env');
    die('Sorry, database configuration is incomplete. Please contact the administrator.');
}

if ($driver === 'pgsql') {
    $host = getenv('DB_HOST') ?: '';
    $port = getenv('DB_PORT') ?: '5432';
    $sslmode = getenv('DB_SSLMODE') ?: 'require';

    if ($host === '') {
        error_log('Database configuration missing: set DB_HOST for PostgreSQL/Supabase in config/.env');
        die('Sorry, database configuration is incomplete. Please contact the administrator.');
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$database;sslmode=$sslmode";
} else {
    $server = getenv('DB_SERVER') ?: 'localhost';
    $dsn = "sqlsrv:Server=$server;Database=$database;Encrypt=no;TrustServerCertificate=yes";
}

// PDO Options
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];


try {
    $conn = new PDO($dsn, $username, $password, $options);
}  catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die("Sorry, we encountered a database connection error. Please try again later.");
}
?>