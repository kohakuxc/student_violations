<?php
/**
 * Student Authentication Model
 * Handles Microsoft 365 OAuth for students
 */

class StudentAuthModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    /**
     * Generate OAuth authorization URL
     * Uses PKCE for security
     */
    public function generateAuthorizationUrl()
    {
        try {
            // Generate random state for CSRF protection
            $state = bin2hex(random_bytes(32));
            $_SESSION['oauth_state'] = $state;

            // Generate PKCE code challenge
            $codeVerifier = bin2hex(random_bytes(32));
            $_SESSION['oauth_code_verifier'] = $codeVerifier;
            $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

            // Build authorization URL
            $authUrl = MICROSOFT_AUTH_URL . '?' . http_build_query([
                'client_id' => MICROSOFT_CLIENT_ID,
                'redirect_uri' => MICROSOFT_REDIRECT_URI,
                'response_type' => 'code',
                'scope' => MICROSOFT_SCOPES,
                'state' => $state,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => 'S256',
                'prompt' => 'select_account' // Allow user to select account
            ]);

            return $authUrl;
        } catch (Exception $e) {
            error_log("Generate Auth URL Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken($code)
    {
        try {
            if (empty($code)) {
                return ['success' => false, 'message' => 'Authorization code not provided'];
            }

            // Verify state parameter
            if (empty($_SESSION['oauth_state']) || $_SESSION['oauth_state'] !== ($_GET['state'] ?? null)) {
                return ['success' => false, 'message' => 'Invalid state parameter. Possible CSRF attack.'];
            }

            $codeVerifier = $_SESSION['oauth_code_verifier'] ?? null;
            if (empty($codeVerifier)) {
                return ['success' => false, 'message' => 'Code verifier not found in session'];
            }

            // Exchange code for token
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => MICROSOFT_TOKEN_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id' => MICROSOFT_CLIENT_ID,
                    'client_secret' => MICROSOFT_CLIENT_SECRET,
                    'code' => $code,
                    'redirect_uri' => MICROSOFT_REDIRECT_URI,
                    'grant_type' => 'authorization_code',
                    'code_verifier' => $codeVerifier,
                    'scope' => MICROSOFT_SCOPES
                ]),
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Token Exchange Error: HTTP $httpCode - $response");
                return ['success' => false, 'message' => 'Failed to obtain access token'];
            }

            $tokenData = json_decode($response, true);
            if (!isset($tokenData['access_token'])) {
                error_log("No access token in response: " . $response);
                return ['success' => false, 'message' => 'Invalid token response'];
            }

            return [
                'success' => true,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_in' => $tokenData['expires_in'] ?? 3600
            ];
        } catch (Exception $e) {
            error_log("Token Exchange Exception: " . $e->getMessage());
            return ['success' => false, 'message' => 'Token exchange failed'];
        }
    }

    /**
     * Get user profile from Microsoft Graph
     */
    public function getUserProfile($accessToken)
    {
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => MICROSOFT_GRAPH_URL,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT => 10
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                error_log("Get Profile Error: HTTP $httpCode - $response");
                return null;
            }

            $profile = json_decode($response, true);
            
            if (!isset($profile['mail'])) {
                error_log("No email in profile: " . $response);
                return null;
            }

            return $profile;
        } catch (Exception $e) {
            error_log("Get Profile Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate email domain
     */
    public function isValidEmailDomain($email)
    {
        return strpos($email, ALLOWED_EMAIL_DOMAIN) !== false;
    }

    /**
     * Find student by email
     */
    public function findStudentByEmail($email)
    {
        try {
            $query = "SELECT student_id, name, student_number, email 
                      FROM students 
                      WHERE email = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Find Student by Email Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update student with Microsoft OAuth data
     */
    public function updateStudentOAuthData($student_id, $microsoft_id, $access_token)
    {
        try {
            // Encrypt token (basic encryption - use stronger in production)
            $encrypted_token = base64_encode($access_token);

            $query = "UPDATE students 
                      SET microsoft_id = ?, oauth_token = ?, last_login = GETDATE()
                      WHERE student_id = ?";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$microsoft_id, $encrypted_token, $student_id]);

            if ($result) {
                return ['success' => true];
            } else {
                return ['success' => false, 'message' => 'Failed to update student'];
            }
        } catch (PDOException $e) {
            error_log("Update Student OAuth Data Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error'];
        }
    }

    /**
     * Create student session
     */
    public function createStudentSession($student_id, $name, $email)
    {
        try {
            $_SESSION[STUDENT_SESSION_KEY] = $student_id;
            $_SESSION[STUDENT_NAME_KEY] = $name;
            $_SESSION[STUDENT_EMAIL_KEY] = $email;
            $_SESSION['session_start'] = time();
            $_SESSION['user_type'] = 'student'; // Distinguish from officer

            return ['success' => true];
        } catch (Exception $e) {
            error_log("Create Student Session Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Session creation failed'];
        }
    }

    /**
     * Get student violations
     */
    public function getStudentViolations($student_id)
    {
        try {
            $query = "SELECT v.violation_id,
                             vt.type_name,
                             vt.severity_level,
                             v.description,
                             v.date_of_violation,
                             v.created_at,
                             o.name as officer_name
                      FROM violations v
                      JOIN officers o ON v.officer_id = o.officer_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      WHERE v.student_id = ?
                      ORDER BY v.date_of_violation DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Student Violations Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get violation count by severity for student
     */
    public function getViolationCountByType($student_id)
    {
        try {
            $query = "
                SELECT
                    COALESCE(vt.severity_level, 'none') as severity_level,
                    COUNT(*) as count
                FROM violations v
                LEFT JOIN violation_types vt ON vt.violation_type_id = v.violation_type
                WHERE v.student_id = ?
                GROUP BY vt.severity_level
                ORDER BY
                    CASE vt.severity_level
                        WHEN 'major' THEN 1
                        WHEN 'moderate' THEN 2
                        WHEN 'minor' THEN 3
                        ELSE 4
                    END
            ";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Violation Count Error: " . $e->getMessage());
            return [];
        }
    }
}
?>