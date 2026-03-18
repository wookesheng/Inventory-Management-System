<?php
require_once "includes/header.php";

// Check user's position/role
$current_user_sql = "SELECT position FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $current_user_sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$current_user = mysqli_fetch_assoc($result);
$current_position = strtolower($current_user['position'] ?? '');
$is_administrator = $current_position === 'administrator';
$can_manage_users = in_array($current_position, ['administrator', 'manager']);
?>

<!-- Add jQuery first -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Then add DataTables CSS and JS -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<?php
// Handle user actions
if(isset($_POST['action'])) {
    if(!$can_manage_users) {
        $error = "Only administrators and managers can perform user management actions.";
    } else {
        if($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            
            // If editing, check if target user is an administrator
            if($_POST['action'] == 'edit' && !$is_administrator) {
                $check_sql = "SELECT position FROM users WHERE id = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "i", $id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $target_user = mysqli_fetch_assoc($check_result);
                
                if(strtolower($target_user['position']) === 'administrator') {
                    $error = "You do not have permission to modify administrator accounts.";
                    echo json_encode(['error' => $error]);
                    exit;
                }
            }

            // Continue with existing add/edit logic if no error
            if(!isset($error)) {
                // Prepend 'NP' to the employee ID if not present
                $employeeID = trim($_POST['employeeID']);
                if (!str_starts_with($employeeID, 'NP')) {
                    $employeeID = 'NP' . $employeeID;
                }
                $fullName = trim($_POST['fullName']);
                $email = trim($_POST['email']);
                $phone = trim($_POST['phone']);
                $position = trim($_POST['position']);
                
                // Check if trying to create/edit an administrator or manager account
                if(!$is_administrator && (strtolower($position) === 'administrator' || strtolower($position) === 'manager')) {
                    $error = "Only administrators can create or modify administrator and manager accounts.";
                } else {
                    // Validate employee ID format
                    if(!preg_match("/^NP\d{5}$/", $employeeID)) {
                        $error = "Invalid employee ID format. Must be NP followed by 5 digits (e.g., NP00001)";
                    } else {
                        if($_POST['action'] == 'add') {
                            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                            $sql = "INSERT INTO users (employeeID, password, fullName, email, phone, position) VALUES (?, ?, ?, ?, ?, ?)";
                            $stmt = mysqli_prepare($conn, $sql);
                            mysqli_stmt_bind_param($stmt, "ssssss", $employeeID, $password, $fullName, $email, $phone, $position);
                        } else {
                            if(!empty($_POST['password'])) {
                                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                                $sql = "UPDATE users SET employeeID=?, password=?, fullName=?, email=?, phone=?, position=? WHERE id=?";
                                $stmt = mysqli_prepare($conn, $sql);
                                mysqli_stmt_bind_param($stmt, "ssssssi", $employeeID, $password, $fullName, $email, $phone, $position, $id);
                            } else {
                                $sql = "UPDATE users SET employeeID=?, fullName=?, email=?, phone=?, position=? WHERE id=?";
                                $stmt = mysqli_prepare($conn, $sql);
                                mysqli_stmt_bind_param($stmt, "sssssi", $employeeID, $fullName, $email, $phone, $position, $id);
                            }
                        }
                        
                        if(mysqli_stmt_execute($stmt)) {
                            $success = "User " . ($_POST['action'] == 'add' ? 'added' : 'updated') . " successfully!";
                        } else {
                            $error = "Error: " . mysqli_error($conn);
                        }
                    }
                }
            }
        } elseif($_POST['action'] == 'delete') {
            $id = (int)$_POST['id'];
            
            // Prevent deleting own account
            if($id == $_SESSION['id']) {
                $error = "You cannot delete your own account!";
                echo json_encode(['error' => $error]);
                exit;
            }
            
            // Check if target user is an administrator when manager tries to delete
            if(!$is_administrator) {
                $check_sql = "SELECT position FROM users WHERE id = ?";
                $check_stmt = mysqli_prepare($conn, $check_sql);
                mysqli_stmt_bind_param($check_stmt, "i", $id);
                mysqli_stmt_execute($check_stmt);
                $check_result = mysqli_stmt_get_result($check_stmt);
                $target_user = mysqli_fetch_assoc($check_result);
                
                if(strtolower($target_user['position']) === 'administrator') {
                    $error = "You do not have permission to delete administrator accounts.";
                    echo json_encode(['error' => $error]);
                    exit;
                }
            }

            // Continue with delete if no error
            if(!isset($error)) {
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // Delete related password reset requests
                    $sql = "DELETE FROM password_reset_requests WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    // Delete related notifications
                    $sql = "DELETE FROM notifications WHERE user_id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    // Finally delete the user
                    $sql = "DELETE FROM users WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $id);
                    mysqli_stmt_execute($stmt);
                    
                    // If everything is successful, commit the transaction
                    mysqli_commit($conn);
                    $success = "User deleted successfully!";
                } catch (Exception $e) {
                    // If there's an error, rollback the transaction
                    mysqli_rollback($conn);
                    $error = "Error deleting user: " . $e->getMessage();
                }
            }
        }
    }
}

// Get all users
$sql = "SELECT id, employeeID, fullName, email, phone, position, created_at FROM users ORDER BY employeeID";
$users = mysqli_query($conn, $sql);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">User Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Users</li>
                        </ol>
                    </nav>
                </div>
                <?php if($can_manage_users): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
                    <i class='bx bx-user-plus'></i> Add New User
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if(isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?php echo $success; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Employee ID</th>
                                    <th>Full Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Position</th>
                                    <th>Created At</th>
                                    <?php if($can_manage_users): ?>
                                    <th class="text-end">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($user = mysqli_fetch_assoc($users)): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['employeeID']); ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-2">
                                                    <?php 
                                                    $initials = strtoupper(substr($user['fullName'], 0, 2));
                                                    echo $initials;
                                                    ?>
                                                </div>
                                                <?php echo htmlspecialchars($user['fullName']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone']); ?></td>
                                        <td>
                                            <span class="position-badge position-<?php echo strtolower(str_replace(' ', '-', $user['position'] ?: 'not-set')); ?>">
                                                <?php echo htmlspecialchars($user['position'] ?: 'Not Set'); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <?php if($can_manage_users): ?>
                                            <td class="text-end">
                                                <div class="action-buttons">
                                                    <button type="button" class="btn btn-sm btn-outline-primary edit-user" 
                                                            data-id="<?php echo $user['id']; ?>"
                                                            data-employee-id="<?php echo htmlspecialchars($user['employeeID']); ?>"
                                                            data-full-name="<?php echo htmlspecialchars($user['fullName']); ?>"
                                                            data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                            data-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                                            data-position="<?php echo htmlspecialchars($user['position']); ?>">
                                                        <i class='bx bx-edit-alt'></i>
                                                    </button>
                                                    <?php if($user['id'] != $_SESSION['id']): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-user" data-id="<?php echo $user['id']; ?>">
                                                            <i class='bx bx-trash-alt'></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Only show modals if user can manage users -->
<?php if($can_manage_users): ?>
    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add/Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="userForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" id="userId">
                        
                        <div class="mb-3">
                            <label class="form-label">Employee ID</label>
                            <div class="input-group">
                                <span class="input-group-text">NP</span>
                                <input type="text" class="form-control" name="employeeID" required pattern="\d{5}" 
                                       title="Must be 5 digits (e.g., 00001)" placeholder="00001">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="fullName" required placeholder="Enter full name">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required placeholder="Enter email address">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="tel" class="form-control" name="phone" placeholder="+60 12-345-6789">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Position</label>
                            <select class="form-select" name="position" required>
                                <option value="">Select Position</option>
                                <?php if($is_administrator): ?>
                                <option value="Administrator">Administrator</option>
                                <option value="Manager">Manager</option>
                                <?php endif; ?>
                                <option value="Supervisor">Supervisor</option>
                                <option value="Staff">Staff</option>
                                <option value="Intern">Intern</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="password" id="password">
                                <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                    <i class='bx bx-show'></i>
                                </button>
                            </div>
                            <small class="form-text text-muted">Leave blank to keep existing password when editing</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="post" id="deleteForm">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title" id="deleteModalLabel">
                            <i class='bx bx-error-circle me-2'></i>
                            Confirm Delete
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center py-4">
                        <i class='bx bx-error-circle text-danger' style="font-size: 6rem;"></i>
                        <h4 class="mt-3">Are you sure?</h4>
                        <p class="text-muted mb-0">You are about to delete user:</p>
                        <p class="user-to-delete fw-bold mb-0"></p>
                        <p class="text-danger mt-2 mb-0">This action cannot be undone!</p>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="deleteUserId">
                    </div>
                    <div class="modal-footer justify-content-center border-top-0">
                        <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4">
                            <i class='bx bx-trash me-1'></i>
                            Delete User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.action-buttons .btn i {
    font-size: 1.25rem;
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: var(--main-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    font-weight: 600;
}

.position-badge {
    display: inline-block;
    padding: 0.35em 0.65em;
    font-size: 0.75em;
    font-weight: 500;
    line-height: 1;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: 0.25rem;
}

.position-administrator, .position-admin {
    background-color: #dc3545;
    color: white;
}

.position-manager {
    background-color: #0d6efd;
    color: white;
}

.position-supervisor {
    background-color: #198754;
    color: white;
}

.position-staff {
    background-color: #6c757d;
    color: white;
}

.position-intern {
    background-color: #ffc107;
    color: #000;
}

.position-not-set {
    background-color: #e9ecef;
    color: #495057;
}
</style>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#usersTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            search: "",
            searchPlaceholder: "Search users..."
        }
    });
    
    <?php if($can_manage_users): ?>
    // Handle delete button clicks
    $('.delete-user').on('click', function(e) {
        e.preventDefault();
        const userId = $(this).data('id');
        const row = $(this).closest('tr');
        const userName = row.find('.d-flex.align-items-center').text().trim();
        const userPosition = row.find('.position-badge').text().trim();
        
        if(!<?php echo $is_administrator ? 'true' : 'false' ?> && userPosition.toLowerCase() === 'administrator') {
            alert('You do not have permission to delete administrator accounts.');
            return;
        }
        
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        $('.user-to-delete').text(`${userName} (${userPosition})`);
        $('#deleteUserId').val(userId);
        deleteModal.show();
    });

    // Handle delete form submission
    $('#deleteForm').on('submit', function(e) {
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);
        submitButton.html('<span class="spinner-border spinner-border-sm me-2"></span>Deleting...');
        return true;
    });

    // Handle edit button clicks
    $('.edit-user').on('click', function(e) {
        e.preventDefault();
        const position = $(this).data('position');
        
        if(!<?php echo $is_administrator ? 'true' : 'false' ?> && position.toLowerCase() === 'administrator') {
            alert('You do not have permission to modify administrator accounts.');
            return;
        }
        
        const modal = new bootstrap.Modal(document.getElementById('userModal'));
        const form = document.getElementById('userForm');
        const modalTitle = $('#userModal .modal-title');
        
        modalTitle.text('Edit User');
        form.elements['action'].value = 'edit';
        form.elements['id'].value = $(this).data('id');
        form.elements['employeeID'].value = $(this).data('employeeId').substring(2); // Remove 'NP' prefix
        form.elements['fullName'].value = $(this).data('fullName');
        form.elements['email'].value = $(this).data('email');
        form.elements['phone'].value = $(this).data('phone');
        form.elements['position'].value = $(this).data('position');
        form.elements['password'].value = '';
        
        modal.show();
    });

    // Reset form when modal is hidden
    $('#userModal').on('hidden.bs.modal', function() {
        const form = document.getElementById('userForm');
        const modalTitle = $(this).find('.modal-title');
        modalTitle.text('Add New User');
        form.reset();
        form.elements['action'].value = 'add';
        form.elements['id'].value = '';
    });

    // Toggle password visibility
    $('#togglePassword').on('click', function() {
        const passwordInput = document.getElementById('password');
        const icon = $(this).find('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.removeClass('bx-show').addClass('bx-hide');
        } else {
            passwordInput.type = 'password';
            icon.removeClass('bx-hide').addClass('bx-show');
        }
    });
    <?php endif; ?>
    
    // Auto-format phone number
    $('input[name="phone"]').on('input', function(e) {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 0) {
            if (value.length <= 3) {
                value = value;
            } else if (value.length <= 7) {
                value = value.slice(0,3) + '-' + value.slice(3);
            } else {
                value = value.slice(0,3) + '-' + value.slice(3,7) + '-' + value.slice(7,11);
            }
            $(this).val(value);
        }
    });

    // Add position validation for managers
    $('#userForm').on('submit', function(e) {
        const employeeIDInput = $(this).find('input[name="employeeID"]');
        const employeeID = employeeIDInput.val().trim();
        
        if(!/^\d{5}$/.test(employeeID)) {
            e.preventDefault();
            alert('Employee ID must be exactly 5 digits (e.g., 00001)');
            employeeIDInput.focus();
            return false;
        }
        
        // Format the ID with leading zeros if needed
        employeeIDInput.val(employeeID.padStart(5, '0'));
        return true;
    });
});
</script>

<?php require_once "includes/footer.php"; ?> 