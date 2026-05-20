<?php
if (!function_exists('csrfEnsureSession')) {
    function csrfEnsureSession()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }
}

if (!function_exists('csrfToken')) {
    function csrfToken()
    {
        csrfEnsureSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrfGenerateFormToken')) {
    function csrfGenerateFormToken($formKey)
    {
        csrfEnsureSession();
        $formKey = trim((string) $formKey);
        if ($formKey === '') {
            return '';
        }
        $token = bin2hex(random_bytes(32));
        if (!isset($_SESSION['form_tokens']) || !is_array($_SESSION['form_tokens'])) {
            $_SESSION['form_tokens'] = [];
        }
        $_SESSION['form_tokens'][$formKey] = $token;
        return $token;
    }
}

if (!function_exists('csrfValidateToken')) {
    function csrfValidateToken($token)
    {
        csrfEnsureSession();
        $token = (string) $token;
        if ($token === '' || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('csrfValidateFormToken')) {
    function csrfValidateFormToken($formKey, $token)
    {
        csrfEnsureSession();
        $formKey = trim((string) $formKey);
        $token = (string) $token;
        if ($formKey === '' || $token === '') {
            return false;
        }
        $stored = $_SESSION['form_tokens'][$formKey] ?? null;
        if (!$stored || !hash_equals($stored, $token)) {
            return false;
        }
        unset($_SESSION['form_tokens'][$formKey]);
        return true;
    }
}

if (!function_exists('csrfRequireValidToken')) {
    function csrfRequireValidToken($token, $formKey = null, $formToken = null)
    {
        if (!csrfValidateToken($token)) {
            throw new Exception('Invalid CSRF token.');
        }
        if ($formKey !== null && $formToken !== null) {
            if (!csrfValidateFormToken($formKey, $formToken)) {
                throw new Exception('Form submission already processed. Please refresh and try again.');
            }
        }
    }
}
