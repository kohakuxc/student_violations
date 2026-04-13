<?php
/**
 * Student Authentication Model
 * Handles Microsoft 365 OAuth for students
 */

class StudentAuthModel
{
    private $conn;
    private $studentsTableColumns = null;

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
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ],
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
            $curlError = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response === false) {
                error_log('Token Exchange cURL Error: ' . $curlError);
                return ['success' => false, 'message' => 'Token request failed: ' . $curlError];
            }

            if ($httpCode !== 200) {
                error_log("Token Exchange Error: HTTP $httpCode - $response");
                $errorData = json_decode($response, true);
                $oauthError = $errorData['error'] ?? 'unknown_error';
                $oauthDescription = $errorData['error_description'] ?? 'Failed to obtain access token';

                if ($oauthError === 'invalid_grant') {
                    return [
                        'success' => false,
                        'error_code' => 'invalid_grant',
                        'message' => 'Sign-in session expired. Please click Student login again to get a fresh authorization code.'
                    ];
                }

                return [
                    'success' => false,
                    'message' => $oauthError . ': ' . $oauthDescription
                ];
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

            // Some Azure accounts do not populate `mail`; use UPN as fallback.
            if (empty($profile['mail']) && !empty($profile['userPrincipalName'])) {
                $profile['mail'] = $profile['userPrincipalName'];
            }

            if (empty($profile['mail'])) {
                error_log("No usable email in profile: " . $response);
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
        return strpos(strtolower(trim($email)), strtolower(ALLOWED_EMAIL_DOMAIN)) !== false;
    }

    /**
     * Find student by email
     */
    public function findStudentByEmail($email)
    {
        try {
            $email = trim(strtolower($email));

            $query = "SELECT student_id, name, student_number, email 
                      FROM students 
                      WHERE LOWER(LTRIM(RTRIM(email))) = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$email]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Find Student by Email Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find student by student number.
     */
    public function getStudentByNumber($student_number)
    {
        try {
            $query = "SELECT student_id, name, student_number, email
                      FROM students
                      WHERE student_number = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_number]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Student by Number Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Find student by Microsoft account ID.
     */
    public function findStudentByMicrosoftId($microsoft_id)
    {
        try {
            $query = "SELECT student_id, name, student_number, email
                      FROM students
                      WHERE microsoft_id = ?";
            $stmt = $this->conn->prepare($query);
            $stmt->execute([$microsoft_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Find Student by Microsoft ID Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Return a normalized list of columns available on the students table.
     */
    private function getStudentsTableColumns()
    {
        if ($this->studentsTableColumns !== null) {
            return $this->studentsTableColumns;
        }

        try {
            $query = "SELECT COLUMN_NAME
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_NAME = 'students'";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $columnName) {
                $columns[strtolower(trim($columnName))] = true;
            }

            $this->studentsTableColumns = $columns;
        } catch (PDOException $e) {
            error_log("Get Students Table Columns Error: " . $e->getMessage());
            $this->studentsTableColumns = [];
        }

        return $this->studentsTableColumns;
    }

    /**
     * Check whether a students column exists.
     */
    private function studentsColumnExists($columnName)
    {
        $columns = $this->getStudentsTableColumns();
        return isset($columns[strtolower(trim($columnName))]);
    }

    /**
     * Generate a fallback student number for first-time Microsoft sign-ins.
     */
    private function generateAutoStudentNumber($email, $microsoft_id)
    {
        $seed = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $microsoft_id));

        if ($seed === '') {
            $localPart = strstr((string) $email, '@', true);
            $seed = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $localPart));
        }

        if ($seed === '') {
            $seed = strtoupper(bin2hex(random_bytes(4)));
        }

        return 'AUTO-' . substr($seed, 0, 12);
    }

    /**
     * Create a new student row from a Microsoft profile.
     *
     * Note: age is not available from Microsoft Graph by default, so it must
     * be collected later if your database requires it.
     */
    public function createStudentFromMicrosoftProfile(array $profile, $access_token)
    {
        try {
            $email = strtolower(trim($profile['mail'] ?? $profile['userPrincipalName'] ?? ''));
            $name = trim($profile['displayName'] ?? '');
            $microsoft_id = trim($profile['id'] ?? '');

            if ($email === '' || $microsoft_id === '') {
                return ['success' => false, 'message' => 'Microsoft profile is missing required identity fields'];
            }

            if ($name === '') {
                $name = trim(($profile['givenName'] ?? '') . ' ' . ($profile['surname'] ?? ''));
            }

            if ($name === '') {
                $name = $email;
            }

            $student_number = $this->generateAutoStudentNumber($email, $microsoft_id);
            $encrypted_token = base64_encode($access_token);

            $insertColumns = ['name', 'student_number', 'email'];
            $insertValues = [$name, $student_number, $email];

            if ($this->studentsColumnExists('microsoft_id')) {
                $insertColumns[] = 'microsoft_id';
                $insertValues[] = $microsoft_id;
            }

            if ($this->studentsColumnExists('oauth_token')) {
                $insertColumns[] = 'oauth_token';
                $insertValues[] = $encrypted_token;
            }

            if ($this->studentsColumnExists('last_login')) {
                $insertColumns[] = 'last_login';
            }

            // Avoid accidental student_number collision.
            $counter = 1;
            while ($this->getStudentByNumber($student_number)) {
                $student_number = $this->generateAutoStudentNumber($email, $microsoft_id . '-' . $counter);
                $counter++;
                if ($counter > 5) {
                    $student_number = 'AUTO-' . strtoupper(bin2hex(random_bytes(6)));
                    break;
                }
            }

            $placeholders = array_fill(0, count($insertValues), '?');

            if ($this->studentsColumnExists('last_login')) {
                $query = "INSERT INTO students (" . implode(', ', $insertColumns) . ")
                          VALUES (" . implode(', ', $placeholders) . ", CURRENT_TIMESTAMP)";
            } else {
                $query = "INSERT INTO students (" . implode(', ', $insertColumns) . ")
                          VALUES (" . implode(', ', $placeholders) . ")";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($insertValues);

            $student = $this->findStudentByMicrosoftId($microsoft_id);
            if (!$student) {
                $student = $this->findStudentByEmail($email);
            }

            if (!$student) {
                return ['success' => false, 'message' => 'Student created but could not be reloaded'];
            }

            return ['success' => true, 'student' => $student, 'created' => true];
        } catch (PDOException $e) {
            error_log("Create Student From Microsoft Profile Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create student account'];
        }
    }

    /**
     * Find a student by Microsoft account or email; create one if missing.
     */
    public function findOrCreateStudentByMicrosoftProfile(array $profile, $access_token)
    {
        $email = strtolower(trim($profile['mail'] ?? $profile['userPrincipalName'] ?? ''));
        $microsoft_id = trim($profile['id'] ?? '');
        $name = trim($profile['displayName'] ?? '');

        if ($name === '') {
            $name = trim(($profile['givenName'] ?? '') . ' ' . ($profile['surname'] ?? ''));
        }

        if ($name === '') {
            $name = $email;
        }

        $student = null;

        if ($microsoft_id !== '') {
            $student = $this->findStudentByMicrosoftId($microsoft_id);
        }

        if (!$student && $email !== '') {
            $student = $this->findStudentByEmail($email);
        }

        if (!$student) {
            return $this->createStudentFromMicrosoftProfile($profile, $access_token);
        }

        $updateResult = $this->syncStudentOAuthData(
            $student['student_id'],
            $name,
            $email,
            $microsoft_id,
            $access_token
        );

        if (!$updateResult['success']) {
            return $updateResult;
        }

        $student = $this->findStudentByMicrosoftId($microsoft_id);
        if (!$student && $email !== '') {
            $student = $this->findStudentByEmail($email);
        }

        return [
            'success' => true,
            'student' => $student,
            'created' => false
        ];
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
                      SET microsoft_id = ?, oauth_token = ?, last_login = CURRENT_TIMESTAMP
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
     * Keep the local student row synchronized with Microsoft profile data.
     */
    public function syncStudentOAuthData($student_id, $name, $email, $microsoft_id, $access_token)
    {
        try {
            $encrypted_token = base64_encode($access_token);

            $setParts = ['name = ?', 'email = ?'];
            $params = [$name, $email];

            if ($this->studentsColumnExists('microsoft_id')) {
                $setParts[] = 'microsoft_id = ?';
                $params[] = $microsoft_id;
            }

            if ($this->studentsColumnExists('oauth_token')) {
                $setParts[] = 'oauth_token = ?';
                $params[] = $encrypted_token;
            }

            if ($this->studentsColumnExists('last_login')) {
                $setParts[] = 'last_login = CURRENT_TIMESTAMP';
            }

            $query = "UPDATE students
                      SET " . implode(', ', $setParts) . "
                      WHERE student_id = ?";
            $stmt = $this->conn->prepare($query);
            $params[] = $student_id;
            $result = $stmt->execute($params);

            if ($result) {
                return ['success' => true];
            }

            return ['success' => false, 'message' => 'Failed to update student'];
        } catch (PDOException $e) {
            error_log("Sync Student OAuth Data Error: " . $e->getMessage());
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