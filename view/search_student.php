<?php $pageTitle = "Search Student Violations";
include 'view/partials/layout_top.php'; ?>

<div class="form-container">
  <div class="page-header">
    <h2 class="page-title">Search Student Violations</h2>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
  </div>

  <form method="POST" action="index.php?page=search_student" class="search-form">
    <input type="text" name="search" placeholder="Enter student name or student ID..."
      value="<?php echo htmlspecialchars($_POST['search'] ?? ''); ?>" required>
    <button type="submit" class="btn btn-primary">Search</button>
  </form>

  <?php if ($search_performed && $student_info): ?>
    <div class="student-info">
      <h3>Student Information</h3>
      <div class="info-grid">
        <div class="info-item">
          <label>Name:</label>
          <span><?php echo htmlspecialchars($student_info['name']); ?></span>
        </div>
        <div class="info-item">
          <label>Student ID:</label>
          <span><?php echo htmlspecialchars($student_info['student_number']); ?></span>
        </div>
        <div class="info-item">
          <label>Email:</label>
          <span><?php echo htmlspecialchars($student_info['email'] ?? 'N/A'); ?></span>
        </div>
      </div>
    </div>

    <?php if (!empty($violation_counts)): ?>
      <div class="violation-summary">
        <h3>Violation Summary</h3>
        <div class="summary-grid">
          <?php foreach ($violation_counts as $count): ?>
            <?php $sev = strtolower($count['severity_level'] ?? 'unknown'); ?>
            <div class="summary-card">
              <p><?php echo htmlspecialchars(ucfirst($sev)); ?></p>
              <p class="count"><?php echo (int) $count['count']; ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
    
    <h3>Violations History</h3>

    <div class="violations-list">
      <?php if (!empty($violations)): ?>
        <table class="violations-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Level</th>
              <th>Description</th>
              <th>Recorded By</th>
              <th>Recorded On</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($violations as $v): ?>
              <tr>
                <td><?php echo date('M d, Y', strtotime($v['date_of_violation'])); ?></td>
                <td>
                  <?php $sev = strtolower($v['severity_level'] ?? 'unknown'); ?>
                  <span class="badge sev-<?php echo htmlspecialchars($sev); ?>">
                    <?php echo htmlspecialchars(ucfirst($sev)); ?>
                  </span>
                </td>
                <td>
                  <p class="violation-desc"><?php echo htmlspecialchars($v['description']); ?></p>
                </td>
                <td><?php echo htmlspecialchars($v['officer_name']); ?></td>
                <td><?php echo date('M d, Y H:i', strtotime($v['created_at'])); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="alert alert-info">ℹ️ No violations found for this student.</div>
      <?php endif; ?>
    </div>

  <?php elseif ($search_performed && !$student_info): ?>
    <div class="alert alert-error">
      ❌ Student not found! Please check the name or student ID.
    </div>
  <?php endif; ?>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>