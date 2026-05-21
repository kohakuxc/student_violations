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
    cursor: pointer;
    user-select: none;
  }

  th:hover {
    background: #e8eaed;
  }

  th a {
    color: inherit;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .sort-arrow {
    font-size: 11px;
    opacity: 0.5;
  }

  .sort-arrow.active {
    opacity: 1;
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

  .filter-btn {
    background: none;
    border: none;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 16px;
    transition: background 0.2s;
  }

  .filter-btn:hover {
    background: #e5e7eb;
  }

  .filter-modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.4);
  }

  .filter-modal.show {
    display: flex;
    justify-content: center;
    align-items: center;
  }

  .filter-modal-content {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    max-width: 600px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
  }

  .filter-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    border-bottom: 1px solid #eee;
    padding-bottom: 12px;
  }

  .filter-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #666;
  }

  .filter-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .filter-form-group {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .filter-form-group label {
    font-weight: 500;
    color: #333;
  }

  .filter-form-group select,
  .filter-form-group input {
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
  }

  .filter-form-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
  }

  .pagination-nav {
    margin-top: 16px;
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
  }

  .pagination-nav a,
  .pagination-nav button {
    padding: 6px 12px;
    border-radius: 4px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    font-size: 14px;
  }

  .pagination-nav a:hover,
  .pagination-nav button:hover {
    background: #f3f4f6;
  }

  .pagination-nav .page-info {
    margin: 0 8px;
    font-size: 14px;
    color: #666;
  }

  .pagination-nav .page-numbers {
    display: flex;
    gap: 4px;
  }

  .pagination-nav .page-numbers a {
    padding: 4px 8px;
    min-width: 32px;
    text-align: center;
  }

  .pagination-nav .page-numbers a.active {
    background: #3b82f6;
    color: white;
    border-color: #3b82f6;
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
    <button class="btn btn-primary" onclick="openFilterModal()">Filter</button>
    <?php $currentSort = $_GET['sort'] ?? 'created_at';
    $currentDir = strtolower($_GET['dir'] ?? 'desc');
    $currentSearch = $_GET['search'] ?? ''; ?>
    <a href="index.php?page=all_violations&export=csv&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&page_size=<?php echo (int) ($_GET['page_size'] ?? 25); ?>"
      class="btn btn-primary">Export CSV</a>
  </div>

  <!-- Filter Modal -->
  <div id="filterModal" class="filter-modal">
    <div class="filter-modal-content">
      <div class="filter-modal-header text-black">
        <h5 class="modal-title"> Filter Options</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="closeFilterModal()"></button>
      </div>
      <form method="get" action="index.php" class="filter-form">
        <input type="hidden" name="page" value="all_violations">

        <div class="filter-form-group">
          <label for="sort_select">Sort by:</label>
          <select id="sort_select" name="sort">
            <option value="created_at" <?php echo $currentSort === 'created_at' ? 'selected' : ''; ?>>Date Created
            </option>
            <option value="date_of_violation" <?php echo $currentSort === 'date_of_violation' ? 'selected' : ''; ?>>Date
              of Violation</option>
            <option value="violation_type" <?php echo $currentSort === 'violation_type' ? 'selected' : ''; ?>>Violation
              Type</option>
            <option value="severity" <?php echo $currentSort === 'severity' ? 'selected' : ''; ?>>Severity (Major/Minor)
            </option>
          </select>
        </div>

        <div class="filter-form-group">
          <label for="dir_select">Direction:</label>
          <select id="dir_select" name="dir">
            <option value="desc" <?php echo $currentDir === 'desc' ? 'selected' : ''; ?>>Newest first</option>
            <option value="asc" <?php echo $currentDir === 'asc' ? 'selected' : ''; ?>>Oldest first</option>
          </select>
        </div>

        <div class="filter-form-group">
          <label for="search_box">Search (description or student name):</label>
          <input id="search_box" type="text" name="search" placeholder="Enter search term"
            value="<?php echo htmlspecialchars($currentSearch); ?>">
        </div>

        <div class="filter-form-actions">
          <button type="submit" class="btn btn-primary">Apply Filters</button>
          <a href="index.php?page=all_violations" class="btn btn-secondary">Clear All</a>
        </div>
      </form>
    </div>
  </div>

  <p class="muted">Showing <?php echo count($violations); ?> unique student record(s).</p>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <?php
          $baseArgs = ['page' => 'all_violations', 'search' => $currentSearch, 'page_size' => (int) ($_GET['page_size'] ?? 25)];
          $getSortArrow = function ($field) use ($currentSort, $currentDir) {
            if ($currentSort === $field) {
              return ($currentDir === 'asc') ? '▲' : '▼';
            }
            return '⇅';
          };
          $makeLink = function ($field) use ($currentSort, $currentDir, $baseArgs) {
            $dir = 'desc';
            if ($currentSort === $field) {
              $dir = ($currentDir === 'desc') ? 'asc' : 'desc';
            }
            $args = $baseArgs;
            $args['sort'] = $field;
            $args['dir'] = $dir;
            $args['p'] = 1;
            return 'index.php?' . http_build_query($args);
          };
          $isActive = function ($field) use ($currentSort) {
            return $currentSort === $field;
          };
          ?>
          <th><a href="<?php echo $makeLink('date_of_violation'); ?>">Date of Violation <span
                class="sort-arrow <?php echo $isActive('date_of_violation') ? 'active' : ''; ?>"><?php echo $getSortArrow('date_of_violation'); ?></span></a>
          </th>
          <th>Student Num</th>
          <th>Student</th>
              <th>Total Violations</th>
          <th>Officer</th>
          <th><a href="<?php echo $makeLink('severity'); ?>">Severity <span
                class="sort-arrow <?php echo $isActive('severity') ? 'active' : ''; ?>"><?php echo $getSortArrow('severity'); ?></span></a>
          </th>
          <th>Description</th>
          <th><a href="<?php echo $makeLink('created_at'); ?>">Created At <span
                class="sort-arrow <?php echo $isActive('created_at') ? 'active' : ''; ?>"><?php echo $getSortArrow('created_at'); ?></span></a>
          </th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($violations)): ?>
          <tr>
            <td colspan="8">No violations found.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($violations as $v): ?>
            <tr class="violation-row" data-student-id="<?php echo htmlspecialchars($v['student_id']); ?>" style="cursor: pointer;">
              <td><?php echo htmlspecialchars($v['date_of_violation']); ?></td>
              <td><?php echo htmlspecialchars($v['student_num'] ?? '-'); ?></td>
              <td><?php echo htmlspecialchars($v['student_name']); ?></td>
              <td>
                <span class="pill">
                  <?php echo (int) ($v['violation_count'] ?? 0); ?>
                  <?php echo ((int) ($v['violation_count'] ?? 0) === 1) ? 'violation' : 'violations'; ?>
                </span>
              </td>
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

  <?php if (isset($totalPages)): ?>
    <div class="pagination-nav">
      <?php
      $curP = $pageNum ?? 1;
      $tp = $totalPages ?? 1;
      $pageSize = (int) ($_GET['page_size'] ?? 25);
      $startPage = max(1, $curP - 2);
      $endPage = min($tp, $curP + 2);
      ?>

      <?php if ($curP > 1): ?>
        <a
          href="index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=1&page_size=<?php echo $pageSize; ?>">«
          First</a>
        <a
          href="index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=<?php echo $curP - 1; ?>&page_size=<?php echo $pageSize; ?>">‹
          Prev</a>
      <?php endif; ?>

      <span class="page-info">Page <strong><?php echo $curP; ?></strong> of <strong><?php echo $tp; ?></strong></span>

      <div class="page-numbers">
        <?php if ($startPage > 1): ?>
          <span style="padding: 4px 8px; color: #999;">...</span>
        <?php endif; ?>

        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
          <?php if ($i == $curP): ?>
            <span class="page-numbers" style="padding: 4px 8px; color: #666;"><strong><?php echo $i; ?></strong></span>
          <?php else: ?>
            <a href="index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=<?php echo $i; ?>&page_size=<?php echo $pageSize; ?>"
              class="<?php echo $i == $curP ? 'active' : ''; ?>"><?php echo $i; ?></a>
          <?php endif; ?>
        <?php endfor; ?>

        <?php if ($endPage < $tp): ?>
          <span style="padding: 4px 8px; color: #999;">...</span>
        <?php endif; ?>
      </div>

      <?php if ($curP < $tp): ?>
        <a
          href="index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=<?php echo $curP + 1; ?>&page_size=<?php echo $pageSize; ?>">Next
          ›</a>
        <a
          href="index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=<?php echo $tp; ?>&page_size=<?php echo $pageSize; ?>">Last
          »</a>
      <?php endif; ?>

      <span class="page-info" style="margin-left: 12px;">
        <label for="page_size_select" style="margin-right: 4px;">Rows:</label>
        <select id="page_size_select" style="padding: 4px 6px; border: 1px solid #ddd; border-radius: 4px;"
          onchange="location.href='index.php?page=all_violations&sort=<?php echo urlencode($currentSort); ?>&dir=<?php echo urlencode($currentDir); ?>&search=<?php echo urlencode($currentSearch); ?>&p=1&page_size=' + this.value">
          <option value="10" <?php echo $pageSize == 10 ? 'selected' : ''; ?>>10</option>
          <option value="25" <?php echo $pageSize == 25 ? 'selected' : ''; ?>>25</option>
          <option value="50" <?php echo $pageSize == 50 ? 'selected' : ''; ?>>50</option>
          <option value="100" <?php echo $pageSize == 100 ? 'selected' : ''; ?>>100</option>
        </select>
      </span>
    </div>
  <?php endif; ?>

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

<!-- Bootstrap JS (required for modal) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<script>
  function openFilterModal() {
    document.getElementById('filterModal').classList.add('show');
  }

  function closeFilterModal() {
    document.getElementById('filterModal').classList.remove('show');
  }

  // Close modal when clicking outside the modal content
  window.onclick = function (event) {
    const filterModal = document.getElementById('filterModal');
    const studentModal = document.getElementById('studentDetailModal');
    if (event.target === filterModal) {
      closeFilterModal();
    }
    if (event.target === studentModal) {
      closeStudentModal();
    }
  }

  // Close modal on Escape key
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeFilterModal();
      closeStudentModal();
    }
  });

  // Student detail modal functions
  function openStudentModal(studentId) {
    const modalEl = document.getElementById('studentDetailModal');
    const content = document.getElementById('studentDetailContent');
    const modal = new bootstrap.Modal(modalEl);

    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    modal.show();

    const apiUrl = `api/student_detail.php?student_id=${studentId}&t=${Date.now()}`;
    fetch(apiUrl)
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          renderStudentModal(data.student, data.violations, data.violation_counts, data.escalation_history);
        } else {
          content.innerHTML = `<div class="alert alert-danger">${htmlEscape(data.error || 'Error loading details')}</div>`;
        }
      })
      .catch(error => {
        content.innerHTML = `<div class="alert alert-danger">${htmlEscape(error.message)}</div>`;
      });
  }

  function closeStudentModal() {
    bootstrap.Modal.getInstance(document.getElementById('studentDetailModal'))?.hide();
  }

  function renderStudentModal(student, violations, violationCounts, escalationHistory) {
    const content = document.getElementById('studentDetailContent');

    const summaryHtml = (violationCounts || []).map(count => {
      const sev = (count.severity_level || 'unknown').toLowerCase();
      const colorMap = { major: 'danger', moderate: 'warning', minor: 'info', unknown: 'secondary' };
      const badge = colorMap[sev] || 'secondary';
      return `
        <div class="col-md-6 mb-2">
          <div class="card border-start border-4 border-${badge}">
            <div class="card-body py-2 text-center">
              <div class="text-muted small">${htmlEscape(count.severity_level || 'Unknown')}</div>
              <div class="fs-5 fw-bold text-${badge}">${count.count}</div>
            </div>
          </div>
        </div>`;
    }).join('');

    const historyHtml = (violations || []).map(v => {
      const sev = (v.severity_level || 'unknown').toLowerCase();
      const colorMap = { major: 'danger', moderate: 'warning', minor: 'info', unknown: 'secondary' };
      const badge = colorMap[sev] || 'secondary';
      return `
        <div class="card mb-2 border-start border-${badge} border-4">
          <div class="card-body py-2">
            <div class="d-flex justify-content-between align-items-start">
              <span class="badge bg-${badge}">${htmlEscape(v.severity_level || 'Unknown')}</span>
              <small class="text-muted">${new Date(v.date_of_violation).toLocaleDateString()}</small>
            </div>
            <p class="mb-1 mt-2">${htmlEscape(v.description || '')}</p>
            <small class="text-muted">Type: ${htmlEscape(v.type_name || 'N/A')} | Recorded: ${new Date(v.created_at).toLocaleDateString()}</small>
          </div>
        </div>`;
    }).join('');

    const escalationHtml = (escalationHistory || []).map(e => {
      const sources = Array.isArray(e.source_violations) ? e.source_violations : [];
      const sourceHtml = sources.length
        ? `
          <div class="mt-2">
            <div class="text-muted small fw-semibold">Source Minor Violations</div>
            <ul class="mb-0">
              ${sources.map(src => `
                <li>
                  #${src.source_violation_id}
                  (${new Date(src.date_of_violation).toLocaleDateString()})
                  - ${htmlEscape(src.description || '')}
                </li>`).join('')}
            </ul>
          </div>`
        : '<div class="text-muted small mt-2">No source violations listed.</div>';

      return `
        <div class="card mb-2 border-start border-primary border-4">
          <div class="card-body py-2">
            <div class="badge bg-primary mb-2">Escalation #${e.escalation_id}</div>
            <p class="mb-1"><strong>Created:</strong> ${new Date(e.escalated_at).toLocaleDateString()}</p>
            <p class="mb-0"><strong>Major Record:</strong> #${e.major_violation_id} (${new Date(e.major_date_of_violation).toLocaleDateString()})</p>
            ${sourceHtml}
          </div>
        </div>`;
    }).join('');

    content.innerHTML = `
      <div class="row mb-3">
        <div class="col-md-6">
          <h6 class="text-muted">Name</h6>
          <p><strong>${htmlEscape(student.name || 'N/A')}</strong></p>
        </div>
        <div class="col-md-6">
          <h6 class="text-muted">Student ID</h6>
          <p><strong>${htmlEscape(student.student_number || '-')}</strong></p>
        </div>
      </div>
      <div class="mb-3">
        <h6 class="text-muted">Email</h6>
        <p>${htmlEscape(student.email || 'N/A')}</p>
      </div>

      <hr>
      <h6 class="text-muted">Violation Summary</h6>
      <div class="row mb-3">
        ${summaryHtml || '<p class="text-muted small">No summary available.</p>'}
      </div>

      <hr>
      <h6 class="text-muted">Violations History</h6>
      ${historyHtml || '<p class="text-muted small">No violations found.</p>'}

      <hr>
      <h6 class="text-muted">Escalation History</h6>
      ${escalationHtml || '<p class="text-muted small">No escalation events.</p>'}
    `;
  }

  function htmlEscape(text) {
    const map = {
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
  }

  // Add click handlers to violation rows
  document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.violation-row').forEach(row => {
      row.addEventListener('click', function() {
        const studentId = this.getAttribute('data-student-id');
        if (studentId) {
          openStudentModal(studentId);
        }
      });
      // Add hover effect
      row.addEventListener('mouseenter', function() {
        this.style.backgroundColor = '#f3f4f6';
      });
      row.addEventListener('mouseleave', function() {
        this.style.backgroundColor = '';
      });
    });
  });
</script>

<!-- Student Detail Modal -->
<div class="modal fade" id="studentDetailModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header text-white">
        <h5 class="modal-title"><i class="fas fa-user"></i> Student Details</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="studentDetailContent">
        <div class="text-center">
          <div class="spinner-border" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>