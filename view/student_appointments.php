<?php
// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// Include the layout header
include __DIR__ . '/partials/student_layout_top.php';

require_once __DIR__ . '/../model/AppointmentModel.php';
$appointmentModel = new AppointmentModel();
$student_id = (int) $_SESSION['student_id'];

// Get all appointments and status counts for this student
$all_appointments      = $appointmentModel->getStudentAppointments($student_id);
$upcoming_appointments = $appointmentModel->getStudentUpcomingAppointments($student_id);
$counts                = $appointmentModel->getStudentAppointmentCounts($student_id);

// Get categories for the new appointment form
$categories = $appointmentModel->getAllCategories();

function getStatusBadgeColor($status) {
    $colors = [
        'pending'     => 'warning',
        'approved'    => 'info',
        'in_progress' => 'primary',
        'completed'   => 'success',
        'rejected'    => 'danger',
        'cancelled'   => 'secondary',
        'rescheduled' => 'info',
    ];
    return $colors[$status] ?? 'secondary';
}
?>

<div class="container-fluid mt-4">
    <!-- Flash messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <div class="card mb-4">
        <ul class="nav nav-tabs card-header-tabs" role="tablist" style="border-bottom:0;">
            <li class="nav-item">
                <a class="nav-link active" id="new-appointment-tab" data-bs-toggle="tab"
                   href="#new-appointment" role="tab">
                    <i class="fas fa-plus"></i> New Appointment
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="upcoming-tab" data-bs-toggle="tab" href="#upcoming" role="tab">
                    <i class="fas fa-calendar"></i> Upcoming
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="history-tab" data-bs-toggle="tab" href="#history" role="tab">
                    <i class="fas fa-history"></i> History
                </a>
            </li>
        </ul>
    </div>

    <div class="tab-content">

        <!-- ===== New Appointment Tab ===== -->
        <div id="new-appointment" class="tab-pane fade show active">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> Create New Appointment</h5>
                </div>
                <div class="card-body">
                    <form id="appointmentForm" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="category_id" class="form-label">
                                    Appointment Category <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="category_id" name="category_id" required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo (int) $cat['category_id']; ?>">
                                            <?php echo htmlspecialchars($cat['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="subcategory_id" class="form-label">
                                    Appointment Type <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="subcategory_id" name="subcategory_id" required disabled>
                                    <option value="">-- Select Type --</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">
                                Description <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" id="description" name="description" rows="4"
                                placeholder="Please describe the details of your appointment request..."
                                required maxlength="1000"></textarea>
                            <small class="form-text text-muted">Maximum 1000 characters</small>
                        </div>

                        <div class="mb-3">
                            <label for="evidence_image" class="form-label">
                                Upload Evidence <span class="badge bg-info">Optional</span>
                            </label>
                            <input type="file" class="form-control" id="evidence_image" name="evidence_image"
                                accept=".pdf,.jpg,.jpeg">
                            <small class="form-text text-muted">Accepted formats: PDF, JPG | Max size: 3MB</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date" class="form-label">
                                    Appointment Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="date" name="date" required
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                                <small class="form-text text-muted">Monday – Friday only</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="time" class="form-label">
                                    Appointment Time <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="time" name="time" required disabled>
                                    <option value="">-- Select Time --</option>
                                </select>
                                <small class="form-text text-muted">8:00 AM – 5:00 PM</small>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-secondary">Clear Form</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Appointment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ===== Upcoming Tab ===== -->
        <div id="upcoming" class="tab-pane fade">

            <!-- Status Count Cards -->
            <?php if ($counts): ?>
            <div class="row mb-3">
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-warning text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['pending_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">Pending</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-info text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['approved_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">Approved</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-primary text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['in_progress_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">In Progress</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-success text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['completed_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">Completed</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-danger text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['rejected_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">Rejected</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-2">
                    <div class="card text-center bg-secondary text-white">
                        <div class="card-body py-2">
                            <h5><?php echo (int)($counts['cancelled_count'] ?? 0); ?></h5>
                            <p class="mb-0 small">Cancelled</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Upcoming Appointments</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($upcoming_appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Date &amp; Time</th>
                                        <th>Officer</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_appointments as $apt): ?>
                                        <tr>
                                            <td><?php echo (int) $apt['appointment_id']; ?></td>
                                            <td><?php echo htmlspecialchars($apt['category_name'] ?? $apt['category_id']); ?></td>
                                            <td><?php echo htmlspecialchars($apt['subcategory_name'] ?? $apt['subcategory_id']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($apt['scheduled_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($apt['officer_name'] ?? '—'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeColor($apt['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-info"
                                                        onclick="viewStudentAppointment(<?php echo (int) $apt['appointment_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                                <?php if (in_array($apt['status'], ['pending', 'approved'])): ?>
                                                    <button class="btn btn-sm btn-danger"
                                                            onclick="confirmCancel(<?php echo (int) $apt['appointment_id']; ?>)">
                                                        <i class="fas fa-ban"></i> Cancel
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You have no upcoming appointments.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ===== History Tab ===== -->
        <div id="history" class="tab-pane fade">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history"></i> Appointment History</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($all_appointments)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Scheduled Date</th>
                                        <th>Officer</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_appointments as $apt): ?>
                                        <tr>
                                            <td><?php echo (int) $apt['appointment_id']; ?></td>
                                            <td><?php echo htmlspecialchars($apt['category_name'] ?? $apt['category_id']); ?></td>
                                            <td><?php echo htmlspecialchars($apt['subcategory_name'] ?? $apt['subcategory_id']); ?></td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($apt['scheduled_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($apt['officer_name'] ?? '—'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeColor($apt['status']); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($apt['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-info"
                                                        onclick="viewStudentAppointment(<?php echo (int) $apt['appointment_id']; ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> You have no appointment history.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div><!-- /tab-content -->
</div>

<!-- ===== Appointment Detail Modal ===== -->
<div class="modal fade" id="studentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-calendar-alt"></i> Appointment Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="studentDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- ===== Cancel Confirmation Modal ===== -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-ban"></i> Cancel Appointment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancelAppointmentId">
                <p>Are you sure you want to cancel this appointment?</p>
                <div class="mb-3">
                    <label for="cancelReason" class="form-label">Reason <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="cancelReason" rows="3" required
                              placeholder="Please provide a reason for cancellation..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Back</button>
                <button type="button" class="btn btn-danger" onclick="submitCancel()">
                    <i class="fas fa-ban"></i> Confirm Cancellation
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sidebar.js"></script>

<script>
// --- Load subcategories when category changes ---
document.getElementById('category_id')?.addEventListener('change', function () {
    const catId = this.value;
    const sub = document.getElementById('subcategory_id');
    sub.innerHTML = '<option value="">Loading...</option>';
    sub.disabled = true;

    if (!catId) { sub.innerHTML = '<option value="">-- Select Type --</option>'; return; }

    fetch('api/appointments.php?action=getSubcategories&category_id=' + catId)
        .then(r => r.json())
        .then(data => {
            sub.innerHTML = '<option value="">-- Select Type --</option>';
            if (data.success) {
                data.data.forEach(s => {
                    sub.innerHTML += `<option value="${s.subcategory_id}">${s.subcategory_name}</option>`;
                });
                sub.disabled = false;
            }
        });
});

// --- Load time slots when date changes ---
document.getElementById('date')?.addEventListener('change', function () {
    const date = this.value;
    const timeEl = document.getElementById('time');
    timeEl.innerHTML = '<option value="">Loading...</option>';
    timeEl.disabled = true;

    if (!date) return;

    fetch('api/appointments.php?action=getAvailableSlots&date=' + date)
        .then(r => r.json())
        .then(data => {
            timeEl.innerHTML = '<option value="">-- Select Time --</option>';
            if (data.success && data.data.length > 0) {
                data.data.forEach(slot => {
                    const val = typeof slot === 'object' ? slot.time : slot;
                    timeEl.innerHTML += `<option value="${val}">${val}</option>`;
                });
                timeEl.disabled = false;
            } else {
                timeEl.innerHTML = '<option value="">No slots available</option>';
            }
        });
});

// --- Submit new appointment form via AJAX ---
document.getElementById('appointmentForm')?.addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'createAppointment');

    fetch('index.php', { method: 'POST', body: formData })
        .then(r => {
            // createAppointment redirects, so handle gracefully
            showToast('Appointment submitted successfully!', 'success');
            this.reset();
            document.getElementById('subcategory_id').disabled = true;
            document.getElementById('time').disabled = true;
        })
        .catch(() => showToast('Error submitting appointment.', 'danger'));
});

// --- View appointment details ---
function viewStudentAppointment(appointmentId) {
    const modal   = new bootstrap.Modal(document.getElementById('studentDetailsModal'));
    const content = document.getElementById('studentDetailsContent');
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    modal.show();

    fetch('api/appointments.php?action=getAppointmentDetails&id=' + appointmentId)
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
                return;
            }
            const apt = data.data;
            const statusColor = statusBadgeColor(apt.status);

            let notesHtml = '';
            if (apt.notes && apt.notes.length > 0) {
                notesHtml = apt.notes.map(n => `
                    <div class="card mb-2 border-start border-primary border-3">
                        <div class="card-body py-2">
                            <p class="mb-1">${escapeHtml(n.note_text)}</p>
                            <small class="text-muted">
                                <i class="fas fa-user"></i> ${escapeHtml(n.officer_name || 'Officer')}
                                &nbsp;|&nbsp;
                                <i class="fas fa-clock"></i> ${n.created_at}
                            </small>
                        </div>
                    </div>`).join('');
            } else {
                notesHtml = '<p class="text-muted small">No notes from officer yet.</p>';
            }

            content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-6">
                        <h6 class="text-muted">Appointment ID</h6>
                        <p><strong>#${apt.appointment_id}</strong></p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">Status</h6>
                        <p><span class="badge bg-${statusColor} fs-6">${apt.status.replace('_',' ')}</span></p>
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
                        <img src="${escapeHtml(apt.evidence_image)}" class="img-fluid" style="max-height:200px;" alt="Evidence">
                    </div>` : ''}
                <hr>
                <h6><i class="fas fa-sticky-note"></i> Officer Notes</h6>
                ${notesHtml}
            `;
        })
        .catch(() => {
            content.innerHTML = '<div class="alert alert-danger">Error loading details</div>';
        });
}

// --- Cancel appointment ---
function confirmCancel(id) {
    document.getElementById('cancelAppointmentId').value = id;
    document.getElementById('cancelReason').value = '';
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function submitCancel() {
    const id     = document.getElementById('cancelAppointmentId').value;
    const reason = document.getElementById('cancelReason').value.trim();
    if (!reason) { alert('Please provide a cancellation reason.'); return; }

    fetch('api/appointments.php?action=cancelAppointment', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({appointment_id: id, reason: reason})
    })
    .then(r => r.json())
    .then(data => {
        bootstrap.Modal.getInstance(document.getElementById('cancelModal'))?.hide();
        if (data.success) {
            showToast('Appointment cancelled.', 'warning');
            setTimeout(() => location.reload(), 1200);
        } else {
            showToast(data.message, 'danger');
        }
    });
}

// --- Helpers ---
function statusBadgeColor(status) {
    const map = {
        pending: 'warning', approved: 'info', in_progress: 'primary',
        completed: 'success', rejected: 'danger', cancelled: 'secondary', rescheduled: 'info'
    };
    return map[status] || 'secondary';
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function showToast(message, type) {
    let c = document.getElementById('toastContainer');
    if (!c) {
        c = document.createElement('div');
        c.id = 'toastContainer';
        c.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;';
        document.body.appendChild(c);
    }
    const t = document.createElement('div');
    t.className = `alert alert-${type} alert-dismissible fade show shadow`;
    t.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
    c.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>

</body>
</html>
