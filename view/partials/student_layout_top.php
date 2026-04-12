<?php
$pageTitle = $pageTitle ?? 'Student Portal';
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
        <div class="app-main">
            <header class="topbar">
                <a class="topbar-title" href="index.php?page=student_dashboard">Student Violations Portal</a>

                <div class="topbar-right">
                    <?php if (!empty($_SESSION['student_name'])): ?>
                        <span class="welcome-text"><b><?php echo htmlspecialchars($_SESSION['student_name']); ?></b></span>
                    <?php endif; ?>
                    <a href="index.php?page=logout" class="btn btn-small btn-danger">Logout</a>
                </div>
            </header>

            <main class="app-content"></main>