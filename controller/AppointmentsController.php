<?php
if (!isset($_SESSION['officer_id'])) {
  header("Location: index.php?page=login");
  exit();
}
include 'view/appointments.php';