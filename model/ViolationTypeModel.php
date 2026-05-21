<?php
/**
 * ViolationType Model
 * Handles violation type mapping (type name + severity level)
 */

class ViolationTypeModel
{
    private $conn;

    public function __construct()
    {
        include 'config/db_connection.php';
        $this->conn = $conn;
    }

    public function getActiveViolationTypes()
    {
        try {
            $query = "SELECT violation_type_id, type_name, severity_level
                      FROM violation_types
                      WHERE is_active = true
                      ORDER BY violation_type_id ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Get Violation Types Error: " . $e->getMessage());
            return [];
        }
    }

    public function findActiveViolationTypeByLookup($lookup)
    {
        try {
            $lookup = trim((string) $lookup);
            if ($lookup === '') {
                return null;
            }

            $normalizedLookup = strtolower(preg_replace('/\s+/', ' ', $lookup));
            $types = $this->getActiveViolationTypes();
            foreach ($types as $type) {
                if ((string) ($type['violation_type_id'] ?? '') === $lookup) {
                    return $type;
                }
            }

            foreach ($types as $type) {
                $typeName = trim((string) ($type['type_name'] ?? ''));
                $normalizedName = strtolower(preg_replace('/\s+/', ' ', $typeName));
                $aliasList = [$normalizedName, strtolower($typeName)];

                if (strtolower((string) ($type['severity_level'] ?? '')) === 'minor') {
                    $aliasList[] = 'minor';
                }

                if (in_array($normalizedLookup, $aliasList, true)) {
                    return $type;
                }
            }

            return null;
        } catch (Exception $e) {
            error_log("Find Violation Type Error: " . $e->getMessage());
            return null;
        }
    }
}
?>