<?php $pageTitle = "Dashboard";
include 'view/partials/layout_top.php'; ?>

<style>
  /* Layout tuned for 1920x1080 */
  .dash-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 24px;
    align-items: start;
  }

  .dash-card {
    background: #fff;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
  }

  .dash-card h3 {
    margin: 0 0 10px;
    font-size: 18px;
    font-weight: 800;
  }

  .dash-muted {
    margin: 0 0 10px;
    color: #666;
    font-size: 0.95em;
  }

  /* Fixed card heights to match the reference proportions */
  .card-tall {
    min-height: 360px;
  }

  .card-mid {
    min-height: 340px;
  }

  /* Chart sizing */
  .chart-wrap {
    height: 280px;
  }

  .chart-wrap canvas {
    width: 100% !important;
    height: 100% !important;
  }

  .donut-wrap {
    height: 280px;
    position: relative;
    display: grid;
    place-items: center;
  }

  .donut-wrap canvas {
    width: 280px !important;
    height: 280px !important;
    display: block;
  }

  /* Recent Activity list */
  .activity-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 8px;
  }

  .activity-item {
    border: 1px solid #eef0f4;
    border-radius: 10px;
    padding: 12px;
    background: #fff;
  }

  .activity-top {
    display: flex;
    gap: 10px;
    justify-content: space-between;
    flex-wrap: wrap;
    align-items: center;
  }

  .activity-title {
    font-weight: 800;
    color: #2c3e50;
  }

  .activity-meta {
    color: #666;
    font-size: 0.9em;
  }

  /* Monthly report filter */
  .month-filter {
    display: grid;
    grid-template-columns: auto 1fr auto;
    /* label | calendar | button */
    gap: 10px;
    align-items: center;
    margin: 12px 0 10px;
  }

  .month-filter input[type="month"] {
    width: 100%;
    margin: 0;
  }

  .month-filter button {
    margin: 0;
    height: 40px;
    /* optional: aligns nicely with input */
    padding: 0 16px;
  }

  /* Responsive fallback */
  @media (max-width: 1100px) {
    .dash-grid {
      grid-template-columns: 1fr;
    }

    .card-tall,
    .card-mid {
      min-height: unset;
    }

    .chart-wrap,
    .donut-wrap {
      height: 260px;
    }
  }

  .donut-center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    pointer-events: none;
    text-align: center;
    transform: translateY(-30px);
  }

  .donut-center-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 700;
    margin-bottom: 2px;
  }

  .donut-center-value {
    font-size: 44px;
    font-weight: 900;
    color: #111827;
    line-height: 1;
  }
</style>

<div class="dash-grid">

  <!-- Top-left: Violation's Overview (Line Graph) -->
  <div class="dash-card card-tall">
    <h3>Violation's Overview</h3>
    <p class="dash-muted">Unique students per month grouped by highest violation severity.</p>
    <div class="chart-wrap">
      <canvas id="violationsOverviewChart"></canvas>
    </div>
  </div>

  <!-- Top-right: Total Violations (Overall) Donut -->
  <div class="dash-card card-tall">
    <h3>Total Violations (Overall)</h3>
    <p class="dash-muted">All violation records grouped by severity level (all time).</p>
    <div class="donut-wrap">
      <canvas id="overallDonutChart"></canvas>

      <div class="donut-center">
        <div class="donut-center-label">Total</div>
        <div class="donut-center-value" id="donutTotalValue">
          <?php echo (int) $total_violations; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom-left: Recent Activity -->
  <div class="dash-card card-mid">
    <h3>Recent Activity</h3>
    <p class="dash-muted">Last 4 violations recorded.</p>

    <div class="activity-list">
      <?php if (empty($recentViolations)): ?>
        <div class="activity-item">
          <div class="activity-title">No activity yet</div>
          <div class="activity-meta">No violations have been recorded.</div>
        </div>
      <?php else: ?>
        <?php foreach ($recentViolations as $rv): ?>
          <?php $sev = strtolower($rv['severity_level'] ?? 'unknown'); ?>
          <div class="activity-item">
            <div class="activity-top">
              <div class="activity-title">
                <?php echo htmlspecialchars($rv['student_name']); ?>
                <span class="badge sev-<?php echo htmlspecialchars($sev); ?>">
                  <?php echo htmlspecialchars(ucfirst($sev)); ?>
                </span>
              </div>
              <div class="activity-meta">
                <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($rv['created_at']))); ?>
              </div>
            </div>
            <div class="activity-meta">
              Officer: <?php echo htmlspecialchars($rv['officer_name']); ?> |
              Violation date: <?php echo htmlspecialchars(date('M d, Y', strtotime($rv['date_of_violation']))); ?>
            </div>
            <div style="margin-top:6px;">
              <?php echo nl2br(htmlspecialchars($rv['description'] ?? '')); ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bottom-right: Monthly Report (Bar chart) -->
  <div class="dash-card card-mid">
    <h3>Monthly Report</h3>
    <p class="dash-muted">
      Students grouped by highest violation severity in the selected month (includes students with no violations).
    </p>

    <form class="month-filter" method="GET" action="index.php">
      <input type="hidden" name="page" value="dashboard">
      <label for="month"><b>Month:</b></label>
      <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>">
      <button type="submit" class="btn btn-primary btn-small">Apply</button>
    </form>

    <div class="chart-wrap">
      <canvas id="monthlyBarChart"></canvas>
    </div>
  </div>

</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<script>
  (function () {
    const COLOR_NONE = '#3B82F6';
    const COLOR_MINOR = '#A78BFA';
    const COLOR_MODERATE = '#F59E0B';
    const COLOR_MAJOR = '#EF4444';

    // Yearly overview data from PHP
    const yearly = <?php echo json_encode($yearlyOverview ?? [
      'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
      'none' => array_fill(0, 12, 0),
      'minor' => array_fill(0, 12, 0),
      'moderate' => array_fill(0, 12, 0),
      'major' => array_fill(0, 12, 0),
    ]); ?>;

    // Line chart: Violation's Overview
    new Chart(document.getElementById('violationsOverviewChart'), {
      type: 'line',
      data: {
        labels: yearly.labels,
        datasets: [
          {
            label: 'No Violation',
            data: yearly.none,
            borderColor: COLOR_NONE,
            backgroundColor: 'rgba(59,130,246,0.15)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
          },
          {
            label: 'Minor',
            data: yearly.minor,
            borderColor: COLOR_MINOR,
            backgroundColor: 'rgba(167,139,250,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
          },
          {
            label: 'Moderate',
            data: yearly.moderate,
            borderColor: COLOR_MODERATE,
            backgroundColor: 'rgba(245,158,11,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
          },
          {
            label: 'Major',
            data: yearly.major,
            borderColor: COLOR_MAJOR,
            backgroundColor: 'rgba(239,68,68,0.12)',
            fill: true,
            tension: 0.35,
            pointRadius: 3
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { enabled: true }
        },
        scales: {
          y: {
            beginAtZero: true,
            suggestedMax: 50,
            ticks: { precision: 0 }
          }
        }
      }
    });

    // Donut chart: Total Violations (Overall)
    const overall = <?php echo json_encode($overallViolationCounts ?? ['minor' => 0, 'moderate' => 0, 'major' => 0]); ?>;
    const donutData = [overall.minor, overall.moderate, overall.major];
    const total = donutData.reduce((a, b) => a + b, 0);

    var totalEl = document.getElementById('donutTotalValue');
    if (totalEl) totalEl.textContent = <?php echo (int) $total_violations; ?>;

    new Chart(document.getElementById('overallDonutChart'), {
      type: 'doughnut',
      data: {
        labels: ['Minor', 'Moderate', 'Major'],
        datasets: [{
          data: donutData,
          backgroundColor: [COLOR_MINOR, COLOR_MODERATE, COLOR_MAJOR],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        cutout: '60%',
        plugins: {
          legend: { position: 'bottom' },
          tooltip: { enabled: true },
          title: { display: false }
        }
      }
    });

    // Monthly report bar chart
    const monthly = <?php echo json_encode($monthlyStudentCounts ?? ['none' => 0, 'minor' => 0, 'moderate' => 0, 'major' => 0]); ?>;

    new Chart(document.getElementById('monthlyBarChart'), {
      type: 'bar',
      data: {
        labels: ['No Violation', 'Minor', 'Moderate', 'Major'],
        datasets: [{
          label: 'Students',
          data: [monthly.none, monthly.minor, monthly.moderate, monthly.major],
          backgroundColor: [COLOR_NONE, COLOR_MINOR, COLOR_MODERATE, COLOR_MAJOR],
          borderRadius: 8
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { enabled: true }
        },
        scales: {
          y: { beginAtZero: true, ticks: { precision: 0 } }
        }
      }
    });
  })();

  // Custom logout confirmation dialog
  function confirmLogout(event) {
    event.preventDefault();

    const userConfirmed = confirm(
      '⚠️ Are you sure you want to logout?\n\nYou will need to sign in again to access your account.'
    );

    if (userConfirmed) {
      window.location.href = 'index.php?page=logout';
    }
  }

  // Add logout confirmation to all logout links
  document.querySelectorAll('a[href*="page=logout"]').forEach(link => {
    link.addEventListener('click', confirmLogout);
  });
</script>

<?php include 'view/partials/layout_bottom.php'; ?>