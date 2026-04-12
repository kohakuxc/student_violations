<?php
/**
 * Microsoft 365 OAuth Configuration
 * Store sensitive credentials in environment variables in production
 */

// Load environment variables (optional, using .env file)
// You can also hardcode these or use $_ENV

// Microsoft 365 App Registration Details
define('MICROSOFT_CLIENT_ID', getenv('MICROSOFT_CLIENT_ID') ?: '');
define('MICROSOFT_CLIENT_SECRET', getenv('MICROSOFT_CLIENT_SECRET') ?: '');
define('MICROSOFT_REDIRECT_URI', getenv('MICROSOFT_REDIRECT_URI') ?: 'http://localhost/student_violations/index.php?page=student_oauth_callback');
define('MICROSOFT_TENANT', '04199bc0-e2b1-480b-8826-04c0f58146f0'); // Use 'common' for multi-tenant, or specific tenant ID

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