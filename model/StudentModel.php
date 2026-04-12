<?php
/**
 * Student Model
 * Handles all student-related database operations
 */

class StudentModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    /**
     * Search student by name or student number
     */
    public function searchStudent($search_term)
    {
        try {
            $search = "%$search_term%";

            $query = "SELECT student_id, name, student_number, email 
                      FROM students 
                      WHERE name LIKE ? OR student_number LIKE ?";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$search, $search]);

            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Search Student Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get student by ID
     */
    public function getStudentById($student_id)
    {
        try {
            $query = "SELECT student_id, name, student_number, email, created_at 
                      FROM students 
                      WHERE student_id = ?";

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
     * Create new student
     */
    public function createStudent($name, $student_number, $email = null)
    {
        try {
            $query = "INSERT INTO students (name, student_number, email) 
                      VALUES (?, ?, ?)";

            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$name, $student_number, $email]);

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
            $query = "SELECT student_id, name, student_number, email 
                      FROM students 
                      ORDER BY name ASC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get All Students Error: " . $e->getMessage());
            return [];
        }
    }
}
?>