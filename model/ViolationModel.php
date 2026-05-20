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
        include __DIR__ . '/../config/db_connection.php';
        $this->conn = $conn;
    }

    public function addViolation($student_id, $officer_id, $violation_type_id, $description, $date_of_violation, $is_self_harm = false)
    {
        try {
            $dupSql = $this->isPgsql()
                ? "SELECT violation_id FROM violations
                    WHERE student_id = ?
                      AND officer_id = ?
                      AND violation_type = ?
                      AND description = ?
                      AND date_of_violation = ?
                      AND created_at >= (now() - interval '2 minutes')
                    ORDER BY created_at DESC
                    LIMIT 1"
                : "SELECT TOP 1 violation_id FROM violations
                    WHERE student_id = ?
                      AND officer_id = ?
                      AND violation_type = ?
                      AND description = ?
                      AND date_of_violation = ?
                      AND created_at >= DATEADD(minute, -2, GETDATE())
                    ORDER BY created_at DESC";
            $dupStmt = $this->conn->prepare($dupSql);
            $dupStmt->execute([
                (int) $student_id,
                (int) $officer_id,
                (int) $violation_type_id,
                $description,
                $date_of_violation,
            ]);
            $existingId = $dupStmt->fetchColumn();
            if ($existingId) {
                return [
                    'success' => true,
                    'message' => 'Violation recorded successfully.',
                    'violation_id' => (int) $existingId,
                ];
            }

            $this->conn->beginTransaction();

            $insertSql = "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation, is_self_harm)
                          VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($insertSql);
            $stmt->execute([
                (int) $student_id,
                (int) $officer_id,
                (int) $violation_type_id,
                $description,
                $date_of_violation,
                $this->isPgsql() ? (bool) $is_self_harm : ($is_self_harm ? 1 : 0),
            ]);

            $newViolationId = (int) $this->conn->lastInsertId();

            // Determine severity of the violation type
            $sevStmt = $this->conn->prepare("SELECT severity_level FROM violation_types WHERE violation_type_id = ?");
            $sevStmt->execute([(int) $violation_type_id]);
            $severity = strtolower((string) $sevStmt->fetchColumn());

            if ($severity === 'minor') {
                // Check for 3 active minor violations to escalate
                $minorSql = "SELECT v.violation_id
                             FROM violations v
                             JOIN violation_types vt ON vt.violation_type_id = v.violation_type
                             WHERE v.student_id = ?
                               AND LOWER(COALESCE(vt.severity_level, '')) = 'minor'
                               AND " . $this->isEscalatedFalseCondition('v') . "
                             ORDER BY v.date_of_violation ASC, v.created_at ASC, v.violation_id ASC";
                $minorStmt = $this->conn->prepare($minorSql);
                $minorStmt->execute([(int) $student_id]);
                $minorRows = $minorStmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($minorRows) >= 3) {
                    $sourceMinorIds = array_slice(array_map(function ($row) {
                        return (int) $row['violation_id'];
                    }, $minorRows), 0, 3);

                    // Find violation type for Major Offense - Category A
                    $majorTypeSql = $this->isPgsql()
                        ? "SELECT violation_type_id FROM violation_types WHERE type_name = ? LIMIT 1"
                        : "SELECT TOP 1 violation_type_id FROM violation_types WHERE type_name = ?";
                    $majorTypeStmt = $this->conn->prepare($majorTypeSql);
                    $majorTypeStmt->execute(['Major Offense - Category A']);
                    $majorTypeId = (int) $majorTypeStmt->fetchColumn();

                    if ($majorTypeId) {
                        $majorDesc = 'Auto-escalation rule: Converted 3 minor offenses into 1 Major Offense - Category A.';
                        $majorInsert = $this->conn->prepare(
                             "INSERT INTO violations (student_id, officer_id, violation_type, description, date_of_violation, is_self_harm)
                              VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $majorInsert->execute([
                            (int) $student_id,
                            (int) $officer_id,
                            $majorTypeId,
                            $majorDesc,
                            $date_of_violation,
                            $this->isPgsql() ? false : 0,
                        ]);
                        $majorViolationId = (int) $this->conn->lastInsertId();

                        $now = date('Y-m-d H:i:s');
                        $updateSql = "UPDATE violations
                                      SET is_escalated = ?, escalated_at = ?, escalated_to_violation_id = ?
                                      WHERE violation_id = ?";
                        $updateStmt = $this->conn->prepare($updateSql);
                        foreach ($sourceMinorIds as $sid) {
                            $updateStmt->execute([
                                $this->isPgsql() ? true : 1,
                                $now,
                                $majorViolationId,
                                $sid,
                            ]);
                        }

                        $escStmt = $this->conn->prepare(
                            "INSERT INTO violation_escalations (student_id, major_violation_id, created_by_officer_id, rule_code, created_at)
                             VALUES (?, ?, ?, ?, ?)"
                        );
                        $escStmt->execute([
                            (int) $student_id,
                            $majorViolationId,
                            (int) $officer_id,
                            'minor_3_to_major_a',
                            $now,
                        ]);
                        $escalationId = (int) $this->conn->lastInsertId();

                        $itemStmt = $this->conn->prepare(
                            "INSERT INTO violation_escalation_items (escalation_id, source_violation_id, created_at)
                             VALUES (?, ?, ?)"
                        );
                        foreach ($sourceMinorIds as $sid) {
                            $itemStmt->execute([$escalationId, $sid, $now]);
                        }
                    }
                }
            }

            $this->conn->commit();

            return [
                'success' => true,
                'message' => 'Violation recorded successfully.',
                'violation_id' => $newViolationId,
            ];
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log('Add Violation Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to record violation. Please try again.'
            ];
        }
    }

    // --- existing helper methods left unchanged (addViolation, isMinorViolationType, etc.)
    // For brevity in this replacement, reimplement only the methods we need to support pagination, sorting and search.

    public function getAllViolations($sort = 'created_at', $search = null, $dir = 'DESC', $limit = null, $offset = 0)
    {
        try {
            $allowed = [
                'created_at' => 'v.created_at',
                'date_of_violation' => 'v.date_of_violation',
                'violation_type' => 'vt.type_name'
            ];

            $orderBy = isset($allowed[$sort]) ? $allowed[$sort] : $allowed['created_at'];
            $orderDir = (strtoupper($dir) === 'ASC') ? 'ASC' : 'DESC';

            $where = $this->isEscalatedFalseCondition('v');
            $params = [];
            if (!empty($search)) {
                $studentNameExpr = "COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '')";
                $where .= " AND (LOWER(v.description) LIKE ? OR LOWER(" . $studentNameExpr . ") LIKE ? )";
                $params[] = '%' . strtolower($search) . '%';
                $params[] = '%' . strtolower($search) . '%';
            }

            $severityOrderExpr = "CASE LOWER(COALESCE(vt.severity_level, '')) WHEN 'major' THEN 1 WHEN 'moderate' THEN 2 WHEN 'minor' THEN 3 ELSE 4 END";

            if ($sort === 'severity') {
                $orderClause = $severityOrderExpr . ' ' . $orderDir . ', v.created_at DESC';
            } else {
                $orderClause = $orderBy . ' ' . $orderDir;
            }

            $query = "SELECT v.violation_id,
                             v.student_id,
                             vt.type_name,
                             vt.severity_level,
                             v.description,
                             v.date_of_violation,
                             v.created_at,
                             COALESCE(si.student_num, '') AS student_num,
                         COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '') AS student_name,
                             o.name as officer_name
                      FROM violations v
                      JOIN students s ON v.student_id = s.student_id
                     LEFT JOIN student_information si ON s.student_id = si.student_id
                      JOIN officers o ON v.officer_id = o.officer_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      WHERE " . $where . "
                      ORDER BY " . $orderClause;

            // Apply pagination depending on driver
            if ($limit !== null) {
                $limit = (int) $limit;
                $offset = (int) $offset;
                if ($this->isPgsql()) {
                    $query .= " LIMIT $limit OFFSET $offset";
                } else {
                    // SQL Server: OFFSET FETCH requires ORDER BY which we have
                    $query .= " OFFSET $offset ROWS FETCH NEXT $limit ROWS ONLY";
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            error_log("Get All Violations Error: " . $e->getMessage());
            return [];
        }
    }

    public function getAllViolationsCount($search = null)
    {
        try {
            $where = $this->isEscalatedFalseCondition('v');
            $params = [];
            if (!empty($search)) {
                $studentNameExpr = "COALESCE(NULLIF(LTRIM(RTRIM(CONCAT(COALESCE(si.last_name, ''), ', ', COALESCE(si.first_name, '')))), ','), '')";
                $where .= " AND (LOWER(v.description) LIKE ? OR LOWER(" . $studentNameExpr . ") LIKE ? )";
                $params[] = '%' . strtolower($search) . '%';
                $params[] = '%' . strtolower($search) . '%';
            }

            $query = "SELECT COUNT(*) AS cnt
                      FROM violations v
                      JOIN students s ON v.student_id = s.student_id
                     LEFT JOIN student_information si ON s.student_id = si.student_id
                      LEFT JOIN violation_types vt ON v.violation_type = vt.violation_type_id
                      WHERE " . $where;

            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($row['cnt']) ? (int) $row['cnt'] : 0;
        } catch (PDOException $e) {
            error_log("Get All Violations Count Error: " . $e->getMessage());
            return 0;
        }
    }

    // Keep other methods unchanged: getViolationsByStudent, getViolationCountByType, getRecentViolations, getEscalationHistory
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

    public function getViolationCountByType($student_id)
    {
        try {
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
