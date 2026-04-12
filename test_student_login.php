<?php
session_start();

// Manually set student session for testing
$_SESSION['student_id'] = 1; // Use an existing student_id from your database
$_SESSION['student_name'] = 'Juan Dela Cruz'; // Use actual student name
$_SESSION['student_email'] = 'juan@fairview.sti.edu.ph';
$_SESSION['user_type'] = 'student';

echo "✅ Student session created!<br>";
echo "Student ID: " . $_SESSION['student_id'] . "<br>";
echo "Student Name: " . $_SESSION['student_name'] . "<br>";
echo "<br>";
echo '<a href="index.php?page=student_dashboard">Go to Dashboard</a>';
?>