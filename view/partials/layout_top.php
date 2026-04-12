<?php
// view/partials/layout_top.php
// Usage: set $pageTitle = 'Dashboard'; (optional) before including this file.
$pageTitle = $pageTitle ?? 'Violations Management System';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($pageTitle); ?> - Student Violations System</title>
    <link rel="stylesheet" href="css/style.css" />
</head>

<body>

    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <button class="sidebar-hamburger" type="button" id="sidebarClose" aria-label="Close menu">
                    ☰
                </button>

            </div>

            <nav class="sidebar-nav">
                <a class="sidebar-link" href="index.php?page=dashboard">Dashboard</a>
                <a class="sidebar-link" href="index.php?page=add_violation">Add New Record</a>
                <a class="sidebar-link" href="index.php?page=search_student">Search Violations</a>
                <a class="sidebar-link" href="index.php?page=all_violations">View All Violations</a>
                <a class="sidebar-link" href="index.php?page=appointments">Appointments</a>
            </nav>
        </aside>

        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="app-main">
            <header class="topbar">
                <button class="sidebar-hamburger" type="button" id="sidebarToggle" aria-label="Toggle menu">
                    ☰
                </button>

                <a class="topbar-title" href="index.php?page=dashboard">Violations Management System</a>

                <div class="topbar-right">
                    <?php if (!empty($_SESSION['name'])): ?>
                        <span class="welcome-text"><b>Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?></b></span>
                    <?php endif; ?>
                    <a href="index.php?page=logout" class="btn btn-small btn-danger">Logout</a>
                </div>
            </header>

            <main class="app-content">