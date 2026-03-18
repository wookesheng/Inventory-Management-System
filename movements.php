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
$can_approve = in_array($current_position, ['administrator', 'manager']);

// Handle movement search
$search_results = null;
if(isset($_POST['search'])) {
    $search_term = trim($_POST['search_term']);
    
    $sql = "SELECT m.*, i.sku, i.name as item_name, u.fullName as user_name 
            FROM inventory_movements m 
            JOIN inventory_items i ON m.item_id = i.id 
            JOIN users u ON m.user_id = u.id 
            WHERE i.deleted_at IS NULL 
            AND (i.sku LIKE ? OR i.name LIKE ? OR m.delivery_type LIKE ?)
            ORDER BY m.created_at DESC";
            
    $stmt = mysqli_prepare($conn, $sql);
    $search_pattern = "%{$search_term}%";
    mysqli_stmt_bind_param($stmt, "sss", $search_pattern, $search_pattern, $search_pattern);
    mysqli_stmt_execute($stmt);
    $search_results = mysqli_stmt_get_result($stmt);
}

// Get items for dropdown
$items = mysqli_query($conn, "SELECT id, sku, name FROM inventory_items WHERE deleted_at IS NULL ORDER BY name");

// Get movement history with item and user details
$sql = "SELECT m.*, i.sku, i.name as item_name, u.fullName as user_name 
        FROM inventory_movements m 
        JOIN inventory_items i ON m.item_id = i.id 
        JOIN users u ON m.user_id = u.id 
        WHERE i.deleted_at IS NULL
        ORDER BY m.created_at DESC";
$movements = mysqli_query($conn, $sql);

// Debug logging for first row
if ($movements && mysqli_num_rows($movements) > 0) {
    $first_row = mysqli_fetch_assoc($movements);
    error_log("First movement row data: " . print_r($first_row, true));
    mysqli_data_seek($movements, 0); // Reset pointer to beginning
}

// Debug logging
error_log("POST data: " . print_r($_POST, true));

// Function to send email notification
function sendMovementNotificationEmail($to, $movement_details) {
    error_log("Attempting to send email notification to: " . $to);
    error_log("Movement details: " . print_r($movement_details, true));

    $subject = "New Inventory Movement Requires Review";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .details { 
                background: #f8f9fa; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                font-size: 12px; 
                color: #666; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>New Inventory Movement Notification</h2>
            <p>A new inventory movement has been recorded and requires your review:</p>
            <div class='details'>
                <p><strong>Item:</strong> {$movement_details['item_name']}</p>
                <p><strong>Movement Type:</strong> {$movement_details['movement_type']}</p>
                <p><strong>Delivery Type:</strong> {$movement_details['delivery_type']}</p>";
    
    // Add delivery address if it exists
    if (!empty($movement_details['delivery_address'])) {
        $message .= "<p><strong>Delivery Address:</strong> {$movement_details['delivery_address']}</p>";
    }
    
    $message .= "
                <p><strong>Quantity:</strong> {$movement_details['quantity']}</p>
                <p><strong>Recorded by:</strong> {$movement_details['user_name']}</p>
            </div>
            <p>Please log in to the system to review and approve/reject this movement.</p>
            <div class='footer'>
                <p>This is an automated message from Nehemiah Prestress Inventory Management System.</p>
            </div>
        </div>
    </body>
    </html>";

    // Additional headers
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: Nehemiah Prestress <kesheng@ypccollege.edu.my>',
        'Reply-To: kesheng@ypccollege.edu.my',
        'X-Mailer: PHP/' . phpversion()
    );

    error_log("Sending email with headers: " . print_r($headers, true));

    // Try to send email and log the result
    $mail_result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if ($mail_result) {
        error_log("Email sent successfully to: " . $to);
        return true;
    } else {
        error_log("Failed to send email to: " . $to);
        error_log("Mail error info: " . error_get_last()['message']);
        return false;
    }
}

// Handle new movement
if(isset($_POST['action']) && $_POST['action'] == 'add_movement') {
    // Prevent any output before JSON response
    ob_clean();
    header('Content-Type: application/json');
    
    $response = ['success' => false, 'message' => ''];
    
    try {
        // Validate required fields
        if(empty($_POST['item_id'])) {
            throw new Exception("Please select an item");
        }
        if(empty($_POST['movement_type'])) {
            throw new Exception("Please select a movement type");
        }
        if(empty($_POST['delivery_type'])) {
            throw new Exception("Please select a delivery type");
        }
        if(empty($_POST['quantity']) || $_POST['quantity'] <= 0) {
            throw new Exception("Please enter a valid quantity");
        }

        $item_id = (int)$_POST['item_id'];
        $movement_type = $_POST['movement_type'];
        $delivery_type = $_POST['delivery_type'];
        $quantity = (int)$_POST['quantity'];
        $reference_number = trim($_POST['reference_number'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $user_id = $_SESSION['id'];

        // Debug logging
        error_log("Processing movement - Item ID: $item_id, Type: $movement_type, Delivery: $delivery_type, Quantity: $quantity");
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        // Check if there's enough stock for OUT movements first
        if($movement_type == 'OUT') {
            $check_sql = "SELECT quantity FROM inventory_items WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $item_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $current_stock = mysqli_fetch_assoc($result)['quantity'];
            
            if($current_stock < $quantity) {
                throw new Exception("Not enough stock available. Current stock: $current_stock");
            }
        }
        
        // Insert movement record
        $sql = "INSERT INTO inventory_movements (item_id, movement_type, delivery_type, quantity, reference_number, notes, user_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if(!$stmt) {
            throw new Exception("Error preparing statement: " . mysqli_error($conn));
        }
        
        mysqli_stmt_bind_param($stmt, "issiisi", $item_id, $movement_type, $delivery_type, $quantity, $reference_number, $notes, $user_id);
        
        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error recording movement: " . mysqli_stmt_error($stmt));
        }
        
        // Get the new movement ID
        $movement_id = mysqli_insert_id($conn);
        
        // Get item name for notification
        $item_sql = "SELECT name FROM inventory_items WHERE id = ?";
        $item_stmt = mysqli_prepare($conn, $item_sql);
        mysqli_stmt_bind_param($item_stmt, "i", $item_id);
        mysqli_stmt_execute($item_stmt);
        $item_result = mysqli_stmt_get_result($item_stmt);
        $item_name = mysqli_fetch_assoc($item_result)['name'];
        
        // Get user name for notification
        $user_sql = "SELECT fullName FROM users WHERE id = ?";
        $user_stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, "i", $user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user_name = mysqli_fetch_assoc($user_result)['fullName'];
        
        // Prepare movement details for email
        $movement_details = [
            'item_name' => $item_name,
            'movement_type' => $movement_type,
            'delivery_type' => $delivery_type,
            'quantity' => $quantity,
            'user_name' => $user_name
        ];
        
        // Get administrators and managers emails
        $admin_sql = "SELECT id, email, fullName FROM users WHERE LOWER(position) IN ('administrator', 'manager')";
        $admin_result = mysqli_query($conn, $admin_sql);
        
        error_log("Found administrators/managers: " . mysqli_num_rows($admin_result));
        
        // Send notifications
        $email_success = false;
        while($admin = mysqli_fetch_assoc($admin_result)) {
            error_log("Preparing to send notification to {$admin['fullName']} ({$admin['email']})");
            
            if(sendMovementNotificationEmail($admin['email'], $movement_details)) {
                $email_success = true;
                
                // Insert notification into the database
                $notification_sql = "INSERT INTO notifications (type, message, user_id) VALUES (?, ?, ?)";
                $notification_stmt = mysqli_prepare($conn, $notification_sql);
                $notification_type = 'MOVEMENT_REVIEW';
                $notification_message = "New movement recorded for item '{$movement_details['item_name']}' requires your review.";
                mysqli_stmt_bind_param($notification_stmt, "ssi", $notification_type, $notification_message, $admin['id']);
                mysqli_stmt_execute($notification_stmt);
                
                error_log("Created database notification for user: " . $admin['id']);
            }
        }
        
        if (!$email_success) {
            error_log("WARNING: Failed to send email notifications to any administrators/managers");
        }
        
        // Update inventory quantity
        $quantity_change = $movement_type == 'IN' ? $quantity : -$quantity;
        
        $sql = "UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $quantity_change, $item_id);
        
        if(!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating inventory: " . mysqli_stmt_error($stmt));
        }
        
        // Check if item is now low in stock
        $sql = "SELECT quantity, minimum_stock FROM inventory_items WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $item_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $item = mysqli_fetch_assoc($result);
        
        if($item['quantity'] <= $item['minimum_stock']) {
            // Create low stock notification for all relevant users
            $sql = "INSERT INTO notifications (type, message, user_id) 
                   SELECT 'LOW_STOCK', 
                          CONCAT('Item \"', (SELECT name FROM inventory_items WHERE id = ?), '\" is running low on stock (', ?, ' units remaining)'),
                          id
                   FROM users 
                   WHERE LOWER(position) IN ('administrator', 'manager', 'supervisor', 'worker', 'staff')";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $item_id, $item['quantity']);
            mysqli_stmt_execute($stmt);
        }
        
        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Movement recorded successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $response['success'] = false;
        $response['message'] = $e->getMessage();
        error_log("Movement error: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Movements</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Movements</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <div class="row">
                <!-- Movement History Table -->
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Movement History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="movementsTable">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Item</th>
                                            <th>Type</th>
                                            <th>Delivery Type</th>
                                            <th>Delivery Address</th>
                                            <th>Quantity</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($movement = mysqli_fetch_assoc($movements)): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i', strtotime($movement['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($movement['item_name']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $movement['movement_type'] == 'IN' ? 'success' : 'danger'; ?>">
                                                        <?php echo $movement['movement_type']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <?php echo htmlspecialchars($movement['delivery_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php 
                                                    if ($movement['delivery_type'] === 'Delivery To Site' && !empty($movement['delivery_address'])) {
                                                        echo htmlspecialchars($movement['delivery_address']); 
                                                    } else {
                                                        echo '-';
                                                    }
                                                ?></td>
                                                <td><?php echo number_format($movement['quantity']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php 
                                                        echo isset($movement['status']) ? 
                                                            ($movement['status'] === 'confirmed' ? 'success' : 
                                                            ($movement['status'] === 'rejected' ? 'danger' : 'warning'))
                                                            : 'warning'; 
                                                    ?>">
                                                        <?php echo isset($movement['status']) ? ucfirst($movement['status']) : 'Pending'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                <?php if($can_approve && (!isset($movement['status']) || $movement['status'] === 'pending')): ?>
                                                    <button type="button" class="btn btn-sm btn-primary review-movement"
                                                            data-id="<?php echo $movement['id']; ?>"
                                                            data-item="<?php echo htmlspecialchars($movement['item_name']); ?>"
                                                            data-type="<?php echo $movement['movement_type']; ?>"
                                                            data-delivery="<?php echo htmlspecialchars($movement['delivery_type']); ?>"
                                                            data-address="<?php echo htmlspecialchars($movement['delivery_address'] ?? ''); ?>"
                                                            data-quantity="<?php echo $movement['quantity']; ?>">
                                                        Review
                                                    </button>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Record Movement Form -->
                <div class="col-lg-3">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Record Movement</h5>
                        </div>
                        <div class="card-body">
                            <form id="movementForm">
                                <div class="mb-3">
                                    <label class="form-label">Items</label>
                                    <div id="itemsList">
                                        <div class="item-entry mb-2">
                                            <div class="d-flex gap-2">
                                                <select class="form-select item-select" name="items[0][id]" required>
                                        <option value="">Select Item</option>
                                        <?php mysqli_data_seek($items, 0); ?>
                                        <?php while($item = mysqli_fetch_assoc($items)): ?>
                                            <option value="<?php echo $item['id']; ?>">
                                                <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['sku']); ?>)
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                                <input type="number" class="form-control" name="items[0][quantity]" placeholder="Quantity" required min="1">
                                                <button type="button" class="btn btn-danger remove-item" style="display: none;">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-secondary mt-2" id="addItem">
                                        <i class='bx bx-plus'></i> Add Another Item
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Movement Type</label>
                                    <select class="form-select" name="movement_type" required>
                                        <option value="">Select Type</option>
                                        <option value="IN">Stock In</option>
                                        <option value="OUT">Stock Out</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Delivery Type</label>
                                    <select class="form-select" name="delivery_type" id="deliveryType" required>
                                        <option value="">Select Delivery Type</option>
                                        <option value="Delivery To Site">Delivery To Site</option>
                                        <option value="Delivery To Warehouse">Delivery To Warehouse</option>
                                    </select>
                                </div>

                                <div class="mb-3" id="siteAddressField" style="display: none;">
                                    <label class="form-label">Delivery Address</label>
                                    <textarea class="form-control" name="delivery_address" rows="2" 
                                              placeholder="Enter the site delivery address"></textarea>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn btn-primary">
                                        <i class='bx bx-save me-1'></i> Record Movement
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Approval Modal -->
<div class="modal fade" id="approvalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Review Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="movement-details mb-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="rejectBtn">
                    <i class='bx bx-x-circle me-1'></i> Reject
                </button>
                <button type="button" class="btn btn-success" id="confirmBtn">
                    <i class='bx bx-check-circle me-1'></i> Confirm
                </button>
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

.card {
    border: none;
    box-shadow: 0 0 20px rgba(0,0,0,0.05);
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.card-header {
    background-color: #fff;
    border-bottom: 1px solid #eee;
    padding: 1rem 1.25rem;
}

.card-title {
    color: #2d3436;
    font-weight: 600;
}

.table {
    margin-bottom: 0;
}

.table th {
    font-weight: 600;
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
}

.table td {
    vertical-align: middle;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-color: #dee2e6;
}

.form-control:focus, .form-select:focus {
    border-color: var(--main-color);
    box-shadow: 0 0 0 0.2rem rgba(139, 0, 0, 0.25);
}

@media (min-width: 992px) {
    .content-wrapper {
        padding: 30px;
    }
}

/* Movement Type Badges */
.badge-stock-in {
    background-color: #28a745;
    color: white;
}

.badge-stock-out {
    background-color: #dc3545;
    color: white;
}

.badge-transfer {
    background-color: #007bff;
    color: white;
}

.badge-adjustment {
    background-color: #ffc107;
    color: #000;
}

/* Add styles for the delivery type filter */
#movementsTable_filter {
    display: flex;
    align-items: center;
    gap: 1rem;
}

#movementsTable_filter select {
    width: auto;
    min-width: 200px;
}

.badge.bg-info {
    background-color: #0dcaf0 !important;
}

/* Add styles for better form layout in thinner column */
.col-lg-3 .card {
    position: sticky;
    top: 20px;
}

.col-lg-3 .form-select,
.col-lg-3 .form-control {
    font-size: 0.9rem;
}

.col-lg-3 .item-entry {
    margin-bottom: 0.75rem;
}

.col-lg-3 .item-entry .d-flex {
    gap: 0.5rem;
}

.col-lg-3 .btn {
    padding: 0.375rem 0.75rem;
    font-size: 0.9rem;
}

/* Adjust table for wider column */
.col-lg-9 .table {
    font-size: 0.95rem;
}

.col-lg-9 .table th {
    white-space: nowrap;
}

.col-lg-9 .table td {
    vertical-align: middle;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .col-lg-3 .card {
        position: static;
        margin-bottom: 1.5rem;
    }
}
</style>

<!-- Add jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then DataTables -->
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>
<!-- Then Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Add DataTables CSS -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.24/css/dataTables.bootstrap5.min.css">

<!-- Add SweetAlert2 CSS and JS in the header section -->
<link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable with proper configuration
    const table = $('#movementsTable').DataTable({
        order: [[0, 'desc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            search: "",
            searchPlaceholder: "Search movements..."
        },
        columnDefs: [
            { targets: '_all', defaultContent: '-' },
            { targets: -1, orderable: false, searchable: false }
        ],
        responsive: true
    });

    // Show/hide delivery address field based on delivery type selection
    const deliveryTypeSelect = document.getElementById('deliveryType');
    const siteAddressField = document.getElementById('siteAddressField');
    
    deliveryTypeSelect.addEventListener('change', function() {
        if (this.value === 'Delivery To Site') {
            siteAddressField.style.display = 'block';
            siteAddressField.querySelector('textarea').setAttribute('required', 'required');
        } else {
            siteAddressField.style.display = 'none';
            siteAddressField.querySelector('textarea').removeAttribute('required');
        }
    });

    // Handle adding new items
    let itemCount = 1;
    const itemsList = document.getElementById('itemsList');
    const addItemBtn = document.getElementById('addItem');

    addItemBtn.addEventListener('click', function() {
        const itemEntry = document.createElement('div');
        itemEntry.className = 'item-entry mb-2';
        itemEntry.innerHTML = `
            <div class="d-flex gap-2">
                <select class="form-select item-select" name="items[${itemCount}][id]" required>
                    <option value="">Select Item</option>
                    <?php mysqli_data_seek($items, 0); ?>
                    <?php while($item = mysqli_fetch_assoc($items)): ?>
                        <option value="<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['name']); ?> (<?php echo htmlspecialchars($item['sku']); ?>)
                        </option>
                    <?php endwhile; ?>
                </select>
                <input type="number" class="form-control" name="items[${itemCount}][quantity]" placeholder="Quantity" required min="1">
                <button type="button" class="btn btn-danger remove-item">
                    <i class='bx bx-trash'></i>
                </button>
            </div>
        `;
        itemsList.appendChild(itemEntry);
        itemCount++;

        // Show remove button for first item if there's more than one item
        if (itemCount > 1) {
            document.querySelector('.remove-item').style.display = 'block';
        }
    });

    // Handle removing items
    itemsList.addEventListener('click', function(e) {
        if (e.target.closest('.remove-item')) {
            const itemEntry = e.target.closest('.item-entry');
            itemEntry.remove();
            itemCount--;

            // Hide remove button for first item if only one item remains
            if (itemCount === 1) {
                document.querySelector('.remove-item').style.display = 'none';
            }
        }
    });

    // Modify form submission
    $('#movementForm').on('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_movement');
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Recording...');
        
        // Convert FormData to object and restructure items data
        const data = Object.fromEntries(formData);
        const items = [];
        
        // Collect all items data
        Object.keys(data).forEach(key => {
            if (key.startsWith('items[')) {
                const matches = key.match(/items\[(\d+)\]\[(\w+)\]/);
                if (matches) {
                    const [, index, field] = matches;
                    if (!items[index]) items[index] = {};
                    items[index][field] = data[key];
                }
            }
        });
        
        // Clean up items array
        const cleanItems = items.filter(item => item && item.id && item.quantity);
        
        // Prepare final data
        const submitData = {
            action: 'add_movement',
            items: cleanItems,
            movement_type: data.movement_type,
            delivery_type: data.delivery_type,
            delivery_address: data.delivery_address || ''
        };
        
        $.ajax({
            url: 'includes/handle_movement.php',
            type: 'POST',
            data: submitData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred'
                    });
                }
                submitBtn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Record Movement');
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing your request'
                });
                submitBtn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Record Movement');
            }
        });
    });

    // Format quantity input
    $('input[name="quantity"]').on('input', function() {
        const value = parseInt($(this).val());
        if (isNaN(value) || value < 1) {
            $(this).val(1);
        }
    });

    // Initialize variable at the top of the script
    let currentMovementId = null;

    // Handle review button click
    $('.review-movement').on('click', function() {
        const id = $(this).data('id');
        const item = $(this).data('item');
        const type = $(this).data('type');
        const delivery = $(this).data('delivery');
        const address = $(this).data('address');
        const quantity = $(this).data('quantity');
        
        console.log('Review button clicked for movement ID:', id);
        currentMovementId = id;
        
        // Update modal content
        let detailsHtml = `
            <p><strong>Item:</strong> ${item}</p>
            <p><strong>Movement Type:</strong> ${type}</p>
            <p><strong>Delivery Type:</strong> ${delivery}</p>`;
            
        // Add delivery address if it exists and is for site delivery
        if (delivery === 'Delivery To Site' && address) {
            detailsHtml += `<p><strong>Delivery Address:</strong> ${address}</p>`;
        }
        
        detailsHtml += `<p><strong>Quantity:</strong> ${quantity}</p>`;
        
        $('.movement-details').html(detailsHtml);
        
        // Show modal
        new bootstrap.Modal(document.getElementById('approvalModal')).show();
    });

    // Handle Reject button click
    $('#rejectBtn').on('click', function() {
        console.log('Reject button clicked');
        updateMovementStatus('rejected');
    });

    // Handle Confirm button click
    $('#confirmBtn').on('click', function() {
        console.log('Confirm button clicked');
        updateMovementStatus('confirmed');
    });

    // Function to update movement status
    function updateMovementStatus(status) {
        console.log('Updating movement status to:', status);
        console.log('Movement ID:', currentMovementId);
        
        if (!currentMovementId) {
            console.error('No movement ID set');
            return;
        }
        
        const modal = bootstrap.Modal.getInstance(document.getElementById('approvalModal'));
        
        // Disable buttons during request
        $('#rejectBtn, #confirmBtn').prop('disabled', true);
        
        $.ajax({
            url: 'includes/handle_movement.php',
            type: 'POST',
            data: {
                action: 'update_status',
                movement_id: currentMovementId,
                status: status
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Close the modal first
                    if (modal) {
                        modal.hide();
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: response.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message || 'An error occurred'
                    });
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred while processing your request'
                });
            },
            complete: function() {
                // Re-enable buttons after request
                $('#rejectBtn, #confirmBtn').prop('disabled', false);
            }
        });
    }
});
</script>

<?php require_once "includes/footer.php"; ?> 