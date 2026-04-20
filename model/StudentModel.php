<?php
/**
 * Student Model
 * Handles all student-related database operations
 */

class StudentModel
{
    private $conn;
    private $studentsTableColumns = null;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    private function driverName()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    private function isPgsql()
    {
        return $this->driverName() === 'pgsql';
    }

    private function hasStudentInformationTable()
    {
        try {
            $query = "SELECT 1
                      FROM INFORMATION_SCHEMA.COLUMNS
                      WHERE TABLE_NAME = 'student_information'
                      LIMIT 1";

            if (!$this->isPgsql()) {
                $query = "SELECT TOP 1 1
                          FROM INFORMATION_SCHEMA.COLUMNS
                          WHERE TABLE_NAME = 'student_information'";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function normalizedNameSql()
    {
        if (!$this->hasStudentInformationTable()) {
            return "''";
        }

        return "COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '')";
    }

    private function normalizedStudentNumberSql()
    {
        if (!$this->hasStudentInformationTable()) {
            return "''";
        }

        return "COALESCE(NULLIF(si.student_num, ''), '')";
    }

    private function normalizedEmailSql()
    {
        if (!$this->hasStudentInformationTable()) {
            return "''";
        }

        return "COALESCE(NULLIF(si.email, ''), '')";
    }

    private function fromStudentsSql()
    {
        if ($this->hasStudentInformationTable()) {
            return ' FROM students s LEFT JOIN student_information si ON si.student_id = s.student_id ';
        }

        return ' FROM students s ';
    }

    private function baseStudentSelectSql()
    {
        return "SELECT s.student_id,
                       " . $this->normalizedNameSql() . " AS name,
                       " . $this->normalizedStudentNumberSql() . " AS student_number,
                       " . $this->normalizedEmailSql() . " AS email,
                       s.created_at" . $this->fromStudentsSql();
    }

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
                $columns[strtolower(trim((string) $columnName))] = true;
            }

            $this->studentsTableColumns = $columns;
        } catch (PDOException $e) {
            $this->studentsTableColumns = [];
        }

        return $this->studentsTableColumns;
    }

    private function studentsColumnExists($columnName)
    {
        $columns = $this->getStudentsTableColumns();
        return isset($columns[strtolower(trim((string) $columnName))]);
    }

    private function splitName($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return ['first_name' => '', 'last_name' => ''];
        }

        if (strpos($name, ',') !== false) {
            $parts = explode(',', $name, 2);
            return [
                'last_name' => trim((string) ($parts[0] ?? '')),
                'first_name' => trim((string) preg_replace('/\\s*\\(.*\\)$/', '', (string) ($parts[1] ?? ''))),
            ];
        }

        $parts = preg_split('/\s+/', $name, 2);
        return [
            'first_name' => trim((string) ($parts[0] ?? '')),
            'last_name' => trim((string) ($parts[1] ?? '')),
        ];
    }

    /**
     * Extract the 6-digit student code from an email address.
     * Example: garcera.295912@fairview.sti.edu.ph => 295912 => lord of the freaks btw
     */
    public function getStudentCodeFromEmail($email, $fallback = '')
    {
        $email = strtolower(trim((string) $email));
        if ($email === '') {
            return (string) $fallback;
        }

        $local_part = strstr($email, '@', true);
        if ($local_part === false) {
            $local_part = $email;
        }

        if (preg_match('/(\d{6})/', $local_part, $matches)) {
            return $matches[1];
        }

        return (string) $fallback;
    }

    private function normalizeSearchTerm($search_term)
    {
        $search_term = strtolower(trim((string) $search_term));
        return preg_replace('/\s+/', ' ', $search_term);
    }

    private function scoreStudentMatch(array $student, $search_term)
    {
        $search_term = $this->normalizeSearchTerm($search_term);
        if ($search_term === '') {
            return 0;
        }

        $name = $this->normalizeSearchTerm($student['name'] ?? '');
        $student_number = $this->normalizeSearchTerm($student['student_number'] ?? '');
        $email = $this->normalizeSearchTerm($student['email'] ?? '');
        $email_local = strpos($email, '@') !== false ? substr($email, 0, strpos($email, '@')) : $email;

        $score = 0;

        if ($name === $search_term) {
            $score += 120;
        }

        if ($student_number === $search_term) {
            $score += 150;
        }

        if ($email_local === $search_term) {
            $score += 145;
        }

        if (strpos($student_number, $search_term) !== false) {
            $score += 110;
        }

        if (strpos($email_local, $search_term) !== false) {
            $score += 105;
        }

        if (strpos($email, $search_term) !== false) {
            $score += 95;
        }

        if (strpos($name, $search_term) !== false) {
            $score += 80;
        }

        foreach (preg_split('/\s+/', $name) as $part) {
            if ($part !== '' && $part === $search_term) {
                $score += 100;
                break;
            }
        }

        return $score;
    }

    /**
     * Search students by name, student number, or email.
     * Returns matches ranked by relevance.
     */
    public function searchStudents($search_term, $limit = 8)
    {
        try {
            $search_term = trim((string) $search_term);
            if ($search_term === '') {
                return [];
            }

            $like = '%' . strtolower($search_term) . '%';

            $query = $this->baseStudentSelectSql() .
                " WHERE LOWER(" . $this->normalizedNameSql() . ") LIKE ?
                      OR LOWER(" . $this->normalizedStudentNumberSql() . ") LIKE ?
                      OR LOWER(" . $this->normalizedEmailSql() . ") LIKE ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$like, $like, $like]);

            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!$students) {
                return [];
            }

            foreach ($students as &$student) {
                $student['_score'] = $this->scoreStudentMatch($student, $search_term);
            }
            unset($student);

            usort($students, function ($left, $right) {
                if ($left['_score'] === $right['_score']) {
                    return strcmp(strtolower($left['name'] ?? ''), strtolower($right['name'] ?? ''));
                }

                return $right['_score'] <=> $left['_score'];
            });

            if ($limit > 0) {
                $students = array_slice($students, 0, (int) $limit);
            }

            foreach ($students as &$student) {
                unset($student['_score']);
            }
            unset($student);

            return $students;

        } catch (PDOException $e) {
            error_log("Search Students Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Search student by name or student number
     */
    public function searchStudent($search_term)
    {
        $students = $this->searchStudents($search_term, 1);
        return $students[0] ?? null;
    }

    /**
     * Find the best student match for officers recording violations.
     */
    public function findStudentByLookup($search_term)
    {
        return $this->searchStudent($search_term);
    }

    /**
     * Get student by ID
     */
    public function getStudentById($student_id)
    {
        try {
            $query = $this->baseStudentSelectSql() . " WHERE s.student_id = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Student Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get student by student number
     */
    public function getStudentByNumber($student_number)
    {
        try {
            $query = $this->baseStudentSelectSql() .
                " WHERE " . $this->normalizedStudentNumberSql() . " = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_number]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Student by Number Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create new student
     */
    public function createStudent($name, $student_number, $email = null)
    {
        try {
            $name = trim((string) $name);
            $email = $email !== null ? trim((string) $email) : null;
            $derivedNumber = $this->getStudentCodeFromEmail($email ?? '', $student_number);
            $student_number = trim((string) ($derivedNumber !== '' ? $derivedNumber : $student_number));

            if ($this->hasStudentInformationTable()) {
                $query = "INSERT INTO students DEFAULT VALUES";
                $stmt = $this->conn->prepare($query);
                $result = $stmt->execute();
            } else {
                $insertColumns = [];
                $insertValues = [];
                $placeholders = [];

                if ($this->studentsColumnExists('name')) {
                    $insertColumns[] = 'name';
                    $insertValues[] = $name;
                    $placeholders[] = '?';
                }

                if ($this->studentsColumnExists('student_number')) {
                    $insertColumns[] = 'student_number';
                    $insertValues[] = $student_number;
                    $placeholders[] = '?';
                }

                if ($this->studentsColumnExists('email')) {
                    $insertColumns[] = 'email';
                    $insertValues[] = $email;
                    $placeholders[] = '?';
                }

                if (empty($insertColumns)) {
                    $query = "INSERT INTO students DEFAULT VALUES";
                    $stmt = $this->conn->prepare($query);
                    $result = $stmt->execute();
                } else {
                    $query = "INSERT INTO students (" . implode(', ', $insertColumns) . ")
                              VALUES (" . implode(', ', $placeholders) . ")";
                    $stmt = $this->conn->prepare($query);
                    $result = $stmt->execute($insertValues);
                }
            }

            if ($result && $this->hasStudentInformationTable()) {
                $student_id = (int) $this->conn->lastInsertId();
                $parts = $this->splitName($name);

                if ($this->isPgsql()) {
                    $sync = $this->conn->prepare(
                        "INSERT INTO student_information (student_id, last_name, first_name, student_num, email)
                         VALUES (?, ?, ?, ?, ?)
                         ON CONFLICT (student_id)
                         DO UPDATE SET last_name = EXCLUDED.last_name,
                                       first_name = EXCLUDED.first_name,
                                       student_num = EXCLUDED.student_num,
                                       email = EXCLUDED.email"
                    );
                    $sync->execute([
                        $student_id,
                        $parts['last_name'] ?? '',
                        $parts['first_name'] ?? '',
                        $student_number,
                        $email,
                    ]);
                }
            }

            if ($result) {
                return ['success' => true, 'message' => 'Student added successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to add student'];
            }

        } catch (PDOException $e) {
            error_log("Create Student Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Student number already exists'];
        }
    }

    /**
     * Get all students
     */
    public function getAllStudents()
    {
        try {
            $query = $this->baseStudentSelectSql() . " ORDER BY name ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get All Students Error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get student with formatted name (for appointments)
     * Splits 'name' into 'first_name' and 'last_name'
     */
    public function getStudentByIdForAppointments($student_id)
    {
        try {
            $query = $this->baseStudentSelectSql() . " WHERE s.student_id = ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);

            $student = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($student && isset($student['name'])) {
                $split = $this->splitName($student['name']);
                $student['first_name'] = $split['first_name'] ?? '';
                $student['last_name'] = $split['last_name'] ?? '';
            }
            
            return $student;

        } catch (PDOException $e) {
            error_log("Get Student for Appointments Error: " . $e->getMessage());
            return null;
        }
    }
}
?>