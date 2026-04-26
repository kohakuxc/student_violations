<?php $pageTitle = "My Violations";
include 'view/partials/student_layout_top.php'; ?>

<style>
    .dashboard {
        background: white;
        padding: 2rem;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        text-align: center;
    }

    .stat-card h3 {
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
    }

    .stat-number {
        font-size: 2.5rem;
        font-weight: bold;
        margin-bottom: 0;
    }

    .violations-list {
        overflow-x: auto;
    }

    .violations-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 1rem;
        background: white;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .violations-table thead {
        background-color: #f5f7fa;
        border-bottom: 2px solid #e0e0e0;
    }

    .violations-table th {
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: #2c3e50;
    }

    .violations-table td {
        padding: 1rem;
        border-bottom: 1px solid #e0e0e0;
    }

    .violations-table tr:hover {
        background-color: #f9f9f9;
    }

    .badge {
        display: inline-block;
        padding: 0.4rem 0.8rem;
        border-radius: 4px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .badge.sev-major {
        background: #fee2e2;
        color: #991b1b;
    }

    .badge.sev-moderate {
        background: #ffedd5;
        color: #9a3412;
    }

    .badge.sev-minor {
        background: #ede9fe;
        color: #5b21b6;
    }

    .badge.sev-none {
        background: #e5e7eb;
        color: #374151;
    }

    .alert-info {
        background-color: #e3f2fd;
        color: #1565c0;
        border-color: #1565c0;
        padding: 1rem;
        border-radius: 6px;
        margin-bottom: 1.5rem;
        border-left: 4px solid #1565c0;
    }

    .empty-state {
        text-align: center;
        padding: 2rem;
        color: #666;
    }

    .empty-state h3 {
        margin: 0 0 0.5rem;
        color: #2c3e50;
    }

    .violation-desc {
        color: #666;
        line-height: 1.4;
    }

    .escalation-panel {
        margin-top: 2rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        padding: 1rem;
    }

    .escalation-item {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 0.9rem;
        margin-bottom: 0.75rem;
        background: #f9fafb;
    }

    .escalation-item:last-child {
        margin-bottom: 0;
    }

    .source-list {
        margin: 0.4rem 0 0 1.2rem;
    }

    .source-list li {
        margin-bottom: 0.25rem;
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

    .pager-wrap {
        margin-top: 1rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
    }

    .pager-links {
        display: flex;
        gap: 0.4rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .pager-link {
        border: 1px solid #d1d5db;
        padding: 6px 10px;
        border-radius: 8px;
        color: #1f2937;
        text-decoration: none;
        background: #fff;
    }

    .pager-link.active {
        background: #0b5793;
        color: #fff;
        border-color: #0b5793;
    }

    .pager-meta {
        color: #6b7280;
        font-size: 0.9rem;
    }
</style>

<div class="dashboard">
    <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 2rem;">
        <h1 style="margin: 0;">My Violations</h1>
    </div>

    <p style="color: #666; margin-bottom: 2rem;">
        Hello, <strong><?php echo htmlspecialchars($_SESSION['student_name']); ?></strong>.
        Below are your most recent recorded violations.
    </p>

    <!-- Statistics -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3>Total Violations</h3>
            <p class="stat-number"><?php echo $total_violations; ?></p>
        </div>

        <?php if (!empty($violation_counts)): ?>
            <?php foreach ($violation_counts as $count): ?>
                <?php $sev = strtolower($count['severity_level'] ?? 'none'); ?>
                <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <h3><?php echo htmlspecialchars(ucfirst($sev)); ?></h3>
                    <p class="stat-number"><?php echo (int) $count['count']; ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Violations Table -->
    <h2>Violation History</h2>

    <?php if (empty($violations)): ?>
        <div class="empty-state">
            <h3>✅ No violations recorded</h3>
            <p>You have a clean record. Great job!</p>
        </div>
    <?php else: ?>
        <div class="violations-list">
            <table class="violations-table">
                <thead>
                    <tr>
                        <th>Date of Violation</th>
                        <th>Severity Level</th>
                        <th>Type</th>
                        <th>Description</th>
                        <th>Recorded By</th>
                        <th>Recorded On</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($violations as $v): ?>
                        <?php $sev = strtolower($v['severity_level'] ?? 'unknown'); ?>
                        <tr>
                            <td><?php echo htmlspecialchars(date('M d, Y', strtotime($v['date_of_violation']))); ?></td>
                            <td>
                                <span class="badge sev-<?php echo htmlspecialchars($sev); ?>">
                                    <?php echo htmlspecialchars(ucfirst($sev)); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($v['type_name'] ?? 'Unknown'); ?></td>
                            <td>
                                <p class="violation-desc"><?php echo htmlspecialchars($v['description']); ?></p>
                            </td>
                            <td><?php echo htmlspecialchars($v['officer_name']); ?></td>
                            <td><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($v['created_at']))); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="pager-wrap">
                <div class="pager-meta">
                    Page <?php echo (int) $violationsPage; ?> of <?php echo (int) $totalViolationsPages; ?>
                    (<?php echo (int) $total_violations; ?> total)
                </div>
                <div class="pager-links">
                    <?php
                    $vBase = [
                        'page' => 'student_dashboard',
                        'force_dashboard' => 1,
                        'epage' => $escalationsPage,
                    ];
                    ?>
                    <?php if ($violationsPage > 1): ?>
                        <?php $vPrev = $vBase; $vPrev['vpage'] = $violationsPage - 1; ?>
                        <a class="pager-link" href="index.php?<?php echo htmlspecialchars(http_build_query($vPrev)); ?>">Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalViolationsPages; $i++): ?>
                        <?php $vPage = $vBase; $vPage['vpage'] = $i; ?>
                        <a class="pager-link <?php echo $i === (int) $violationsPage ? 'active' : ''; ?>" href="index.php?<?php echo htmlspecialchars(http_build_query($vPage)); ?>"><?php echo (int) $i; ?></a>
                    <?php endfor; ?>

                    <?php if ($violationsPage < $totalViolationsPages): ?>
                        <?php $vNext = $vBase; $vNext['vpage'] = $violationsPage + 1; ?>
                        <a class="pager-link" href="index.php?<?php echo htmlspecialchars(http_build_query($vNext)); ?>">Next</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($showEscalationHistory)): ?>
        <div class="escalation-panel">
            <h2>Escalation History</h2>
            <p class="muted">When 3 minor offenses are reached, they are escalated into 1 Major Offense - Category A.</p>

            <?php if (empty($escalation_history ?? [])): ?>
                <div class="empty-state">
                    <h3>No escalation events</h3>
                    <p>No auto-escalation has been recorded for your account.</p>
                </div>
            <?php else: ?>
                <?php foreach (($escalation_history ?? []) as $e): ?>
                    <div class="escalation-item">
                        <div><span class="pill">Escalation #<?php echo (int) $e['escalation_id']; ?></span></div>
                        <p><strong>Created:</strong> <?php echo htmlspecialchars(date('M d, Y h:i A', strtotime($e['escalated_at']))); ?></p>
                        <p><strong>Major Record:</strong> #<?php echo (int) $e['major_violation_id']; ?> (<?php echo htmlspecialchars(date('M d, Y', strtotime($e['major_date_of_violation']))); ?>)</p>
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

                <div class="pager-wrap">
                    <div class="pager-meta">
                        Page <?php echo (int) $escalationsPage; ?> of <?php echo (int) $totalEscalationPages; ?>
                        (<?php echo (int) $totalEscalations; ?> total)
                    </div>
                    <div class="pager-links">
                        <?php
                        $eBase = [
                            'page' => 'student_dashboard',
                            'force_dashboard' => 1,
                            'vpage' => $violationsPage,
                        ];
                        ?>
                        <?php if ($escalationsPage > 1): ?>
                            <?php $ePrev = $eBase; $ePrev['epage'] = $escalationsPage - 1; ?>
                            <a class="pager-link" href="index.php?<?php echo htmlspecialchars(http_build_query($ePrev)); ?>">Previous</a>
                        <?php endif; ?>

                        <?php for ($j = 1; $j <= $totalEscalationPages; $j++): ?>
                            <?php $ePage = $eBase; $ePage['epage'] = $j; ?>
                            <a class="pager-link <?php echo $j === (int) $escalationsPage ? 'active' : ''; ?>" href="index.php?<?php echo htmlspecialchars(http_build_query($ePage)); ?>"><?php echo (int) $j; ?></a>
                        <?php endfor; ?>

                        <?php if ($escalationsPage < $totalEscalationPages): ?>
                            <?php $eNext = $eBase; $eNext['epage'] = $escalationsPage + 1; ?>
                            <a class="pager-link" href="index.php?<?php echo htmlspecialchars(http_build_query($eNext)); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    // Optional: Add any JavaScript for interactivity if needed// Custom logout confirmation dialog
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

    <?php include 'view/partials/student_layout_bottom.php'; ?>