<?php
/**
 * Import Violations Controller
 * Handles bulk import of violations from Excel/CSV files
 */

require_once __DIR__ . '/../helper/AuthHelper.php';
require_once __DIR__ . '/../helper/CsrfHelper.php';
require_once __DIR__ . '/../helper/RateLimiter.php';
require_once __DIR__ . '/../helper/AuditLogger.php';
require_once __DIR__ . '/../model/StudentModel.php';
require_once __DIR__ . '/../model/ViolationModel.php';
require_once __DIR__ . '/../model/ViolationTypeModel.php';
require_once __DIR__ . '/../model/ImportLogModel.php';

// Check if user is logged in as officer
if (!isset($_SESSION['officer_id'])) {
    header("Location: index.php?page=login");
    exit();
}

// Check import permission
if (empty($_SESSION['can_import_excel'])) {
    $_SESSION['import_flash'] = ['type' => 'error', 'message' => 'You do not have permission to import violations.'];
    header("Location: index.php?page=dashboard");
    exit();
}

$studentModel = new StudentModel();
$violationModel = new ViolationModel();
$violationTypeModel = new ViolationTypeModel();
$importLogModel = new ImportLogModel();
$auditLogger = new AuditLogger();

$flash = $_SESSION['import_flash'] ?? null;
unset($_SESSION['import_flash']);

if (!function_exists('parseViolationImportFile')) {
    function parseViolationImportFile($filePath, $fileName)
    {
        $extension = strtolower(pathinfo((string) $fileName, PATHINFO_EXTENSION));
        if ($extension === 'csv') {
            return parseCsvFile($filePath);
        }

        if ($extension === 'xlsx') {
            return parseViolationImportXlsx($filePath);
        }

        throw new Exception('Unsupported file type. Please upload a CSV or XLSX file.');
    }
}

if (!function_exists('parseViolationImportXlsx')) {
    function parseViolationImportXlsx($filePath)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('XLSX import requires the ZipArchive extension. Use CSV if unavailable.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new Exception('Unable to open the XLSX file.');
        }

        $sharedStrings = [];
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedStringsXml !== false) {
            $sharedDoc = new DOMDocument();
            $sharedDoc->loadXML($sharedStringsXml);
            $sharedXPath = new DOMXPath($sharedDoc);
            $sharedXPath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($sharedXPath->query('//a:si') as $sharedItem) {
                $textNodes = $sharedXPath->query('.//a:t', $sharedItem);
                $text = '';
                foreach ($textNodes as $textNode) {
                    $text .= $textNode->textContent;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if ($sheetXml === false) {
            throw new Exception('Could not read the first worksheet from the XLSX file.');
        }

        $doc = new DOMDocument();
        $doc->loadXML($sheetXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $columnLetterToIndex = function ($columnLetter) {
            $columnLetter = strtoupper(trim((string) $columnLetter));
            $index = 0;

            for ($i = 0; $i < strlen($columnLetter); $i++) {
                $index = ($index * 26) + (ord($columnLetter[$i]) - 64);
            }

            return max(0, $index - 1);
        };

        $rows = [];
        foreach ($xpath->query('//a:sheetData/a:row') as $row) {
            $cells = [];
            $maxIndex = -1;
            foreach ($xpath->query('./a:c', $row) as $cell) {
                $value = '';
                $attrType = $cell->attributes->getNamedItem('t');
                $cellType = $attrType ? (string) $attrType->nodeValue : '';
                $cellRefAttr = $cell->attributes->getNamedItem('r');
                $cellRef = $cellRefAttr ? (string) $cellRefAttr->nodeValue : '';
                $cellIndex = $cellRef !== '' ? $columnLetterToIndex(preg_replace('/\d+$/', '', $cellRef)) : count($cells);

                if ($cellType === 's') {
                    $valueNode = $xpath->query('./a:v', $cell)->item(0);
                    $index = $valueNode ? (int) $valueNode->textContent : -1;
                    $value = $sharedStrings[$index] ?? '';
                } elseif ($cellType === 'inlineStr') {
                    $textNode = $xpath->query('.//a:t', $cell)->item(0);
                    $value = $textNode ? (string) $textNode->textContent : '';
                } else {
                    $valueNode = $xpath->query('./a:v', $cell)->item(0);
                    $value = $valueNode ? (string) $valueNode->textContent : '';
                }

                $cells[$cellIndex] = $value;
                if ($cellIndex > $maxIndex) {
                    $maxIndex = $cellIndex;
                }
            }

            if (!empty($cells)) {
                ksort($cells);
                $cells = array_values($cells);
                if ($maxIndex >= 0) {
                    $cells = array_pad($cells, $maxIndex + 1, '');
                }
                $rows[] = $cells;
            }
        }

        return $rows;
    }
}

$violation_types = $violationTypeModel->getActiveViolationTypes();
$violations_by_id = [];
foreach ($violation_types as $vtype) {
    $violations_by_id[$vtype['violation_type_id']] = $vtype;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        csrfRequireValidToken($_POST['csrf_token'] ?? '', $_POST['form_key'] ?? null, $_POST['form_token'] ?? null);

        // Check rate limit
        if (!rateLimitCheck('import_violations_' . (int) $_SESSION['officer_id'], 5, 300)) {
            throw new Exception('Too many import attempts. Please wait before trying again.');
        }

        // Validate file upload
        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('No file uploaded or upload error occurred.');
        }

        $file = $_FILES['import_file'];
        $fileName = basename($file['name']);
        $tmpPath = $file['tmp_name'];
        $fileSize = $file['size'];

        // Validate file size (max 10MB)
        if ($fileSize > 10 * 1024 * 1024) {
            throw new Exception('File size exceeds 10MB limit.');
        }

        // Determine file type
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, ['csv', 'xlsx'])) {
            throw new Exception('Invalid file type. Only CSV and XLSX files are allowed.');
        }

        $fileType = $ext;

        // Parse file based on type
        $rows = [];
        $rows = parseViolationImportFile($tmpPath, $fileName);

        if (empty($rows)) {
            throw new Exception('File is empty or could not be parsed.');
        }

        // Validate header row
        $headers = $rows[0];
        $requiredHeaders = ['student_number', 'violation_type', 'description', 'date_of_violation'];
        $headerMap = array_flip(array_map('strtolower', $headers));

        foreach ($requiredHeaders as $req) {
            if (!isset($headerMap[$req])) {
                throw new Exception("Missing required column: $req");
            }
        }

        // Create import log
        $dataRows = count($rows) - 1; // exclude header
        $importId = $importLogModel->createLog(
            (int) $_SESSION['officer_id'],
            $fileName,
            $fileType,
            $dataRows,
            0,
            0,
            'pending',
            ['started_at' => date('c')]
        );

        $auditLogger->log(
            (int) $_SESSION['officer_id'],
            'officer',
            'import_started',
            'import_logs',
            $importId,
            ['file_name' => $fileName, 'total_rows' => $dataRows]
        );

        // Process rows
        $importedCount = 0;
        $errorCount = 0;
        $errors = [];

        for ($i = 1; $i < count($rows); $i++) {
            $row = $rows[$i];

            try {
                $studentNumber = trim((string) ($row[$headerMap['student_number']] ?? ''));
                $violationTypeId = trim((string) ($row[$headerMap['violation_type']] ?? ''));
                $description = trim((string) ($row[$headerMap['description']] ?? ''));
                $dateOfViolation = trim((string) ($row[$headerMap['date_of_violation']] ?? ''));

                // Validate row data
                if (empty($studentNumber)) {
                    throw new Exception("Row " . ($i + 1) . ": Student number is required.");
                }
                if (empty($violationTypeId)) {
                    throw new Exception("Row " . ($i + 1) . ": Violation type is required.");
                }
                if (empty($description)) {
                    throw new Exception("Row " . ($i + 1) . ": Description is required.");
                }
                if (empty($dateOfViolation)) {
                    throw new Exception("Row " . ($i + 1) . ": Date of violation is required.");
                }

                // Find student
                $student = $studentModel->findStudentByLookup($studentNumber);
                if (!$student) {
                    throw new Exception("Row " . ($i + 1) . ": Student '$studentNumber' not found.");
                }

                // Check if violation type exists and is numeric
                $violationType = $violationTypeModel->findActiveViolationTypeByLookup($violationTypeId);
                if (!$violationType) {
                    $allowedTypes = array_map(function ($type) {
                        return $type['violation_type_id'] . ' (' . $type['type_name'] . ')';
                    }, $violation_types);
                    throw new Exception(
                        "Row " . ($i + 1) . ": Violation type '$violationTypeId' not found. Allowed values: " . implode(', ', $allowedTypes)
                    );
                }
                $violationTypeId = (int) $violationType['violation_type_id'];

                // Validate date format (YYYY-MM-DD or other common formats)
                $date = DateTime::createFromFormat('Y-m-d', $dateOfViolation);
                if (!$date) {
                    $date = DateTime::createFromFormat('m/d/Y', $dateOfViolation);
                }
                if (!$date) {
                    $date = DateTime::createFromFormat('d/m/Y', $dateOfViolation);
                }
                if (!$date) {
                    throw new Exception("Row " . ($i + 1) . ": Invalid date format. Use YYYY-MM-DD, MM/DD/YYYY, or DD/MM/YYYY.");
                }
                $dateOfViolation = $date->format('Y-m-d');

                // Check for self-harm keywords
                $isSelfHarm = false;
                if (isset($headerMap['is_self_harm'])) {
                    $harmValue = strtolower(trim((string) ($row[$headerMap['is_self_harm']] ?? '')));
                    $isSelfHarm = in_array($harmValue, ['yes', 'true', '1', 'y']) ? true : false;
                }

                // Add violation
                $result = $violationModel->addViolation(
                    (int) $student['student_id'],
                    (int) $_SESSION['officer_id'],
                    (int) $violationTypeId,
                    $description,
                    $dateOfViolation,
                    $isSelfHarm
                );

                if ($result['success']) {
                    $importedCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Row " . ($i + 1) . ": " . ($result['message'] ?? 'Unknown error.') . (!empty($result['debug_message']) ? " [" . $result['debug_message'] . "]" : '');
                }
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = $e->getMessage();
            }
        }

        // Update import log
        $importLogModel->updateLog(
            $importId,
            'completed',
            $importedCount,
            $errorCount,
            [
                'completed_at' => date('c'),
                'errors' => array_slice($errors, 0, 20) // Store first 20 errors
            ]
        );

        $auditLogger->log(
            (int) $_SESSION['officer_id'],
            'officer',
            'import_completed',
            'import_logs',
            $importId,
            ['imported' => $importedCount, 'errors' => $errorCount]
        );

        $flash = [
            'type' => 'success',
            'message' => "Import completed. $importedCount violations imported, $errorCount errors.",
            'import_id' => $importId,
            'errors' => $errors
        ];
    } catch (Exception $e) {
        if (isset($importId)) {
            $importLogModel->updateLog(
                $importId,
                'failed',
                $importedCount ?? 0,
                $errorCount ?? 0,
                [
                    'failed_at' => date('c'),
                    'errors' => isset($errors) ? array_slice($errors, 0, 20) : [$e->getMessage()]
                ]
            );
        }
        $flash = ['type' => 'error', 'message' => $e->getMessage()];
    }

    $_SESSION['import_flash'] = $flash;
    header("Location: index.php?page=import_violations");
    exit();
}

/**
 * Parse CSV file
 */
function parseCsvFile($filePath)
{
    $rows = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
    }
    return $rows;
}

// Render view
include 'view/import_violations.php';
?>
