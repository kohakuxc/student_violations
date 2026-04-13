<?php


// Check if student is logged in
if (!isset($_SESSION['student_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// Include the layout header
include __DIR__ . '/partials/student_layout_top.php';

require_once __DIR__ . '/../controller/StudentAppointmentController.php';
$controller = new StudentAppointmentController();
$appointments_data = $controller->getStudentAppointments();

// Get categories for dropdown
require_once __DIR__ . '/../model/AppointmentModel.php';
$appointmentModel = new AppointmentModel();
$categories = $appointmentModel->getAllCategories();
?>

<div class="container-fluid mt-4">
    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success'];
            unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error'];
            unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="container-fluid mt-4">
        <!-- Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success'];
                unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error'];
                unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Tabs Navigation -->
        <div class="card mb-4">
            <ul class="nav nav-tabs card-header-tabs" role="tablist" style="border-bottom: 0;">
                <li class="nav-item">
                    <a class="nav-link active" id="new-appointment-tab" data-bs-toggle="tab" href="#new-appointment"
                        role="tab">
                        <i class="fas fa-plus"></i> New Appointment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="upcoming-tab" data-bs-toggle="tab" href="#upcoming" role="tab">
                        <i class="fas fa-calendar"></i> Upcoming Appointments
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" id="history-tab" data-bs-toggle="tab" href="#history" role="tab">
                        <i class="fas fa-history"></i> Appointment History
                    </a>
                </li>
            </ul>
        </div>


        <!-- Tab Content -->
        <div class="tab-content">
            <!-- New Appointment Form -->
            <div id="new-appointment" class="tab-pane fade show active">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-file-alt"></i> Create New Appointment</h5>
                    </div>
                    <div class="card-body">
                        <form id="appointmentForm" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Category Selection -->
                                <div class="col-md-6 mb-3">
                                    <label for="category_id" class="form-label">
                                        Appointment Category <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">-- Select Category --</option>
                                        <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['category_id']; ?>">
                                                <?php echo htmlspecialchars($cat['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Subcategory Selection -->
                                <div class="col-md-6 mb-3">
                                    <label for="subcategory_id" class="form-label">
                                        Appointment Type <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="subcategory_id" name="subcategory_id" required
                                        disabled>
                                        <option value="">-- Select Type --</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    Description <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" id="description" name="description" rows="4"
                                    placeholder="Please describe the details of your appointment request..."
                                    required></textarea>
                                <small class="form-text text-muted">Maximum 1000 characters</small>
                            </div>

                            <!-- Evidence Upload -->
                            <div class="mb-3">
                                <label for="evidence_image" class="form-label">
                                    Upload Evidence <span class="badge bg-info">Optional</span>
                                </label>
                                <input type="file" class="form-control" id="evidence_image" name="evidence_image"
                                    accept=".pdf,.jpg,.jpeg" />
                                <small class="form-text text-muted">
                                    Accepted formats: PDF, JPG | Max size: 3MB
                                </small>
                                <div id="file-preview" class="mt-2"></div>
                            </div>

                            <div class="row">
                                <!-- Date Selection -->
                                <div class="col-md-6 mb-3">
                                    <label for="date" class="form-label">
                                        Appointment Date <span class="text-danger">*</span>
                                    </label>
                                    <input type="date" class="form-control" id="date" name="date" required
                                        min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" />
                                    <small class="form-text text-muted">Monday - Friday only</small>
                                </div>

                                <!-- Time Slot Selection -->
                                <div class="col-md-6 mb-3">
                                    <label for="time" class="form-label">
                                        Appointment Time <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="time" name="time" required disabled>
                                        <option value="">-- Select Time --</option>
                                    </select>
                                    <small class="form-text text-muted">8:00 AM - 12:00 PM, 1:00 PM - 5:00 PM</small>
                                </div>
                            </div>

                            <!-- Form Actions -->
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

            <!-- Upcoming Appointments -->
            <div id="upcoming" class="tab-pane fade">
                <div class="row mb-3">
                    <!-- Status Count Cards -->
                    <?php if ($appointments_data['counts']): ?>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-warning text-white">
                                <div class="card-body">
                                    <h5><?php echo $appointments_data['counts']['pending_count'] ?? 0; ?></h5>
                                    <p class="mb-0">Pending</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-info text-white">
                                <div class="card-body">
                                    <h5><?php echo $appointments_data['counts']['approved_count'] ?? 0; ?></h5>
                                    <p class="mb-0">Approved</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-purple text-white">
                                <div class="card-body">
                                    <h5><?php echo $appointments_data['counts']['in_progress_count'] ?? 0; ?></h5>
                                    <p class="mb-0">In Progress</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-success text-white">
                                <div class="card-body">
                                    <h5><?php echo $appointments_data['counts']['completed_count'] ?? 0; ?></h5>
                                    <p class="mb-0">Completed</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2 mb-3">
                            <div class="card text-center bg-danger text-white">
                                <div class="card-body">
                                    <h5><?php echo $appointments_data['counts']['rejected_count'] ?? 0; ?></h5>
                                    <p class="mb-0">Rejected</p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Upcoming Appointments List -->
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Upcoming Appointments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($appointments_data['upcoming'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Type</th>
                                            <th>Date & Time</th>
                                            <th>Officer</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments_data['upcoming'] as $apt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($apt['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($apt['subcategory_name']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($apt['scheduled_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($apt['officer_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeColor($apt['status']); ?>">
                                                        <?php echo ucfirst($apt['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#detailsModal"
                                                        onclick="loadAppointmentDetails(<?php echo $apt['appointment_id']; ?>)">
                                                        View
                                                    </button>
                                                    <?php if ($apt['status'] === 'pending' || $apt['status'] === 'approved'): ?>
                                                        <button type="button" class="btn btn-sm btn-danger"
                                                            onclick="cancelAppointment(<?php echo $apt['appointment_id']; ?>)">
                                                            Cancel
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

            <!-- Appointment History -->
            <div id="history" class="tab-pane fade">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Appointment History</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($appointments_data['all'])): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Type</th>
                                            <th>Date & Time</th>
                                            <th>Officer</th>
                                            <th>Status</th>
                                            <th>Created Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appointments_data['all'] as $apt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($apt['category_name']); ?></td>
                                                <td><?php echo htmlspecialchars($apt['subcategory_name']); ?></td>
                                                <td><?php echo date('M d, Y h:i A', strtotime($apt['scheduled_date'])); ?></td>
                                                <td><?php echo htmlspecialchars($apt['officer_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo getStatusBadgeColor($apt['status']); ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $apt['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($apt['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal"
                                                        data-bs-target="#detailsModal"
                                                        onclick="loadAppointmentDetails(<?php echo $apt['appointment_id']; ?>)">
                                                        View
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
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="detailsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div class="modal fade" id="cancelModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Cancel Appointment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="cancelForm" method="POST" action="index.php?action=cancelAppointment">
                    <div class="modal-body">
                        <input type="hidden" id="cancelAppointmentId" name="appointment_id">
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">Reason for Cancellation <span
                                    class="text-danger">*</span></label>
                            <textarea class="form-control" id="cancelReason" name="reason" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-danger">Confirm Cancellation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <!-- Include jQuery (optional, for AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include JavaScript -->
    <script src="assets/js/student_appointments.js"></script>
    <script src="assets/js/sidebar.js"></script>

    </body>

    </html>

    <?php
    // Helper function for status badge colors
    function getStatusBadgeColor($status)
    {
        $colors = [
            'pending' => 'warning',
            'approved' => 'info',
            'in_progress' => 'purple',
            'completed' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            'rescheduled' => 'cyan'
        ];
        return $colors[$status] ?? 'secondary';
    }
    ?>