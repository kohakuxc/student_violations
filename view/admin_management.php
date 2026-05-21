<?php $pageTitle = 'Superadmin'; include 'view/partials/layout_top.php'; ?>

<div class="page-header">
    <div>
        <h2 class="page-title">Superadmin Management</h2>
        <p class="text-muted">Manage admin accounts, student access, and reset links.</p>
    </div>
    <a href="index.php?page=dashboard" class="btn btn-primary btn-small">← Back to Dashboard</a>
</div>

<?php if (!empty($flash)): ?>
    <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white" >Create Admin</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="index.php?page=admin_management" data-confirm="Create this admin account?">
                    <?php
                        require_once __DIR__ . '/../helper/CsrfHelper.php';
                        $formKey = 'create_admin_form';
                        $formToken = csrfGenerateFormToken($formKey);
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="create_admin">

                    <div class="form-group">
                        <label for="new_username">Username</label>
                        <input type="text" id="new_username" name="username" required>
                    </div>
                    <div class="form-group">
                        <label for="new_name">Full name</label>
                        <input type="text" id="new_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Temporary password</label>
                        <input type="password" id="new_password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label class="checkbox-item">
                            <input type="checkbox" name="is_admin" value="1" checked>
                            <span>Admin access</span>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="is_superadmin" value="1">
                            <span>Superadmin access</span>
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" name="can_import_excel" value="1">
                            <span>Can import Excel</span>
                        </label>
                    </div>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0 text-white">Student Access List</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="index.php?page=admin_management" data-confirm="Add these students to the access list?">
                    <?php
                        $formKey = 'student_access_bulk';
                        $formToken = csrfGenerateFormToken($formKey);
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="add_student_accounts">

                    <label for="bulk_emails">Student emails (comma or newline separated)</label>
                    <textarea id="bulk_emails" name="bulk_emails" rows="4" placeholder="student1@fairview.sti.edu.ph&#10;student2@fairview.sti.edu.ph"></textarea>
                    <button type="submit" class="btn btn-primary mt-2">Add / Enable Students</button>
                </form>

                <hr style="margin: 20px 0;">

                <form method="POST" action="index.php?page=admin_management" enctype="multipart/form-data" data-confirm="Import student emails from this file?">
                    <?php
                        $formKey = 'student_access_import';
                        $formToken = csrfGenerateFormToken($formKey);
                    ?>
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="action" value="import_student_accounts">

                    <label for="import_file">Upload student email file</label>
                    <input type="file" id="import_file" name="import_file" accept=".csv,.xlsx" required>
                    <small class="text-muted">Use a CSV or XLSX file with one column only. The first row may be a header such as email or student_email.</small>

                    <button type="submit" class="btn btn-secondary mt-2">Import File</button>
                </form>

                <div style="margin-top:16px;">
                    <h6>Current Students</h6>
                    <div style="max-height:200px;overflow:auto;">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Email</th>
                                    <th>Status</th>
                                    <th>Update</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentAccounts as $account): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($account['email']); ?></td>
                                        <td><?php echo !empty($account['is_enabled']) ? 'Enabled' : 'Disabled'; ?></td>
                                        <td>
                                            <form method="POST" action="index.php?page=admin_management" data-confirm="Update this student account?">
                                                <?php
                                                    $formKey = 'student_toggle_' . (int) $account['account_id'];
                                                    $formToken = csrfGenerateFormToken($formKey);
                                                ?>
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="toggle_student_account">
                                                <input type="hidden" name="account_id" value="<?php echo (int) $account['account_id']; ?>">
                                                <select name="is_enabled" class="form-select form-select-sm">
                                                    <option value="1" <?php echo !empty($account['is_enabled']) ? 'selected' : ''; ?>>Enabled</option>
                                                    <option value="0" <?php echo empty($account['is_enabled']) ? 'selected' : ''; ?>>Disabled</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-secondary mt-1">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($studentAccounts)): ?>
                                    <tr><td colspan="3" class="text-muted text-center">No student accounts yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0 text-white">Admin Accounts</h5>
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Permissions</th>
                            <th>Reset</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($admins as $admin): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                <td><?php echo htmlspecialchars($admin['name']); ?></td>
                                <td><?php echo !empty($admin['is_active']) ? 'Active' : 'Disabled'; ?></td>
                                <td style="min-width:180px;">
                                    <form method="POST" action="index.php?page=admin_management" data-confirm="Update this admin account?">
                                        <?php
                                            $formKey = 'admin_update_' . (int) $admin['officer_id'];
                                            $formToken = csrfGenerateFormToken($formKey);
                                        ?>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="update_admin">
                                        <input type="hidden" name="officer_id" value="<?php echo (int) $admin['officer_id']; ?>">
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="is_active" value="1" <?php echo !empty($admin['is_active']) ? 'checked' : ''; ?>>
                                            <span>Active</span>
                                        </label>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="is_admin" value="1" <?php echo !empty($admin['is_admin']) ? 'checked' : ''; ?>>
                                            <span>Admin</span>
                                        </label>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="is_superadmin" value="1" <?php echo !empty($admin['is_superadmin']) ? 'checked' : ''; ?>>
                                            <span>Superadmin</span>
                                        </label>
                                        <label class="checkbox-item">
                                            <input type="checkbox" name="can_import_excel" value="1" <?php echo !empty($admin['can_import_excel']) ? 'checked' : ''; ?>>
                                            <span>Excel Import</span>
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-secondary mt-1">Save</button>
                                    </form>
                                </td>
                                <td>
                                    <form method="POST" action="index.php?page=admin_management" data-confirm="Generate a password reset link for this admin?">
                                        <?php
                                            $formKey = 'admin_reset_' . (int) $admin['officer_id'];
                                            $formToken = csrfGenerateFormToken($formKey);
                                        ?>
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="form_key" value="<?php echo htmlspecialchars($formKey, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($formToken, ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="action" value="generate_reset">
                                        <input type="hidden" name="officer_id" value="<?php echo (int) $admin['officer_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Generate Link</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($admins)): ?>
                            <tr><td colspan="5" class="text-muted text-center">No admin accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h5 class="mb-0 text-white">Recent Audit Logs</h5>
            </div>
            <div class="card-body">
                <div style="max-height:260px;overflow:auto;">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Actor</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                                    <td><?php echo htmlspecialchars(($log['actor_role'] ?? '') . ' #' . ($log['actor_officer_id'] ?? '')); ?></td>
                                    <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($auditLogs)): ?>
                                <tr><td colspan="3" class="text-muted text-center">No audit logs yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'view/partials/layout_bottom.php'; ?>
