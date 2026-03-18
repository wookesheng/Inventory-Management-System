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
$can_manage_suppliers = in_array($current_position, ['administrator', 'manager', 'supervisor']);

// Add header for JSON responses
if(isset($_POST['action']) && $_POST['action'] == 'delete_supplier') {
    header('Content-Type: application/json');
}

// Handle supplier actions
if(isset($_POST['action'])) {
    // Check if user has permission to manage suppliers
    if(!$can_manage_suppliers) {
        if($_POST['action'] == 'delete_supplier') {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to manage suppliers.']);
            exit;
        } else {
            $error = "You do not have permission to manage suppliers.";
        }
    } else if($_POST['action'] == 'edit_supplier') {
        $supplier_id = (int)$_POST['supplier_id'];
        $name = trim($_POST['name']);
        $contact_person = trim($_POST['contact_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if($supplier_id > 0) {
            // Update existing supplier
            $sql = "UPDATE suppliers SET name = ?, contact_person = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssssi", $name, $contact_person, $email, $phone, $supplier_id);
            
            if(mysqli_stmt_execute($stmt)) {
                $success = "Supplier updated successfully!";
            } else {
                $error = "Error updating supplier: " . mysqli_error($conn);
            }
        } else {
            // Create new supplier
            $sql = "INSERT INTO suppliers (name, contact_person, email, phone) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ssss", $name, $contact_person, $email, $phone);
            
            if(mysqli_stmt_execute($stmt)) {
                $success = "Supplier created successfully!";
            } else {
                $error = "Error creating supplier: " . mysqli_error($conn);
            }
        }
    } elseif($_POST['action'] == 'delete_supplier') {
        $response = ['success' => false, 'message' => ''];
        $supplier_id = (int)$_POST['supplier_id'];
        
        // Check if supplier has any items
        $check_sql = "SELECT COUNT(*) as count FROM inventory_items WHERE supplier_id = ?";
        $check_stmt = mysqli_prepare($conn, $check_sql);
        mysqli_stmt_bind_param($check_stmt, "i", $supplier_id);
        mysqli_stmt_execute($check_stmt);
        $result = mysqli_stmt_get_result($check_stmt);
        $items_count = mysqli_fetch_assoc($result)['count'];
        
        if($items_count > 0) {
            $response['message'] = "Cannot delete supplier: There are items associated with this supplier.";
        } else {
            // Delete the supplier
            $sql = "DELETE FROM suppliers WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $supplier_id);
            
            if(mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = "Supplier deleted successfully!";
            } else {
                $response['message'] = "Error deleting supplier: " . mysqli_error($conn);
            }
        }
        
        echo json_encode($response);
        exit;
    }
}

// Get low stock items count
$low_stock_sql = "SELECT COUNT(*) as count 
                  FROM inventory_items 
                  WHERE quantity <= minimum_stock 
                  AND is_deleted = 0";
$low_stock_result = mysqli_query($conn, $low_stock_sql);
$low_stock_count = mysqli_fetch_assoc($low_stock_result)['count'];

// Get total items count
$total_items_sql = "SELECT COUNT(*) as count 
                    FROM inventory_items 
                    WHERE is_deleted = 0";
$total_items_result = mysqli_query($conn, $total_items_sql);
$total_items_count = mysqli_fetch_assoc($total_items_result)['count'];

// Get active warehouses count
$warehouses_sql = "SELECT COUNT(*) as count FROM warehouses";
$warehouses_result = mysqli_query($conn, $warehouses_sql);
$warehouses_count = mysqli_fetch_assoc($warehouses_result)['count'];

// Get recent movements
$sql = "SELECT m.*, i.name as item_name, i.sku, u.fullName as user_name 
        FROM inventory_movements m 
        JOIN inventory_items i ON m.item_id = i.id 
        JOIN users u ON m.user_id = u.id 
        WHERE m.status = 'confirmed'
        AND i.deleted_at IS NULL
        ORDER BY m.created_at DESC LIMIT 10";
$recent_movements = mysqli_query($conn, $sql);

// Get suppliers list
$sql = "SELECT * FROM suppliers ORDER BY name ASC";
$suppliers = mysqli_query($conn, $sql);
?>



<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="text-muted">Overview of inventory system</p>
                </div>
            </div>
        </div>
    </div>

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

    <div class="content">
        <div class="container-fluid">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-lg-4">
                    <div class="stats-card bg-primary text-white">
                        <div class="stats-icon">
                            <i class='bx bx-package'></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($total_items_count); ?></h3>
                            <p>Total Items</p>
                        </div>
                        <div class="stats-link">
                            <a href="inventory.php" class="text-white">View Details <i class='bx bx-right-arrow-alt'></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="stats-card bg-success text-white">
                        <div class="stats-icon">
                            <i class='bx bx-building'></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($warehouses_count); ?></h3>
                            <p>Active Warehouses</p>
                        </div>
                        <div class="stats-link">
                            <a href="warehouses.php" class="text-white">View Details <i class='bx bx-right-arrow-alt'></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="stats-card bg-danger text-white">
                        <div class="stats-icon">
                            <i class='bx bx-error-circle'></i>
                        </div>
                        <div class="stats-info">
                            <h3><?php echo number_format($low_stock_count); ?></h3>
                            <p>Low Stock Items</p>
                        </div>
                        <div class="stats-link">
                            <a href="inventory.php?filter=low_stock" class="text-white">View Details <i class='bx bx-right-arrow-alt'></i></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main content row -->
            <div class="row">
                <!-- Left column for Recent Movements -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Recent Inventory Movements</h5>
                                <a href="movements.php" class="btn btn-sm btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Item</th>
                                            <th>Type</th>
                                            <th>Quantity</th>
                                            <th>User</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($movement = mysqli_fetch_assoc($recent_movements)): ?>
                                            <tr>
                                                <td><?php echo date('M d, Y H:i', strtotime($movement['created_at'])); ?></td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="item-icon me-2">
                                                            <?php echo strtoupper(substr($movement['item_name'], 0, 2)); ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-medium"><?php echo htmlspecialchars($movement['item_name']); ?></div>
                                                            <small class="text-muted"><?php echo htmlspecialchars($movement['sku']); ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $movement['movement_type'] == 'IN' ? 'success' : 'danger'; ?>">
                                                        <?php echo $movement['movement_type']; ?>
                                                    </span>
                                                </td>
                                                <td class="fw-medium"><?php echo number_format($movement['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($movement['user_name']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right column for Supplier Overview -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0">Supplier Overview</h5>
                                <?php if($can_manage_suppliers): ?>
                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editSupplierModal">
                                    <i class='bx bx-plus'></i> Add New
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="supplier-list">
                                <?php if(mysqli_num_rows($suppliers) > 0): ?>
                                    <?php while($supplier = mysqli_fetch_assoc($suppliers)): ?>
                                        <div class="supplier-item" data-supplier-id="<?php echo $supplier['id']; ?>">
                                            <div class="supplier-info">
                                                <h6><?php echo htmlspecialchars($supplier['name']); ?></h6>
                                                <small class="text-muted">
                                                    Contact: <?php echo htmlspecialchars($supplier['contact_person'] ?: 'Not specified'); ?>
                                                </small>
                                            </div>
                                            <div class="supplier-actions">
                                                <button type="button" class="btn btn-sm btn-outline-info view-supplier" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#viewSupplierModal"
                                                        data-supplier-id="<?php echo $supplier['id']; ?>"
                                                        data-supplier-name="<?php echo htmlspecialchars($supplier['name']); ?>">
                                                    <i class='bx bx-show'></i>
                                                </button>
                                                <?php if($can_manage_suppliers): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-supplier" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editSupplierModal"
                                                        data-supplier-id="<?php echo $supplier['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($supplier['name']); ?>"
                                                        data-contact="<?php echo htmlspecialchars($supplier['contact_person']); ?>"
                                                        data-email="<?php echo htmlspecialchars($supplier['email']); ?>"
                                                        data-phone="<?php echo htmlspecialchars($supplier['phone']); ?>">
                                                    <i class='bx bx-edit'></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-supplier" 
                                                        data-supplier-id="<?php echo $supplier['id']; ?>"
                                                        data-supplier-name="<?php echo htmlspecialchars($supplier['name']); ?>">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="text-muted mb-0">No suppliers found</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit/Add Supplier Modal -->
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_supplier">
                    <input type="hidden" name="supplier_id" id="editSupplierId" value="0">
                    
                    <div class="mb-3">
                        <label class="form-label">Supplier Name</label>
                        <input type="text" class="form-control" name="name" id="editSupplierName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" class="form-control" name="contact_person" id="editSupplierContact">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="editSupplierEmail">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="tel" class="form-control" name="phone" id="editSupplierPhone">
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

<!-- Delete Supplier Modal -->
<div class="modal fade" id="deleteSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Supplier</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete_supplier">
                    <input type="hidden" name="supplier_id" id="deleteSupplierIdInput">
                    <p>Are you sure you want to delete this supplier?</p>
                    <p class="text-danger mb-0">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Supplier Modal -->
<div class="modal fade" id="viewSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Supplier Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Content will be dynamically loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Statistics Cards */
.stats-card {
    padding: 1.5rem;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.stats-icon {
    font-size: 1.5rem;
    margin-bottom: 1rem;
}

.stats-info h3 {
    font-size: 2rem;
    margin: 0;
    font-weight: 600;
}

.stats-info p {
    margin: 0;
    opacity: 0.9;
}

.stats-link {
    margin-top: 1rem;
}

.stats-link a {
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stats-link a:hover {
    opacity: 0.9;
}

.stats-link i {
    font-size: 1.25rem;
}

/* Item Icon */
.item-icon {
    width: 40px;
    height: 40px;
    background: var(--main-color);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
}

/* Supplier List */
.supplier-list {
    max-height: 600px;
    overflow-y: auto;
}

.supplier-item {
    padding: 1rem;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.supplier-item:last-child {
    border-bottom: none;
}

.supplier-info {
    flex: 1;
    padding-right: 1rem;
}

.supplier-info h6 {
    margin: 0 0 0.25rem;
    font-weight: 600;
}

.supplier-info small {
    color: #6c757d;
}

.supplier-actions {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.supplier-actions .btn {
    padding: 0.25rem 0.5rem;
}

.supplier-actions .btn i {
    font-size: 1.1rem;
}

.delete-supplier-form {
    margin: 0;
}

/* Category Cards */
.category-card {
    background: #fff;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    height: 100%;
}

.category-icon {
    width: 48px;
    height: 48px;
    background: var(--main-color);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.category-info h6 {
    margin: 0 0 0.5rem;
    font-weight: 600;
}

.category-stats {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Print Styles */
@media print {
    .sidebar, .header-actions, .card-header button {
        display: none !important;
    }
    
    .content-wrapper {
        margin: 0 !important;
        padding: 0 !important;
    }
    
    .card {
        break-inside: avoid;
    }
}

/* Responsive adjustments */
@media (max-width: 992px) {
    .supplier-list {
        max-height: 400px;
    }
    
    .category-card {
        margin-bottom: 1rem;
    }
}

/* Additional styles for supplier details */
.supplier-details label {
    color: #6c757d;
    font-size: 0.875rem;
    margin-bottom: 0;
}

.supplier-details .border-bottom:last-child {
    border-bottom: none !important;
}

.supplier-details .bg-light {
    background-color: #f8f9fa !important;
}

.supplier-details ul li {
    margin-bottom: 0.5rem;
}

.supplier-details ul li:last-child {
    margin-bottom: 0;
}
</style>

<script>
$(document).ready(function() {
    // Set a variable to check if user can manage suppliers
    const canManageSuppliers = <?php echo $can_manage_suppliers ? 'true' : 'false'; ?>;
    
    // Handle edit supplier form submission
    $('#editSupplierModal form').on('submit', function(e) {
        e.preventDefault();
        
        // Check if user has permission
        if (!canManageSuppliers) {
            alert('You do not have permission to manage suppliers.');
            return false;
        }
        
        $.ajax({
            url: 'dashboard.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                // Close the modal
                $('#editSupplierModal').modal('hide');
                
                // Reload the page to show updated data
                location.reload();
            },
            error: function() {
                alert('Error updating supplier. Please try again.');
            }
        });
    });

    // Handle edit supplier modal
    $('#editSupplierModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const modal = $(this);
        
        // Reset form
        modal.find('form')[0].reset();
        
        if (button.hasClass('edit-supplier')) {
            // Edit mode
            modal.find('.modal-title').text('Edit Supplier');
            modal.find('#editSupplierId').val(button.data('supplier-id'));
            modal.find('#editSupplierName').val(button.data('name'));
            modal.find('#editSupplierContact').val(button.data('contact'));
            modal.find('#editSupplierEmail').val(button.data('email'));
            modal.find('#editSupplierPhone').val(button.data('phone'));
        } else {
            // Add new mode
            modal.find('.modal-title').text('Add New Supplier');
            modal.find('#editSupplierId').val('0');
        }
    });

    // View supplier details
    $(document).on('click', '.view-supplier', function() {
        const supplierId = $(this).data('supplier-id');
        const supplierName = $(this).data('supplier-name');
        
        // Debug log
        console.log('View supplier clicked:', { id: supplierId, name: supplierName });
        
        // Show loading state
        $('#viewSupplierModal .modal-body').html(`
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 mb-0">Loading supplier details...</p>
            </div>
        `);
        
        // Show the modal
        $('#viewSupplierModal').modal('show');
        
        // Fetch supplier details
        $.ajax({
            url: 'includes/handle_supplier.php',
            type: 'POST',
            data: {
                action: 'get_details',
                supplier_id: supplierId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Server response:', response);
                
                if(response.success && response.data) {
                    const supplier = response.data;
                    let itemsList = '';
                    
                    if(supplier.items && supplier.items.length > 0) {
                        itemsList = '<ul class="list-unstyled mb-0">';
                        supplier.items.forEach(item => {
                            itemsList += `
                                <li class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <span>${item.name || '-'}</span>
                                    <small class="text-muted">${item.sku || '-'}</small>
                                </li>
                            `;
                        });
                        itemsList += '</ul>';
                    } else {
                        itemsList = '<p class="text-muted mb-0">No items found</p>';
                    }
                    
                    const modalContent = `
                        <div class="supplier-details">
                            <div class="mb-3">
                                <label class="fw-bold d-block">Supplier Name:</label>
                                <div class="mt-1">${supplier.name || '-'}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold d-block">Contact Person:</label>
                                <div class="mt-1">${supplier.contact_person || '-'}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold d-block">Email:</label>
                                <div class="mt-1">${supplier.email || '-'}</div>
                            </div>
                            <div class="mb-3">
                                <label class="fw-bold d-block">Phone:</label>
                                <div class="mt-1">${supplier.phone || '-'}</div>
                            </div>
                            <div class="mb-0">
                                <label class="fw-bold d-block mb-2">Items Supplied:</label>
                                <div class="border rounded p-3 bg-light">
                                    ${itemsList}
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#viewSupplierModal .modal-body').html(modalContent);
                } else {
                    $('#viewSupplierModal .modal-body').html(`
                        <div class="alert alert-danger mb-0">
                            ${response.message || 'Error loading supplier details'}
                        </div>
                    `);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                $('#viewSupplierModal .modal-body').html(`
                    <div class="alert alert-danger mb-0">
                        Error loading supplier details. Please try again.
                    </div>
                `);
            }
        });
    });

    // Delete supplier
    $(document).on('click', '.delete-supplier', function() {
        // Check if user has permission
        if (!canManageSuppliers) {
            alert('You do not have permission to manage suppliers.');
            return false;
        }
        
        const supplierId = $(this).data('supplier-id');
        const supplierName = $(this).data('supplier-name');
        
        if(confirm(`Are you sure you want to delete supplier "${supplierName}"?`)) {
            $.ajax({
                url: 'includes/handle_supplier.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    supplier_id: supplierId
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        // Remove the supplier item with animation
                        $(`.supplier-item[data-supplier-id="${supplierId}"]`).fadeOut(400, function() {
                            $(this).remove();
                            // Show success message
                            const alert = $(`<div class="alert alert-success alert-dismissible fade show" role="alert">
                                ${response.message}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>`);
                            $('.content-header').after(alert);
                            // Auto dismiss after 3 seconds
                            setTimeout(() => alert.alert('close'), 3000);
                            
                            // If no suppliers left, show message
                            if($('.supplier-item').length === 0) {
                                $('.supplier-list').html(`
                                    <div class="text-center py-4">
                                        <p class="text-muted mb-0">No suppliers found</p>
                                    </div>
                                `);
                            }
                        });
                    } else {
                        alert(response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Delete AJAX Error:', error);
                    alert('Error deleting supplier. Please try again.');
                }
            });
        }
    });

    // Auto-format phone number
    $('input[name="phone"]').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length > 0) {
            if (value.length <= 4) {
                value = value;
            } else if (value.length <= 8) {
                value = value.slice(0,4) + '-' + value.slice(4);
            } else {
                value = value.slice(0,4) + '-' + value.slice(4,8) + '-' + value.slice(8,12);
            }
            $(this).val(value);
        }
    });

    // Show success message and auto-hide after 3 seconds
    $('.alert-success').delay(3000).fadeOut(500);
});
</script>

<?php require_once "includes/footer.php"; ?>