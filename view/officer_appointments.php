<?php
include __DIR__ . '/partials/layout_top.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

if (!isset($_SESSION['officer_id'])) {
    header('Location: index.php?page=login');
    exit;
}

$appointmentModel = new AppointmentModel();

$category_id = $_GET['category'] ?? null;
$status      = $_GET['status'] ?? null;
$search      = trim($_GET['search'] ?? '');

// Get appointments with search support
$appointments_data = $appointmentModel->getAllAppointments($category_id, $status, $search ?: null);
$stats             = $appointmentModel->getAppointmentStats();
$categories        = $appointmentModel->getAllCategories();

function getStatusBadgeColor($status) {
    $colors = [
        'pending'    => 'warning',
        'approved'   => 'info',
        'in_progress'=> 'primary',
        'completed'  => 'success',
        'rejected'   => 'danger',
        'cancelled'  => 'secondary',
        'rescheduled'=> 'info'
    ];
    return $colors[$status] ?? 'secondary';
}
?>

<div class="container-fluid mt-4">
    <h2 class="mb-4"><i class="fas fa-clipboard-list"></i> Appointments Management</h2>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-calendar-day fa-2x text-info mb-2"></i>
                    <h6 class="text-muted">Today's Appointments</h6>
                    <h3><?php echo $stats['today_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-hourglass-half fa-2x text-warning mb-2"></i>
                    <h6 class="text-muted">Pending Review</h6>
                    <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                    <h6 class="text-muted">Completed This Month</h6>
                    <h3><?php echo $stats['completed_this_month'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <i class="fas fa-list fa-2x text-primary mb-2"></i>
                    <h6 class="text-muted">Total Appointments</h6>
                    <h3><?php echo $stats['total_count'] ?? 0; ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Appointments Card -->
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-list"></i> All Appointments</h5>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form method="GET" action="index.php" class="row g-2 mb-4">
                <input type="hidden" name="page" value="officer_appointments">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search"
                           placeholder="Search by student ID or description"
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['category_id']; ?>"
                                <?php echo ($category_id == $cat['category_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="pending"     <?php echo ($status === 'pending')     ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved"    <?php echo ($status === 'approved')    ? 'selected' : ''; ?>>Approved</option>
                        <option value="in_progress" <?php echo ($status === 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        <option value="completed"   <?php echo ($status === 'completed')   ? 'selected' : ''; ?>>Completed</option>
                        <option value="rejected"    <?php echo ($status === 'rejected')    ? 'selected' : ''; ?>>Rejected</option>
                        <option value="cancelled"   <?php echo ($status === 'cancelled')   ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                    <a href="index.php?page=officer_appointments" class="btn btn-secondary">Reset</a>
                </div>
            </form>

            <!-- Appointments Table -->
            <?php if (empty($appointments_data)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No appointments found with the selected filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th>Scheduled Date</th>
                                <th>Assigned Officer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments_data as $apt): ?>
                                <tr style="cursor: pointer;"
                                    onclick="viewAppointmentDetails(<?php echo (int) $apt['appointment_id']; ?>)">
                                    <td><strong><?php echo htmlspecialchars($apt['student_id']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($apt['category_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars(substr($apt['description'] ?? '', 0, 50)); ?></td>
                                    <td><?php echo date('M d, Y - h:i A', strtotime($apt['scheduled_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($apt['officer_name'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadgeColor($apt['status']); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS (single load) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sidebar.js"></script>

<!-- ===== Appointment Details Modal ===== -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" id="appointmentModalFooter">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Approve Modal ===== -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="fas fa-check-circle"></i> Approve Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="approveAppointmentId">
                <div class="mb-3">
                    <label class="form-label">Optional Note</label>
                    <textarea class="form-control" id="approveNote" rows="3"
                              placeholder="Add an optional note for the student..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" onclick="submitApprove()">
                    <i class="fas fa-check"></i> Confirm Approval
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Reject Modal ===== -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-times-circle"></i> Reject Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="rejectAppointmentId">
                <div class="mb-3">
                    <label class="form-label">Reason for Rejection <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="rejectReason" rows="3"
                              placeholder="Provide a reason for rejection..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="submitReject()">
                    <i class="fas fa-times"></i> Confirm Rejection
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Add Note Modal ===== -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sticky-note"></i> Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="noteAppointmentId">
                <div class="mb-3">
                    <label class="form-label">Note <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="noteText" rows="4"
                              placeholder="Enter your note here..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitNote()">
                    <i class="fas fa-save"></i> Save Note
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Update Status Modal ===== -->
<div class="modal fade" id="updateStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sync-alt"></i> Update Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="updateStatusAppointmentId">
                <div class="mb-3">
                    <label class="form-label">New Status</label>
                    <select class="form-select" id="newStatus">
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Optional Note</label>
                    <textarea class="form-control" id="statusNote" rows="3"
                              placeholder="Add an optional note..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusUpdate()">
                    <i class="fas fa-save"></i> Update Status
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentAppointmentId = null;

function statusBadgeColor(status) {
    const map = {
        pending: 'warning', approved: 'info', in_progress: 'primary',
        completed: 'success', rejected: 'danger', cancelled: 'secondary', rescheduled: 'info'
    };
    return map[status] || 'secondary';
}

function viewAppointmentDetails(appointmentId) {
    currentAppointmentId = appointmentId;
    const modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
    const contentDiv = document.getElementById('appointmentDetailsContent');
    const footerDiv = document.getElementById('appointmentModalFooter');

    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    footerDiv.innerHTML  = '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>';

    fetch('api/appointments.php?action=getAppointmentDetails&id=' + appointmentId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                contentDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }
            const apt = data.data;
            const statusColor = statusBadgeColor(apt.status);

            // Build notes HTML
            let notesHtml = '';
            if (apt.notes && apt.notes.length > 0) {
                notesHtml = apt.notes.map(n => `
                    <div class="card mb-2 border-start border-primary border-3">
                        <div class="card-body py-2">
                            <p class="mb-1">${escapeHtml(n.note_text)}</p>
                            <small class="text-muted">
                                <i class="fas fa-user"></i> ${escapeHtml(n.officer_name || 'Unknown Officer')}
                                &nbsp;|&nbsp;
                                <i class="fas fa-clock"></i> ${n.created_at}
                            </small>
                        </div>
                    </div>`).join('');
            } else {
                notesHtml = '<p class="text-muted">No notes yet.</p>';
            }

            contentDiv.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Student ID</h6>
                        <p><strong>${escapeHtml(String(apt.student_id))}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Status</h6>
                        <p><span class="badge bg-${statusColor}">${apt.status.replace('_',' ')}</span></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Category</h6>
                        <p>${escapeHtml(String(apt.category_id))}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Type</h6>
                        <p>${escapeHtml(String(apt.subcategory_id))}</p>
                    </div>
                </div>
                <div class="mb-3">
                    <h6 class="text-muted">Description</h6>
                    <p>${escapeHtml(apt.description || '')}</p>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Scheduled Date</h6>
                        <p>${new Date(apt.scheduled_date).toLocaleString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Assigned Officer</h6>
                        <p>${escapeHtml(apt.officer_name || '—')}</p>
                    </div>
                </div>
                ${apt.evidence_image ? `
                    <div class="mb-3">
                        <h6 class="text-muted">Evidence</h6>
                        <img src="${escapeHtml(apt.evidence_image)}" class="img-fluid" alt="Evidence" style="max-height:300px;">
                    </div>` : ''}
                <hr>
                <h6><i class="fas fa-sticky-note"></i> Officer Notes</h6>
                <div id="notesContainer">${notesHtml}</div>
            `;

            // Build action buttons based on current status
            let actionBtns = '';
            if (apt.status === 'pending') {
                actionBtns += `<button class="btn btn-success btn-sm" onclick="openApproveModal(${apt.appointment_id})"><i class="fas fa-check"></i> Approve</button>
                               <button class="btn btn-danger btn-sm" onclick="openRejectModal(${apt.appointment_id})"><i class="fas fa-times"></i> Reject</button>`;
            }
            actionBtns += ` <button class="btn btn-info btn-sm" onclick="openAddNoteModal(${apt.appointment_id})"><i class="fas fa-sticky-note"></i> Add Note</button>
                            <button class="btn btn-secondary btn-sm" onclick="openUpdateStatusModal(${apt.appointment_id}, '${apt.status}')"><i class="fas fa-sync-alt"></i> Update Status</button>`;

            footerDiv.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>${actionBtns}`;
        })
        .catch(err => {
            console.error(err);
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading appointment details</div>';
        });

    modal.show();
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// --- Approve ---
function openApproveModal(id) {
    document.getElementById('approveAppointmentId').value = id;
    document.getElementById('approveNote').value = '';
    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
    new bootstrap.Modal(document.getElementById('approveModal')).show();
}

function submitApprove() {
    const id   = document.getElementById('approveAppointmentId').value;
    const note = document.getElementById('approveNote').value;

    fetch('api/appointments.php?action=approveAppointment', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: id, note: note})
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('approveModal'))?.hide();
        if (data.success) {
            showToast('Appointment approved successfully!', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// --- Reject ---
function openRejectModal(id) {
    document.getElementById('rejectAppointmentId').value = id;
    document.getElementById('rejectReason').value = '';
    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
    new bootstrap.Modal(document.getElementById('rejectModal')).show();
}

function submitReject() {
    const id     = document.getElementById('rejectAppointmentId').value;
    const reason = document.getElementById('rejectReason').value.trim();
    if (!reason) { alert('Please provide a rejection reason.'); return; }

    fetch('api/appointments.php?action=rejectAppointment', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: id, reason: reason})
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('rejectModal'))?.hide();
        if (data.success) {
            showToast('Appointment rejected.', 'warning');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// --- Add Note ---
function openAddNoteModal(id) {
    document.getElementById('noteAppointmentId').value = id;
    document.getElementById('noteText').value = '';
    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
    new bootstrap.Modal(document.getElementById('addNoteModal')).show();
}

function submitNote() {
    const id   = document.getElementById('noteAppointmentId').value;
    const note = document.getElementById('noteText').value.trim();
    if (!note) { alert('Please enter a note.'); return; }

    fetch('api/appointments.php?action=addNote', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: id, note: note})
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('addNoteModal'))?.hide();
        if (data.success) {
            showToast('Note added successfully!', 'success');
            // Refresh details modal
            setTimeout(() => viewAppointmentDetails(id), 400);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// --- Update Status ---
function openUpdateStatusModal(id, currentStatus) {
    document.getElementById('updateStatusAppointmentId').value = id;
    document.getElementById('newStatus').value = currentStatus;
    document.getElementById('statusNote').value = '';
    bootstrap.Modal.getInstance(document.getElementById('appointmentDetailsModal'))?.hide();
    new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
}

function submitStatusUpdate() {
    const id     = document.getElementById('updateStatusAppointmentId').value;
    const status = document.getElementById('newStatus').value;
    const note   = document.getElementById('statusNote').value;

    fetch('api/appointments.php?action=updateStatus', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: id, status: status, note: note})
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('updateStatusModal'))?.hide();
        if (data.success) {
            showToast('Status updated successfully!', 'success');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// --- Toast helper ---
function showToast(message, type) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show shadow`;
    toast.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}
</script>

</body>
</html>

