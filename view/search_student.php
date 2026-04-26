<?php $pageTitle = "Search Student Violations";
include 'view/partials/layout_top.php'; ?>

<div class="form-container">
  <div class="page-header">
    <h2 class="page-title">Search Student Violations</h2>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
  </div>

  <form method="POST" action="index.php?page=search_student" class="search-form">
    <div class="student-lookup-wrap">
      <input type="text" id="student_lookup" name="search"
        placeholder="Enter First Name, Last Name, or Student Number..."
        value="<?php echo htmlspecialchars($_POST['search'] ?? ''); ?>" autocomplete="off" required>
      <div id="student_lookup_results" class="student-lookup-results d-none"></div>
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
  </form>

  <script src="assets/js/student_lookup.js"></script>

  <style>
    .escalation-panel {
      margin-top: 24px;
      background: linear-gradient(135deg, #e3f2fd 0%, #f3e5f5 100%);
      padding: 1.5rem;
      border-radius: 8px;
      margin-bottom: 2rem;
      border-left: 4px solid #667eea;
    }

    .escalation-item {
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 0.85rem;
      margin-bottom: 0.8rem;
      background: #f9fafb;
    }

    .escalation-item:last-child {
      margin-bottom: 0;
    }

    .source-list {
      margin: 0.35rem 0 0 1.1rem;
    }

    .source-list li {
      margin-bottom: 0.3rem;
    }

    .pill {
      display: inline-block;
      padding: 2px 8px;
      border-radius: 999px;
      background: #dbeafe;
      color: #1e3a8a;
      font-size: 12px;
      font-weight: 600;
    }
  </style>

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
          <span><?php echo htmlspecialchars($student_info['display_student_id'] ?? $student_info['student_number']); ?></span>
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

    

    <div class="violations-list">
      <h3>Violations History</h3>
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

    <div class="escalation-panel">
      <h3>Escalation History</h3>
      <p class="muted">3 minor offenses are auto-converted into 1 Major Offense - Category A.</p>

      <?php if (empty($escalation_history ?? [])): ?>
        <div class="alert alert-info">ℹ️ No escalation events for this student.</div>
      <?php else: ?>
        <?php foreach (($escalation_history ?? []) as $e): ?>
          <div class="escalation-item">
            <div><span class="pill">Escalation #<?php echo (int) $e['escalation_id']; ?></span></div>
            <p><strong>Created:</strong> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($e['escalated_at']))); ?>
            </p>
            <p><strong>Major Record:</strong> #<?php echo (int) $e['major_violation_id']; ?>
              (<?php echo htmlspecialchars(date('M d, Y', strtotime($e['major_date_of_violation']))); ?>)</p>
            <p><strong>Recorded By:</strong> <?php echo htmlspecialchars($e['escalated_by_officer'] ?? 'System'); ?></p>

            <p><strong>Source Minor Violations:</strong></p>
            <ul class="source-list">
              <?php foreach (($e['source_violations'] ?? []) as $src): ?>
                <li>
                  #<?php echo (int) $src['source_violation_id']; ?>
                  (<?php echo htmlspecialchars(date('M d, Y', strtotime($src['date_of_violation']))); ?>)
                  - <?php echo htmlspecialchars($src['description'] ?? ''); ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  <?php elseif ($search_performed && !$student_info): ?>
    <div class="alert alert-error">
      ❌ Student not found! Please check the name or student number.
    </div>
  <?php endif; ?>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>