<?php
class ImportLogModel
{
    private $conn;

    public function __construct()
    {
        include __DIR__ . '/../config/db_connection.php';
        $this->conn = $conn;
    }

    public function createLog($officerId, $fileName, $fileType, $totalRows, $importedRows, $errorRows, $status, array $metadata = [])
    {
        $query = "INSERT INTO import_logs
                  (officer_id, file_name, file_type, total_rows, imported_rows, error_rows, status, metadata, created_at)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([
            $officerId !== null ? (int) $officerId : null,
            (string) $fileName,
            (string) $fileType,
            (int) $totalRows,
            (int) $importedRows,
            (int) $errorRows,
            (string) $status,
            json_encode($metadata, JSON_UNESCAPED_SLASHES),
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function updateLog($importId, $status, $importedRows, $errorRows, array $metadata = [])
    {
        $query = "UPDATE import_logs
                  SET status = ?, imported_rows = ?, error_rows = ?, metadata = ?, created_at = created_at
                  WHERE import_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            (string) $status,
            (int) $importedRows,
            (int) $errorRows,
            json_encode($metadata, JSON_UNESCAPED_SLASHES),
            (int) $importId
        ]);
    }
}
?>
