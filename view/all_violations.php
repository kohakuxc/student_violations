<?php $pageTitle = "All Violations";
include 'view/partials/layout_top.php'; ?>

<style>
  .table-wrap {
    overflow-x: auto;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
  }

  th,
  td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
    vertical-align: top;
  }

  th {
    background: #f7f7fb;
  }

  .muted {
    color: #666;
    font-size: 0.95em;
  }

  .actions {
    display: flex;
    gap: 10px;
    margin: 10px 0 20px;
    flex-wrap: wrap;
  }

  .btn-secondary {
    background: #6b7280;
  }

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
    padding: 12px;
    margin-bottom: 12px;
    background: #f9fafb;
  }

  .escalation-item:last-child {
    margin-bottom: 0;
  }

  .source-list {
    margin: 8px 0 0 20px;
  }

  .source-list li {
    margin-bottom: 6px;
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

<div class="dashboard">
  <h2>All Violations</h2>

  <div class="actions">
    <a class="btn btn-primary" href="index.php?page=dashboard">← Back to Dashboard</a>
    <a class="btn btn-secondary" href="index.php?page=add_violation">➕ Add New Violation</a>
  </div>

  <p class="muted">Showing <?php echo count($violations); ?> violation record(s).</p>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date of Violation</th>
          <th>Student</th>
          <th>Officer</th>
          <th>Violation Type</th>
          <th>Description</th>
          <th>Created At</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($violations)): ?>
          <tr>
            <td colspan="6">No violations found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($violations as $v): ?>
            <tr>
              <td><?php echo htmlspecialchars($v['date_of_violation']); ?></td>
              <td><?php echo htmlspecialchars($v['student_name']); ?></td>
              <td><?php echo htmlspecialchars($v['officer_name']); ?></td>
              <td><?php echo htmlspecialchars(ucfirst($v['severity_level'] ?? 'Unknown')); ?></td>
              <td><?php echo nl2br(htmlspecialchars($v['description'] ?? '')); ?></td>
              <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($v['created_at']))); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="escalation-panel">
    <h3>Escalation History</h3>
    <p class="muted">Audit trail for auto-escalation rule: 3 minor offenses → 1 Major Offense - Category A.</p>

    <?php if (empty($escalation_history ?? [])): ?>
      <p class="muted">No escalation events recorded yet.</p>
    <?php else: ?>
      <?php foreach (($escalation_history ?? []) as $e): ?>
        <div class="escalation-item">
          <div>
            <span class="pill">Escalation #<?php echo (int) $e['escalation_id']; ?></span>
          </div>
          <p><strong>Student:</strong>
            <?php echo htmlspecialchars($e['student_name'] ?: ('Student #' . $e['student_id'])); ?></p>
          <p><strong>Created:</strong> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($e['escalated_at']))); ?>
          </p>
          <p><strong>Major Record:</strong> #<?php echo (int) $e['major_violation_id']; ?>
            (<?php echo htmlspecialchars(date('M d, Y', strtotime($e['major_date_of_violation']))); ?>)</p>
          <p><strong>Recorded By:</strong> <?php echo htmlspecialchars($e['escalated_by_officer'] ?? 'System'); ?></p>
          <p><strong>Generated Description:</strong> <?php echo htmlspecialchars($e['major_description'] ?? ''); ?></p>

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
</div>

<?php include 'view/partials/layout_bottom.php'; ?>