<?php
// view/partials/layout_top.php
$pageTitle = $pageTitle ?? 'Violations Management System';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($pageTitle); ?> - Student Violations System</title>

    <!-- Your custom CSS -->
    <link rel="stylesheet" href="css/style.css" />

    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Appointment System CSS -->
    <link rel="stylesheet" href="css/appointments.css" />

    <!-- FIX: Override excessive margins -->
    <style>
        .app-content {
            padding: 20px !important;
            margin: 0 !important;
            min-height: calc(100vh - 70px);
        }
        
        .container-fluid {
            margin: 0 !important;
            padding: 0 15px !important;
        }
        
        h2:first-of-type {
            margin-top: 0 !important;
            padding-top: 0 !important;
        }
    </style>
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
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'dashboard' ? 'active' : ''; ?>" 
                   href="index.php?page=dashboard">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'add_violation' ? 'active' : ''; ?>" 
                   href="index.php?page=add_violation">
                    <i class="fas fa-plus-circle"></i> Add New Record
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'search_student' ? 'active' : ''; ?>" 
                   href="index.php?page=search_student">
                    <i class="fas fa-search"></i> Search Violations
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'all_violations' ? 'active' : ''; ?>" 
                   href="index.php?page=all_violations">
                    <i class="fas fa-list"></i> View All Violations
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'officer_appointments' ? 'active' : ''; ?>" 
                   href="index.php?page=officer_appointments">
                    <i class="fas fa-clipboard-list"></i> Appointments
                </a>
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