<?php
/**
 * All Violations Controller
 * Displays all violations in the database
 */

if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php?page=login");
    exit();
}

include 'model/ViolationModel.php';

$violationModel = new ViolationModel();
$violations = $violationModel->getAllViolations();

include 'view/all_violations.php';
?>