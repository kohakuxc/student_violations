<?php $pageTitle = "All Violations"; include 'view/partials/layout_top.php'; ?>

<style>
  .table-wrap { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; background: #fff; }
  th, td { padding: 12px; border-bottom: 1px solid #eee; text-align: left; vertical-align: top; }
  th { background: #f7f7fb; }
  .muted { color: #666; font-size: 0.95em; }
  .actions { display:flex; gap:10px; margin: 10px 0 20px; flex-wrap: wrap; }
  .btn-secondary { background:#6b7280; }
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
</div>

<?php include 'view/partials/layout_bottom.php'; ?>