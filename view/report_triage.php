<?php $pageTitle = 'Report Triage'; include 'view/partials/layout_top.php'; ?>

<div class="page-header">
    <div>
        <h2 class="page-title">Student Reports</h2>
        <p class="text-muted">Review and update reports submitted by students.</p>
    </div>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Report ID</th>
                    <th>Student</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Self-Harm Flag</th>
                    <th>Update</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No reports found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reports as $report): ?>
                        <tr>
                            <td>#<?php echo (int) $report['report_id']; ?></td>
                            <td><?php echo htmlspecialchars($report['student_name'] ?? ('Student #' . $report['student_id'])); ?></td>
                            <td><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $report['report_type']))); ?></td>
                            <td style="max-width: 320px;">
                                <?php echo nl2br(htmlspecialchars($report['description'] ?? '')); ?>
                            </td>
                            <td><span class="badge bg-<?php echo $report['status'] === 'resolved' ? 'success' : ($report['status'] === 'escalated' ? 'danger' : 'warning'); ?>">
                                <?php echo htmlspecialchars(str_replace('_', ' ', $report['status'] ?? 'new')); ?>
                            </span></td>
                            <td>
                                <?php echo !empty($report['is_self_harm']) ? '<span class="text-danger fw-bold">Flagged</span>' : '—'; ?>
                            </td>
                            <td>
                                <form method="POST" action="index.php?page=report_triage" data-confirm="Update this report status?">
                                    <?php
                                        require_once __DIR__ . '/../helper/CsrfHelper.php';
                                        $formKey = 'report_triage_' . (int) $report['report_id'];
                                        $formToken = csrfGenerateFormToken($formKey);
                                    ?>
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="report_id" value="<?php echo (int) $report['report_id']; ?>">
                                    <select name="status" class="form-select form-select-sm" style="margin-bottom:8px;">
                                        <?php foreach (['new','in_review','resolved','escalated'] as $status): ?>
                                            <option value="<?php echo $status; ?>" <?php echo ($report['status'] ?? '') === $status ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $status))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>
