<?php
/**
 * Microsoft 365 OAuth Configuration
 * Store sensitive credentials in environment variables in production
 */

require_once __DIR__ . '/env_loader.php';
loadEnvFile(__DIR__ . '/.env');

// Microsoft 365 App Registration Details
define('MICROSOFT_CLIENT_ID', getenv('MICROSOFT_CLIENT_ID') ?: 'YOUR_CLIENT_ID_HERE');
define('MICROSOFT_CLIENT_SECRET', getenv('MICROSOFT_CLIENT_SECRET') ?: 'YOUR_CLIENT_SECRET_HERE');
define('MICROSOFT_REDIRECT_URI', getenv('MICROSOFT_REDIRECT_URI') ?: 'http://localhost/student_violations/index.php?page=student_oauth_callback');
define('MICROSOFT_TENANT', getenv('MICROSOFT_TENANT') ?: 'common'); // Use 'common' for multi-tenant, or specific tenant ID

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