<?php
/**
 * SQL Server Database Connection
 * Using PDO for secure, flexible connections
 */

require_once __DIR__ . '/env_loader.php';
loadEnvFile(__DIR__ . '/.env');

// Server Configuration
$server = getenv('DB_SERVER') ?: 'localhost';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD') ?: '';

if ($database === '' || $username === '' || $password === '') {
    error_log('Database configuration missing: set DB_DATABASE, DB_USERNAME, DB_PASSWORD in config/.env');
    die('Sorry, database configuration is incomplete. Please contact the administrator.');
}

// DSN (Data Source Name) for SQL Server
$dsn = "sqlsrv:Server=$server;Database=$database;Encrypt=no;TrustServerCertificate=yes";

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