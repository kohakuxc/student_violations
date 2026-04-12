<?php
/**
 * Student Login Controller
 * Handles Microsoft 365 OAuth authentication
 */

if (isset($_SESSION['student_id'])) {
    header("Location: index.php?page=student_dashboard");
    exit();
}

include 'model/StudentAuthModel.php';

$studentAuthModel = new StudentAuthModel();
$error = "";
$login_url = "";

// Step 1: Generate login URL
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['code']) && !isset($_GET['error'])) {
    $login_url = $studentAuthModel->generateAuthorizationUrl();
}

// Step 2: Handle OAuth callback
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for token
    $tokenResult = $studentAuthModel->exchangeCodeForToken($code);

    if ($tokenResult['success']) {
        // Get user profile from Microsoft Graph
        $profile = $studentAuthModel->getUserProfile($tokenResult['access_token']);

        if ($profile) {
            $email = $profile['mail'];
            $microsoft_id = $profile['id'];

            // Validate email domain
            if (!$studentAuthModel->isValidEmailDomain($email)) {
                $error = "❌ Only @fairview.sti.edu.ph email addresses are allowed.";
            } else {
                // Find student by email
                $student = $studentAuthModel->findStudentByEmail($email);

                if ($student) {
                    // Update student with Microsoft OAuth data
                    $updateResult = $studentAuthModel->updateStudentOAuthData(
                        $student['student_id'],
                        $microsoft_id,
                        $tokenResult['access_token']
                    );

                    if ($updateResult['success']) {
                        // Create student session
                        $sessionResult = $studentAuthModel->createStudentSession(
                            $student['student_id'],
                            $student['name'],
                            $email
                        );

                        if ($sessionResult['success']) {
                            // Clear OAuth session data
                            unset($_SESSION['oauth_state']);
                            unset($_SESSION['oauth_code_verifier']);

                            header("Location: index.php?page=student_dashboard");
                            exit();
                        } else {
                            $error = "❌ Session creation failed.";
                        }
                    } else {
                        $error = "❌ Failed to update authentication data.";
                    }
                } else {
                    $error = "❌ Student account not found. Please contact the administration.";
                }
            }
        } else {
            $error = "❌ Failed to retrieve user profile from Microsoft 365.";
        }
    } else {
        $error = "❌ " . $tokenResult['message'];
    }
}

// Step 3: Handle OAuth errors
if (isset($_GET['error'])) {
    $error = "❌ Authentication failed: " . htmlspecialchars($_GET['error_description'] ?? $_GET['error']);
}

include 'view/student_login.php';
?>