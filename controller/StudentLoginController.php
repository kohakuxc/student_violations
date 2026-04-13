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

function redirectStudentError($message)
{
    header('Location: index.php?page=login&tab=student-login&student_error=' . urlencode($message));
    exit();
}

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
            $email = strtolower(trim($profile['mail'] ?? $profile['userPrincipalName'] ?? ''));
            $microsoft_id = $profile['id'] ?? '';

            // Validate email domain
            if (!$studentAuthModel->isValidEmailDomain($email)) {
                redirectStudentError('Only @fairview.sti.edu.ph email addresses are allowed.');
            } else {
                $studentResult = $studentAuthModel->findOrCreateStudentByMicrosoftProfile($profile, $tokenResult['access_token']);

                if (!empty($studentResult['success']) && !empty($studentResult['student'])) {
                    $student = $studentResult['student'];

                    // Create student session
                    $sessionResult = $studentAuthModel->createStudentSession(
                        $student['student_id'],
                        $student['name'],
                        $student['email']
                    );

                    if ($sessionResult['success']) {
                        // Clear OAuth session data
                        unset($_SESSION['oauth_state']);
                        unset($_SESSION['oauth_code_verifier']);

                        header("Location: index.php?page=student_dashboard");
                        exit();
                    }

                    redirectStudentError('Session creation failed. Please try again.');
                } else {
                    redirectStudentError($studentResult['message'] ?? 'Failed to provision student account.');
                }
            }
        } else {
            redirectStudentError('Failed to retrieve user profile from Microsoft 365.');
        }
    } else {
        if (($tokenResult['error_code'] ?? '') === 'invalid_grant') {
            unset($_SESSION['oauth_state']);
            unset($_SESSION['oauth_code_verifier']);
            redirectStudentError($tokenResult['message']);
        }

        redirectStudentError($tokenResult['message']);
    }
}

// Step 3: Handle OAuth errors
if (isset($_GET['error'])) {
    redirectStudentError('Authentication failed: ' . ($_GET['error_description'] ?? $_GET['error']));
}

include 'view/login.php';
?>