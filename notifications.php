<?php
require_once "includes/header.php";

// Get notifications for the current user
$user_id = $_SESSION['id'];

// Get notifications
$sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$notifications = mysqli_stmt_get_result($stmt);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Notifications</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Notifications</li>
                        </ol>
                    </nav>
                </div>
                <div class="action-buttons">
                    <button type="button" id="markAllReadBtn" class="btn btn-primary">
                        <i class='bx bx-check-double me-1'></i>
                        Mark All as Read
                    </button>
                    <button type="button" id="deleteSelectedBtn" class="btn btn-danger" disabled>
                        <i class='bx bx-trash me-1'></i>
                        Delete Selected
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="40px">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="selectAll">
                                        </div>
                                    </th>
                                    <th>Message</th>
                                    <th width="200px">Date</th>
                                    <th width="100px">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($notifications) > 0): ?>
                                    <?php while($notification = mysqli_fetch_assoc($notifications)): ?>
                                        <tr>
                                            <td>
                                                <div class="form-check">
                                                    <input class="form-check-input notification-checkbox" type="checkbox" 
                                                           value="<?php echo $notification['id']; ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                    $icon_class = '';
                                                    switch($notification['type']) {
                                                        case 'LOW_STOCK':
                                                            $icon_class = 'bx-error text-warning';
                                                            break;
                                                        case 'STOCK_IN':
                                                            $icon_class = 'bx-plus-circle text-success';
                                                            break;
                                                        case 'STOCK_OUT':
                                                            $icon_class = 'bx-minus-circle text-danger';
                                                            break;
                                                        default:
                                                            $icon_class = 'bx-bell text-primary';
                                                    }
                                                    ?>
                                                    <i class='bx <?php echo $icon_class; ?> fs-4 me-2'></i>
                                                    <span><?php echo htmlspecialchars($notification['message']); ?></span>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?></td>
                                            <td>
                                                <span class="badge <?php echo $notification['is_read'] ? 'bg-secondary' : 'bg-primary'; ?>">
                                                    <?php echo $notification['is_read'] ? 'Read' : 'Unread'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4">
                                            <div class="no-notifications">
                                                <i class='bx bx-bell-off fs-1 text-muted'></i>
                                                <p class="text-muted mb-0">No notifications found</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.content-wrapper {
    padding: 20px;
    min-height: calc(100vh - 60px);
    background: #f8f9fa;
}

.content-header {
    margin-bottom: 2rem;
}

.page-title {
    font-size: 1.75rem;
    font-weight: 600;
    color: #2d3436;
    margin-bottom: 0.5rem;
}

.breadcrumb {
    margin-bottom: 0;
    background: transparent;
    padding: 0;
}

.breadcrumb-item a {
    color: #6c757d;
    text-decoration: none;
}

.breadcrumb-item.active {
    color: #343a40;
}

.action-buttons {
    display: flex;
    gap: 0.5rem;
}

.card {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.05);
    border-radius: 8px;
}

.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    padding: 1rem;
}

.table td {
    padding: 1rem;
    vertical-align: middle;
}

.table-active {
    background-color: rgba(0,123,255,0.05) !important;
}

.form-check {
    margin: 0;
}

.form-check-input {
    cursor: pointer;
}

.badge {
    padding: 0.5em 0.75em;
    font-weight: 500;
}

.no-notifications {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 2rem;
}

/* Custom Scrollbar */
.table-responsive {
    scrollbar-width: thin;
    scrollbar-color: #dee2e6 #f8f9fa;
}

.table-responsive::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f8f9fa;
}

.table-responsive::-webkit-scrollbar-thumb {
    background-color: #dee2e6;
    border-radius: 3px;
}

@media (min-width: 992px) {
    .content-wrapper {
        padding: 30px;
    }
    
    .table-responsive {
        overflow: visible;
    }
}

/* Animation for new notifications */
@keyframes highlight {
    from { background-color: rgba(0,123,255,0.1); }
    to { background-color: transparent; }
}

.notification-new {
    animation: highlight 2s ease-out;
}
</style>

<script>
$(document).ready(function() {
    // Handle select all checkbox
    $('#selectAll').on('change', function() {
        $('.notification-checkbox').prop('checked', $(this).prop('checked'));
        updateDeleteButton();
    });

    // Handle individual checkboxes
    $('.notification-checkbox').on('change', function() {
        updateDeleteButton();
        const allChecked = $('.notification-checkbox:checked').length === $('.notification-checkbox').length;
        $('#selectAll').prop('checked', allChecked);
    });

    // Update delete button state
    function updateDeleteButton() {
        const checkedCount = $('.notification-checkbox:checked').length;
        $('#deleteSelectedBtn').prop('disabled', checkedCount === 0);
    }

    // Mark all as read
    $('#markAllReadBtn').on('click', function() {
        $.ajax({
            url: 'includes/handle_notification.php',
            type: 'POST',
            data: {
                action: 'mark_all_read'
            },
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error marking notifications as read');
                }
            }
        });
    });

    // Delete selected notifications
    $('#deleteSelectedBtn').on('click', function() {
        if(!confirm('Are you sure you want to delete the selected notifications?')) {
            return;
        }

        const selectedIds = $('.notification-checkbox:checked').map(function() {
            return $(this).val();
        }).get();

        $.ajax({
            url: 'includes/handle_notification.php',
            type: 'POST',
            data: {
                action: 'delete_selected',
                ids: selectedIds
            },
            success: function(response) {
                if(response.success) {
                    location.reload();
                } else {
                    alert(response.message || 'Error deleting notifications');
                }
            }
        });
    });
});
</script>

<?php require_once "includes/footer.php"; ?> 