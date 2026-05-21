<?php $pageTitle = 'Import Violations'; include 'view/partials/layout_top.php'; ?>

<div class="page-header">
    <div>
        <h2 class="page-title">Import Violations</h2>
        <p class="text-muted">Bulk import student violations from CSV files.</p>
    </div>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
        <strong><?php echo $flash['type'] === 'success' ? '✓ Success' : '✗ Error'; ?>:</strong>
        <?php echo htmlspecialchars($flash['message']); ?>
        <?php if (!empty($flash['import_id'])): ?>
            <br><small>Import ID: <?php echo htmlspecialchars($flash['import_id']); ?></small>
        <?php endif; ?>
    </div>

    <?php if (!empty($flash['errors'])): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white">Import Errors (<?php echo count($flash['errors']); ?>)</h5>
            </div>
            <div class="card-body">
                <div style="max-height: 300px; overflow-y: auto; font-size: 0.9rem;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($flash['errors'] as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white">Upload Violations File</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="index.php?page=import_violations" enctype="multipart/form-data" data-confirm="Upload and import violations from this file?">
                    <?php
                        $formKey = 'import_violations_form';
                        $formToken = csrfGenerateFormToken($formKey);
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">

                    <div class="form-group">
                        <label for="import_file"><strong>CSV or XLSX File</strong></label>
                        <input type="file" id="import_file" name="import_file" accept=".csv,.xlsx" required>
                        <small class="text-muted">Supported formats: CSV, XLSX (max 10MB)</small>
                    </div>

                    <div class="alert alert-info">
                        <strong>File Format:</strong>
                        <p style="margin-bottom: 10px;">Your CSV or XLSX file must include the following columns (case-insensitive):</p>
                        <ul style="margin: 0; padding-left: 20px;">
                            <li><code>student_number</code> - Student ID or number (required)</li>
                            <li><code>violation_type</code> - Type name or ID from system (required)</li>
                            <li><code>description</code> - Violation description (required)</li>
                            <li><code>date_of_violation</code> - Date in YYYY-MM-DD format (required)</li>
                            <li><code>is_self_harm</code> - Optional: yes/no, true/false, or 1/0</li>
                        </ul>
                    </div>

                    <div class="alert alert-warning">
                        <strong>Example:</strong>
                        <pre style="margin-top: 10px; overflow-x: auto;">student_number,violation_type,description,date_of_violation,is_self_harm
12345,1,"Unauthorized absence from class",2026-05-15,no
12346,"Bullying","Engaging in peer conflict",2026-05-16,no
12347,3,"Safety concern reported",2026-05-17,yes</pre>
                    </div>

                    <button type="submit" class="btn btn-primary">Upload & Import</button>
                </form>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h5 class="mb-0 text-white">Available Violation Types</h5>
            </div>
            <div class="card-body">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #ddd;">
                            <th style="padding: 10px; text-align: left;">ID</th>
                            <th style="padding: 10px; text-align: left;">Type Name</th>
                            <th style="padding: 10px; text-align: left;">Severity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($violation_types)): ?>
                            <?php foreach ($violation_types as $vtype): ?>
                                <tr style="border-bottom: 1px solid #eee;">
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($vtype['violation_type_id']); ?></td>
                                    <td style="padding: 10px;"><?php echo htmlspecialchars($vtype['type_name']); ?></td>
                                    <td style="padding: 10px;">
                                        <span class="badge" style="
                                            padding: 5px 10px;
                                            border-radius: 3px;
                                            background-color: <?php echo strtolower($vtype['severity_level']) === 'major' ? '#dc3545' : (strtolower($vtype['severity_level']) === 'moderate' ? '#ffc107' : '#28a745'); ?>;
                                            color: <?php echo strtolower($vtype['severity_level']) === 'major' ? 'white' : (strtolower($vtype['severity_level']) === 'moderate' ? 'black' : 'white'); ?>;
                                        ">
                                            <?php echo htmlspecialchars($vtype['severity_level']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="padding: 10px; text-align: center; color: #999;">No violation types available</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white">Import Guidelines</h5>
            </div>
            <div class="card-body">
                <h6>Before You Import</h6>
                <ul style="margin-top: 10px; padding-left: 20px;">
                    <li>Ensure all student numbers exist in the system</li>
                    <li>Use valid violation type names or IDs</li>
                    <li>Dates must be in YYYY-MM-DD format</li>
                    <li>Keep descriptions clear and concise</li>
                    <li>Maximum 10MB file size</li>
                </ul>

                <h6 style="margin-top: 20px;">Duplicate Handling</h6>
                <p style="font-size: 0.95rem;">
                    The system detects duplicate violations (same student, type, description, and date within 2 minutes or so) and returns the existing violation ID instead of creating a duplicate.
                </p>

                <h6 style="margin-top: 20px;">Errors</h6>
                <p style="font-size: 0.95rem;">
                    If an error occurs during import, that row is skipped and the import continues. Check the error report after completion.
                </p>

                <h6 style="margin-top: 20px;">Audit Trail</h6>
                <p style="font-size: 0.95rem;">
                    All imports are logged with:
                </p>
                <ul style="margin-top: 10px; padding-left: 20px; font-size: 0.95rem;">
                    <li>File name and type</li>
                    <li>Import timestamp</li>
                    <li>Success/error counts</li>
                    <li>First 20 errors (if any)</li>
                </ul>
            </div>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div class="card-header">
                <h5 class="mb-0 text-white">Quick Tips</h5>
            </div>
            <div class="card-body" style="font-size: 0.9rem;">
                <p><strong>Prepare your CSV:</strong></p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li>Export from Excel as CSV (comma-delimited)</li>
                    <li>Include headers in first row</li>
                    <li>Remove special characters from descriptions</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>
