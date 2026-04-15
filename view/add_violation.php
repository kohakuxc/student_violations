<?php $pageTitle = "Record Student Violation"; include 'view/partials/layout_top.php'; ?>

<div class="form-container">
  <div class="page-header">
    <h2 class="page-title">Record Student Violation</h2>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
  </div>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div>
  <?php endif; ?>

  <?php if (!empty($error)): ?>
    <div class="alert alert-error">❌ <?php echo htmlspecialchars($error); ?></div>
  <?php endif; ?>

  <form method="POST" action="index.php?page=add_violation" class="violation-form">
    <div class="form-group">
      <label for="student_number">Student ID/Number: <span class="required">*</span></label>
      <input type="text" id="student_number" name="student_number"
             value="<?php echo htmlspecialchars($student_number ?? ''); ?>"
             placeholder="e.g., STU001" required>
    </div>

    <div class="form-group">
      <label for="violation_type">Violation Type: <span class="required">*</span></label>
      <select id="violation_type" name="violation_type" required>
        <option value="">-- Select Violation Type --</option>
        <?php foreach (($violation_types ?? []) as $t): ?>
          <option value="<?php echo htmlspecialchars($t['violation_type_id']); ?>"
            <?php echo ($violation_type ?? '') == $t['violation_type_id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($t['type_name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-group">
      <label for="description">Description: <span class="required">*</span></label>
      <textarea id="description" name="description" rows="5"
                placeholder="Describe the violation in detail..."
                required><?php echo htmlspecialchars($description ?? ''); ?></textarea>
    </div>

    <div class="form-group">
      <label for="date_of_violation">Date of Violation: <span class="required">*</span></label>
      <input type="date" id="date_of_violation" name="date_of_violation"
             value="<?php echo htmlspecialchars($date_of_violation ?? ''); ?>" required>
    </div>

    <div class="form-actions">
      <button type="submit" class="btn btn-primary">Record Violation</button>
      <a href="index.php?page=dashboard" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>