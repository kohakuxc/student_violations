<?php
if (!function_exists('isOfficerLoggedIn')) {
    function isOfficerLoggedIn()
    {
        return !empty($_SESSION['officer_id']);
    }
}

if (!function_exists('isAdminUser')) {
    function isAdminUser()
    {
        return !empty($_SESSION['is_admin']) || !empty($_SESSION['is_superadmin']);
    }
}

if (!function_exists('isSuperAdminUser')) {
    function isSuperAdminUser()
    {
        return !empty($_SESSION['is_superadmin']);
    }
}

if (!function_exists('canImportExcel')) {
    function canImportExcel()
    {
        return !empty($_SESSION['is_superadmin']) || !empty($_SESSION['can_import_excel']);
    }
}

if (!function_exists('requireSuperAdmin')) {
    function requireSuperAdmin()
    {
        if (!isSuperAdminUser()) {
            header('Location: index.php?page=dashboard');
            exit();
        }
    }
}

if (!function_exists('requireImportPermission')) {
    function requireImportPermission()
    {
        if (!canImportExcel()) {
            header('Location: index.php?page=dashboard');
            exit();
        }
    }
}
