<?php
include __DIR__ . '/partials/layout_top.php';
require_once __DIR__ . '/../config/db_connection.php';
require_once __DIR__ . '/../model/AppointmentModel.php';

if (!isset($_SESSION['officer_id'])) {
    header('Location: index.php?page=login');
    exit;
}

$appointmentModel = new AppointmentModel();

$page = $_GET['page'] ?? 1;
$category_id = $_GET['category'] ?? null;
$status = $_GET['status'] ?? null;

// Get appointments
$appointments_data = $appointmentModel->getAllAppointments($category_id, $status);
$stats = $appointmentModel->getAppointmentStats();
$categories = $appointmentModel->getAllCategories();

function getStatusBadgeColor($status) {
    $colors = [
        'pending' => 'warning',
        'approved' => 'info',
        'in_progress' => 'primary',
        'completed' => 'success',
        'rejected' => 'danger',
        'cancelled' => 'secondary',
        'rescheduled' => 'info'
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
            <div class="row mb-4">
                <div class="col-md-8">
                    <form method="GET" action="index.php?page=officer_appointments" class="row g-2">
                        <div class="col-md-5">
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
                        <div class="col-md-5">
                            <select class="form-select" name="status">
                                <option value="">All Statuses</option>
                                <option value="pending" <?php echo ($status == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($status == 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Appointments List -->
            <?php if (empty($appointments_data) || count($appointments_data) === 0): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> No appointments found with the selected filters.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Category ID</th>
                                <th>Description</th>
                                <th>Scheduled Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments_data as $apt): ?>
    <tr style="cursor: pointer;" onclick="viewAppointmentDetails(<?php echo $apt['appointment_id']; ?>)">
        <td>
            <strong><?php echo htmlspecialchars($apt['student_id']); ?></strong>
        </td>
        <td><?php echo htmlspecialchars($apt['category_id'] ?? 'N/A'); ?></td>
        <td><?php echo htmlspecialchars(substr($apt['description'] ?? '', 0, 50)); ?></td>
        <td><?php echo date('M d, Y - h:i A', strtotime($apt['scheduled_date'])); ?></td>
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

<!-- Bootstrap JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- Sidebar Script -->
<script src="assets/js/sidebar.js"></script>

<!-- Appointment Details Modal -->
<div class="modal fade" id="appointmentDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Appointment Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="appointmentDetailsContent">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="approveAppointment()">Approve</button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/sidebar.js"></script>

<script>
function viewAppointmentDetails(appointmentId) {
    const modal = new bootstrap.Modal(document.getElementById('appointmentDetailsModal'));
    const contentDiv = document.getElementById('appointmentDetailsContent');
    
    // Show loading state
    contentDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    // Fetch appointment details
    fetch('api/appointments.php?action=getAppointmentDetails&id=' + appointmentId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const apt = data.data;
                contentDiv.innerHTML = `
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Student ID</h6>
                            <p><strong>${apt.student_id}</strong></p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Status</h6>
                            <p><span class="badge bg-warning">${apt.status}</span></p>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-muted">Category</h6>
                            <p>${apt.category_id}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">Type</h6>
                            <p>${apt.subcategory_id}</p>
                        </div>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted">Description</h6>
                        <p>${apt.description}</p>
                    </div>
                    <div class="mb-3">
                        <h6 class="text-muted">Scheduled Date</h6>
                        <p>${new Date(apt.scheduled_date).toLocaleString()}</p>
                    </div>
                    ${apt.evidence_image ? `
                        <div class="mb-3">
                            <h6 class="text-muted">Evidence</h6>
                            <img src="${apt.evidence_image}" class="img-fluid" alt="Evidence" style="max-height: 300px;">
                        </div>
                    ` : ''}
                `;
            } else {
                contentDiv.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            contentDiv.innerHTML = '<div class="alert alert-danger">Error loading appointment details</div>';
        });
    
    modal.show();
}

function approveAppointment() {
    alert('Approve functionality coming soon');
}
</script>

</body>
</html>

