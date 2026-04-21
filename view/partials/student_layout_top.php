<?php
$pageTitle = $pageTitle ?? 'Student Portal';
$styleVersion = @filemtime(__DIR__ . '/../../css/style.css') ?: time();
$appointmentsStyleVersion = @filemtime(__DIR__ . '/../../css/appointments.css') ?: time();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo htmlspecialchars($pageTitle); ?> - Student Violations System</title>
    
    <!-- Your custom CSS -->
    <link rel="stylesheet" href="css/style.css?v=<?php echo urlencode((string) $styleVersion); ?>" />
    
    <!-- Bootstrap CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Appointment System CSS -->
    <link rel="stylesheet" href="css/appointments.css?v=<?php echo urlencode((string) $appointmentsStyleVersion); ?>" />
</head>

<body>
    <div class="app-shell">
        <!-- Sidebar Navigation (OFF-CANVAS) -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-brand">
                <button class="sidebar-hamburger" type="button" id="sidebarClose" aria-label="Close menu">
                    ☰
                </button>
            </div>

            <nav class="sidebar-nav">
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'student_dashboard' ? 'active' : ''; ?>" 
                   href="index.php?page=student_dashboard">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="sidebar-link <?php echo ($_GET['page'] ?? null) === 'student_appointments' ? 'active' : ''; ?>" 
                   href="index.php?page=student_appointments">
                    <i class="fas fa-calendar-alt"></i> Appointments
                </a>
            </nav>
        </aside>

        <!-- Sidebar Backdrop (Dark overlay) -->
        <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

        <div class="app-main">
            <header class="topbar">
                <!-- Hamburger Menu Button -->
                <button class="sidebar-hamburger" type="button" id="sidebarToggle" aria-label="Toggle menu">
                    ☰
                </button>

                <a class="topbar-title" href="index.php?page=student_dashboard">Student Violations Portal</a>

                <div class="topbar-right">
                    <?php if (!empty($_SESSION['student_name'])): ?>
                        <span class="welcome-text"><b><?php echo htmlspecialchars($_SESSION['student_name']); ?></b></span>
                    <?php endif; ?>
                    <a href="index.php?page=logout" class="btn btn-small btn-danger">Logout</a>
                </div>
            </header>

            <main class="app-content">