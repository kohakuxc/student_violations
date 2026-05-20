<?php $pageTitle = 'File Report'; include 'view/partials/student_layout_top.php'; ?>

<div class="form-container">
    <div class="page-header">
        <h2 class="page-title">File a Report</h2>
        <a href="index.php?page=student_dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <div class="alert alert-info">
            <strong>If you need immediate help:</strong>
            <ul style="margin:8px 0 0 18px;">
                <li>Reach out to a trusted staff member or counselor right away.</li>
                <li>Call local emergency services if you feel unsafe.</li>
                <li>Visit the campus guidance office for confidential support.</li>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="index.php?page=student_report" data-confirm="Submit this report?">
        <?php
            require_once __DIR__ . '/../helper/CsrfHelper.php';
            $formKey = 'student_report_form';
            $formToken = csrfGenerateFormToken($formKey);
        ?>
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
        <input type="text" name="contact_website" value="" style="display:none" tabindex="-1" autocomplete="off">

        <div class="form-group">
            <label for="report_type">Report Type <span class="required">*</span></label>
            <select id="report_type" name="report_type" required>
                <option value="">-- Select --</option>
                <option value="bullying">Bullying / Harassment</option>
                <option value="discipline">Discipline Concern</option>
                <option value="mental_health">Mental Health / Self-Harm Concern</option>
            </select>
        </div>

        <div class="form-group">
            <label for="description">Description <span class="required">*</span></label>
            <textarea id="description" name="description" rows="6" placeholder="Describe what happened and who is involved..."
                required maxlength="1200"></textarea>
            <small class="form-text text-muted">Please provide as much detail as you can (up to 1200 characters).</small>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Submit Report</button>
            <a href="index.php?page=student_dashboard" class="btn btn-secondary">Cancel</a>
        </div>
    </form>
</div>

<?php include 'view/partials/student_layout_bottom.php'; ?>
