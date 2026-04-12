<?php
/**
 * Microsoft 365 OAuth Configuration
 * Store sensitive credentials in environment variables
 */

// Load .env file
$dotenv_path = __DIR__ . '/../.env';
if (file_exists($dotenv_path)) {
    $env = parse_ini_file($dotenv_path);
} else {
    $env = $_ENV; // Fall back to system env vars (for production)
}

// Microsoft 365 App Registration Details from .env
define('MICROSOFT_CLIENT_ID', $env['MICROSOFT_CLIENT_ID'] ?? '');
define('MICROSOFT_CLIENT_SECRET', $env['MICROSOFT_CLIENT_SECRET'] ?? '');
define('MICROSOFT_REDIRECT_URI', $env['MICROSOFT_REDIRECT_URI'] ?? 'http://localhost/student_violations/index.php?page=student_oauth_callback');
define('MICROSOFT_TENANT', 'common');

// Microsoft 365 OAuth Endpoints
define('MICROSOFT_AUTH_URL', 'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/authorize');
define('MICROSOFT_TOKEN_URL', 'https://login.microsoftonline.com/' . MICROSOFT_TENANT . '/oauth2/v2.0/token');
define('MICROSOFT_GRAPH_URL', 'https://graph.microsoft.com/v1.0/me');

// Required scopes for reading user profile
define('MICROSOFT_SCOPES', 'openid profile email');

// Fairview STI allowed email domain
define('ALLOWED_EMAIL_DOMAIN', '@fairview.sti.edu.ph');

// Session configuration
define('STUDENT_SESSION_KEY', 'student_id');
define('STUDENT_EMAIL_KEY', 'student_email');
define('STUDENT_NAME_KEY', 'student_name');

// Security
define('SESSION_LIFETIME', 3600); // 1 hour in seconds

?>