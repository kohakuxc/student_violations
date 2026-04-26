<?php
class ViolationModel
{
    private $conn;

    private function isPgsql()
    {
        return $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql';
    }

    private function isEscalatedFalseCondition($alias = 'v')
    {
        if ($this->isPgsql()) {
            return "COALESCE({$alias}.is_escalated, FALSE) = FALSE";
        }

        return "ISNULL({$alias}.is_escalated, 0) = 0";
    }

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    public function addViolation($student_id, $officer_id, $violation_type, $description, $date_of_violation)
    {
        try {
            $this->conn->beginTransaction();

            $query = "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation)
                      VALUES (?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);
            $result = $stmt->execute([$student_id, $officer_id, $violation_type, $description, $date_of_violation]);

            if (!$result) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Failed to record violation'];
            }

            $escalationCount = 0;
            if ($this->isMinorViolationType((int) $violation_type)) {
                $escalationCount = $this->applyMinorEscalationRule((int) $student_id, (int) $officer_id, (string) $date_of_violation);
            }

            $this->conn->commit();

            $message = 'Violation recorded successfully';
            if ($escalationCount > 0) {
                $message .= '. Rule applied: 3 minor offenses converted to 1 Major Offense - Category A';
                if ($escalationCount > 1) {
                    $message .= ' (' . $escalationCount . ' times)';
                }
                $message .= '.';
            }

            return ['success' => true, 'message' => $message];

        } catch (PDOException $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Add Violation Error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Database error occurred'];
        }
    }

    private function isMinorViolationType($violation_type_id)
    {
        $query = "SELECT severity_level FROM violation_types WHERE violation_type_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([(int) $violation_type_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return !empty($row) && strtolower((string) $row['severity_level']) === 'minor';
    }

    private function getMajorCategoryATypeId()
    {
        if ($this->isPgsql()) {
            $query = "SELECT violation_type_id
                      FROM violation_types
                      WHERE LOWER(type_name) = LOWER(?)
                      ORDER BY violation_type_id ASC
                      LIMIT 1";
        } else {
            $query = "SELECT TOP (1) violation_type_id
                      FROM violation_types
                      WHERE LOWER(type_name) = LOWER(?)
                      ORDER BY violation_type_id ASC";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute(['Major Offense - Category A']);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (empty($row['violation_type_id'])) {
            throw new PDOException('Major Offense - Category A type not found in violation_types');
        }

        return (int) $row['violation_type_id'];
    }

    private function getConvertibleMinorViolationIds($student_id, $limit = 3)
    {
        $limit = (int) $limit;
        $student_id = (int) $student_id;

        if ($this->isPgsql()) {
            $query = "SELECT v.violation_id
                      FROM violations v
                      JOIN violation_types vt ON vt.violation_type_id = v.violation_type
                      WHERE v.student_id = ?
                                                AND " . $this->isEscalatedFalseCondition('v') . "
                        AND LOWER(vt.severity_level) = 'minor'
                      ORDER BY v.date_of_violation ASC, v.created_at ASC, v.violation_id ASC
                      LIMIT " . $limit;
        } else {
            $query = "SELECT TOP (" . $limit . ") v.violation_id
                      FROM violations v
                      JOIN violation_types vt ON vt.violation_type_id = v.violation_type
                      WHERE v.student_id = ?
                                                AND " . $this->isEscalatedFalseCondition('v') . "
                        AND LOWER(vt.severity_level) = 'minor'
                      ORDER BY v.date_of_violation ASC, v.created_at ASC, v.violation_id ASC";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->execute([$student_id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function ($row) {
            return (int) $row['violation_id'];
        }, $rows);
    }

    private function createEscalatedMajorViolation($student_id, $officer_id, $majorCategoryATypeId, $date_of_violation)
    {
        $majorDescription = 'Auto-escalation rule: Converted 3 minor offenses into 1 Major Offense - Category A.';

        if ($this->isPgsql()) {
            $stmt = $this->conn->prepare(
                "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation)
                 VALUES (?, ?, ?, ?, ?)
                 RETURNING violation_id"
            );
            $stmt->execute([
                (int) $student_id,
                (int) $officer_id,
                (int) $majorCategoryATypeId,
                $majorDescription,
                (string) $date_of_violation,
            ]);

            $newId = (int) $stmt->fetchColumn();
            if ($newId <= 0) {
                throw new PDOException('Failed to resolve auto-escalated major violation id');
            }

            return $newId;
        }

        $stmt = $this->conn->prepare(
            "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation)
             VALUES (?, ?, ?, ?, ?)"
        );
        $inserted = $stmt->execute([
            (int) $student_id,
            (int) $officer_id,
            (int) $majorCategoryATypeId,
            $majorDescription,
            (string) $date_of_violation,
        ]);

        if (!$inserted) {
            throw new PDOException('Failed to insert auto-escalated major offense');
        }

        $newId = (int) $this->conn->lastInsertId();
        if ($newId <= 0) {
            $idStmt = $this->conn->query("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS violation_id");
            $idRow = $idStmt ? $idStmt->fetch(PDO::FETCH_ASSOC) : null;
            $newId = isset($idRow['violation_id']) ? (int) $idRow['violation_id'] : 0;
        }

        if ($newId <= 0) {
            throw new PDOException('Failed to resolve auto-escalated major violation id');
        }

        return $newId;
    }

    private function markViolationsEscalated(array $violation_ids, $majorViolationId)
    {
        if (empty($violation_ids)) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($violation_ids), '?'));
        $query = "UPDATE violations
                  SET is_escalated = ?,
                      escalated_at = CURRENT_TIMESTAMP,
                      escalated_to_violation_id = ?
                  WHERE violation_id IN (" . $placeholders . ")";

        $escalatedValue = $this->isPgsql() ? true : 1;
        $stmt = $this->conn->prepare($query);
        return $stmt->execute(array_merge([$escalatedValue, (int) $majorViolationId], array_values($violation_ids)));
    }

    private function logEscalation($student_id, $officer_id, $majorViolationId, array $sourceViolationIds)
    {
        $stmt = $this->conn->prepare(
            "INSERT INTO violation_escalations (student_id, major_violation_id, created_by_officer_id, rule_code, created_at)
             VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)"
        );
        $ok = $stmt->execute([(int) $student_id, (int) $majorViolationId, (int) $officer_id, 'minor_3_to_major_a']);
        if (!$ok) {
            return false;
        }

        $escalationId = (int) $this->conn->lastInsertId();
        if ($this->isPgsql() && $escalationId <= 0) {
            $idStmt = $this->conn->query("SELECT currval(pg_get_serial_sequence('violation_escalations','escalation_id')) AS escalation_id");
            $idRow = $idStmt ? $idStmt->fetch(PDO::FETCH_ASSOC) : null;
            $escalationId = isset($idRow['escalation_id']) ? (int) $idRow['escalation_id'] : 0;
        }
        if (!$this->isPgsql() && $escalationId <= 0) {
            $idStmt = $this->conn->query("SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS escalation_id");
            $idRow = $idStmt ? $idStmt->fetch(PDO::FETCH_ASSOC) : null;
            $escalationId = isset($idRow['escalation_id']) ? (int) $idRow['escalation_id'] : 0;
        }

        if ($escalationId <= 0) {
            throw new PDOException('Failed to resolve escalation id');
        }

        $itemStmt = $this->conn->prepare(
            "INSERT INTO violation_escalation_items (escalation_id, source_violation_id)
             VALUES (?, ?)"
        );

        foreach ($sourceViolationIds as $sourceViolationId) {
            $itemOk = $itemStmt->execute([(int) $escalationId, (int) $sourceViolationId]);
            if (!$itemOk) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply escalation rule and return how many escalation events were created.
     *
     * @return int
     */
    private function applyMinorEscalationRule($student_id, $officer_id, $date_of_violation)
    {
        $majorCategoryATypeId = $this->getMajorCategoryATypeId();
        $escalationCount = 0;

        while (true) {
            $minorIds = $this->getConvertibleMinorViolationIds($student_id, 3);
            if (count($minorIds) < 3) {
                break;
            }

            $majorViolationId = $this->createEscalatedMajorViolation(
                (int) $student_id,
                (int) $officer_id,
                (int) $majorCategoryATypeId,
                (string) $date_of_violation
            );

            $marked = $this->markViolationsEscalated($minorIds, $majorViolationId);
            if (!$marked) {
                throw new PDOException('Failed to mark converted minor offenses as escalated');
            }

            $logged = $this->logEscalation((int) $student_id, (int) $officer_id, (int) $majorViolationId, $minorIds);
            if (!$logged) {
                throw new PDOException('Failed to log escalation linkage');
            }

            $escalationCount++;
        }

        return $escalationCount;
    }

    public function getViolationsByStudent($student_id)
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
                                                AND " . $this->isEscalatedFalseCondition('v') . "
                      ORDER BY v.date_of_violation DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute([$student_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Violations by Student Error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllViolations()
    {
        try {
            $query = "SELECT v.violation_id,
                             vt.type_name,
                             vt.severity_level,
                             v.description,
                             v.date_of_violation,
                             v.created_at,
                         COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                             o.name as officer_name
                      FROM violations v
                      JOIN students s ON v.student_id = s.student_id
                     LEFT JOIN student_information si ON s.student_id = si.student_id
                      JOIN officers o ON v.officer_id = o.officer_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      WHERE " . $this->isEscalatedFalseCondition('v') . "
                      ORDER BY v.created_at DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get All Violations Error: " . $e->getMessage());
            return [];
        }
    }

    public function getViolationCountByType($student_id)
    {
        try {
            // Now "violation_type" is a FK to violation_types.violation_type_id.
            // We count per severity_level.
            $query = "
            SELECT
                vt.severity_level,
                COUNT(*) as count
            FROM violations v
            LEFT JOIN violation_types vt
                ON vt.violation_type_id = v.violation_type
            WHERE v.student_id = ?
              AND " . $this->isEscalatedFalseCondition('v') . "
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

    public function getRecentViolations($limit = 4)
    {
        try {
            $limit = (int) $limit;
            if ($limit < 1)
                $limit = 4;

            $driver = $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'pgsql') {
                $query = "
                SELECT
                       v.violation_id,
                       v.description,
                       v.date_of_violation,
                       v.created_at,
                      COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                       o.name AS officer_name,
                       COALESCE(vt.severity_level, 'unknown') AS severity_level
                FROM violations v
                JOIN students s ON v.student_id = s.student_id
                  LEFT JOIN student_information si ON s.student_id = si.student_id
                JOIN officers o ON v.officer_id = o.officer_id
                LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                                WHERE " . $this->isEscalatedFalseCondition('v') . "
                ORDER BY v.created_at DESC
                LIMIT $limit
            ";
            } else {
                $query = "
                SELECT
                       TOP ($limit)
                       v.violation_id,
                       v.description,
                       v.date_of_violation,
                       v.created_at,
                      COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                       o.name AS officer_name,
                       COALESCE(vt.severity_level, 'unknown') AS severity_level
                FROM violations v
                JOIN students s ON v.student_id = s.student_id
                  LEFT JOIN student_information si ON s.student_id = si.student_id
                JOIN officers o ON v.officer_id = o.officer_id
                LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                                WHERE " . $this->isEscalatedFalseCondition('v') . "
                ORDER BY v.created_at DESC
            ";
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get Recent Violations Error: " . $e->getMessage());
            return [];
        }
    }

    public function getEscalationHistory($student_id = null)
    {
        try {
            $params = [];
            $where = '';

            if ($student_id !== null) {
                $where = ' WHERE e.student_id = ?';
                $params[] = (int) $student_id;
            }

            $query = "SELECT e.escalation_id,
                             e.student_id,
                             COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                             e.major_violation_id,
                             e.rule_code,
                             e.created_at AS escalated_at,
                             mv.date_of_violation AS major_date_of_violation,
                             mv.description AS major_description,
                             mo.name AS escalated_by_officer
                      FROM violation_escalations e
                      JOIN violations mv ON mv.violation_id = e.major_violation_id
                      JOIN students st ON st.student_id = e.student_id
                      LEFT JOIN student_information si ON si.student_id = st.student_id
                      LEFT JOIN officers mo ON mo.officer_id = e.created_by_officer_id" . $where . "
                      ORDER BY e.created_at DESC, e.escalation_id DESC";

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $historyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($historyRows)) {
                return [];
            }

            $itemsQuery = "SELECT i.escalation_id,
                                  i.source_violation_id,
                                  sv.date_of_violation,
                                  sv.description
                           FROM violation_escalation_items i
                           JOIN violations sv ON sv.violation_id = i.source_violation_id
                           ORDER BY i.escalation_id DESC, sv.date_of_violation ASC, sv.created_at ASC, sv.violation_id ASC";
            $itemsStmt = $this->conn->prepare($itemsQuery);
            $itemsStmt->execute();
            $itemRows = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

            $itemsByEscalation = [];
            foreach ($itemRows as $item) {
                $eid = (int) $item['escalation_id'];
                if (!isset($itemsByEscalation[$eid])) {
                    $itemsByEscalation[$eid] = [];
                }
                $itemsByEscalation[$eid][] = [
                    'source_violation_id' => (int) $item['source_violation_id'],
                    'date_of_violation' => $item['date_of_violation'],
                    'description' => $item['description'],
                ];
            }

            $history = [];
            foreach ($historyRows as $row) {
                $eid = (int) $row['escalation_id'];
                $row['source_violations'] = $itemsByEscalation[$eid] ?? [];
                $history[] = $row;
            }

            return $history;
        } catch (PDOException $e) {
            error_log("Get Escalation History Error: " . $e->getMessage());
            return [];
        }
    }
}
?>