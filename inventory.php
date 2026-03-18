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
$can_manage_inventory = in_array($current_position, ['administrator', 'manager', 'supervisor']);

// Get warehouses for dropdown
$warehouses = mysqli_query($conn, "SELECT id, name FROM warehouses ORDER BY name");

// Get suppliers for dropdown
$suppliers = mysqli_query($conn, "SELECT id, name FROM suppliers ORDER BY name");

// Get inventory items with warehouse and supplier names
$sql = "SELECT i.*, w.name as warehouse_name, s.name as supplier_name 
        FROM inventory_items i 
        LEFT JOIN warehouses w ON i.warehouse_id = w.id 
        LEFT JOIN suppliers s ON i.supplier_id = s.id 
        WHERE i.is_deleted = 0
        ORDER BY i.name";
$active_items = mysqli_query($conn, $sql);

// Get deleted items
$sql = "SELECT i.*, w.name as warehouse_name, s.name as supplier_name 
        FROM inventory_items i 
        LEFT JOIN warehouses w ON i.warehouse_id = w.id 
        LEFT JOIN suppliers s ON i.supplier_id = s.id 
        WHERE i.is_deleted = 1
        ORDER BY i.deleted_at DESC";
$deleted_items = mysqli_query($conn, $sql);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Inventory Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Inventory</li>
                        </ol>
                    </nav>
                </div>
                <?php if($can_manage_inventory): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal">
                    <i class='bx bx-plus me-1'></i> Add New Item
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <!-- Tabs -->
            <ul class="nav nav-tabs mb-3" id="inventoryTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="active-tab" data-bs-toggle="tab" data-bs-target="#active" type="button" role="tab">
                        Active Items
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="deleted-tab" data-bs-toggle="tab" data-bs-target="#deleted" type="button" role="tab">
                        Deleted Items
                    </button>
                </li>
            </ul>

            <!-- Tab Content -->
            <div class="tab-content" id="inventoryTabContent">
                <!-- Active Items Tab -->
                <div class="tab-pane fade show active" id="active" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="activeItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product Code</th>
                                            <th>Name</th>
                                            <th>Detail</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Warehouse</th>
                                            <th>Supplier</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($item = mysqli_fetch_assoc($active_items)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><?php echo number_format($item['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                                <td><?php echo htmlspecialchars($item['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                                <td>
                                                    <?php if($item['quantity'] <= $item['minimum_stock']): ?>
                                                        <span class="badge bg-danger">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($can_manage_inventory): ?>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-outline-primary edit-item" 
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#itemModal"
                                                                data-id="<?php echo $item['id']; ?>"
                                                                data-sku="<?php echo htmlspecialchars($item['sku']); ?>"
                                                                data-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                                data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                                                data-category="<?php echo htmlspecialchars($item['category']); ?>"
                                                                data-unit="<?php echo htmlspecialchars($item['unit']); ?>"
                                                                data-quantity="<?php echo $item['quantity']; ?>"
                                                                data-minimum-stock="<?php echo $item['minimum_stock']; ?>"
                                                                data-warehouse-id="<?php echo $item['warehouse_id']; ?>"
                                                                data-supplier-id="<?php echo $item['supplier_id']; ?>">
                                                            <i class='bx bx-edit'></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger delete-item" 
                                                                data-id="<?php echo $item['id']; ?>">
                                                            <i class='bx bx-trash'></i>
                                                        </button>
                                                    </div>
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

                <!-- Deleted Items Tab -->
                <div class="tab-pane fade" id="deleted" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover" id="deletedItemsTable">
                                    <thead>
                                        <tr>
                                            <th>Product Code</th>
                                            <th>Name</th>
                                            <th>Detail</th>
                                            <th>Quantity</th>
                                            <th>Unit</th>
                                            <th>Warehouse</th>
                                            <th>Supplier</th>
                                            <th>Deleted At</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($item = mysqli_fetch_assoc($deleted_items)): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['sku']); ?></td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><?php echo number_format($item['quantity']); ?></td>
                                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                                <td><?php echo htmlspecialchars($item['warehouse_name']); ?></td>
                                                <td><?php echo htmlspecialchars($item['supplier_name']); ?></td>
                                                <td><?php echo date('Y-m-d H:i', strtotime($item['deleted_at'])); ?></td>
                                                <td>
                                                    <?php if(in_array($current_position, ['administrator', 'manager'])): ?>
                                                    <button type="button" class="btn btn-sm btn-success restore-item" 
                                                            data-id="<?php echo $item['id']; ?>">
                                                        <i class='bx bx-refresh'></i> Restore
                                                    </button>
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
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Item Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="itemForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Product Code</label>
                        <input type="text" class="form-control" name="sku" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Item Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Detail</label>
                            <input type="text" class="form-control" name="category" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" required min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Stock</label>
                            <input type="number" class="form-control" name="minimum_stock" required min="0">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warehouse</label>
                            <select class="form-select" name="warehouse_id" required>
                                <option value="">Select Warehouse</option>
                                <?php
                                mysqli_data_seek($warehouses, 0);
                                while($warehouse = mysqli_fetch_assoc($warehouses)) {
                                    echo "<option value='" . $warehouse['id'] . "'>" . htmlspecialchars($warehouse['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier</label>
                            <select class="form-select" name="supplier_id" required>
                                <option value="">Select Supplier</option>
                                <?php
                                mysqli_data_seek($suppliers, 0);
                                while($supplier = mysqli_fetch_assoc($suppliers)) {
                                    echo "<option value='" . $supplier['id'] . "'>" . htmlspecialchars($supplier['name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
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
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class='bx bx-error-circle me-2'></i>
                    Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center py-4">
                <i class='bx bx-error-circle text-danger' style="font-size: 6rem;"></i>
                <h4 class="mt-3">Are you sure?</h4>
                <p class="text-muted mb-0">You are about to delete this item. This action cannot be undone.</p>
            </div>
            <div class="modal-footer justify-content-center border-top-0">
                <form method="post">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteItemId">
                    <button type="button" class="btn btn-light px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger px-4">
                        <i class='bx bx-trash me-1'></i>
                        Delete Item
                    </button>
                </form>
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

.badge {
    padding: 0.5em 0.75em;
    font-weight: 500;
}

.text-monospace {
    font-family: monospace;
    font-size: 0.875rem;
}

.modal-content {
    border: none;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}

.modal-header {
    padding: 1.25rem 1.5rem;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.25rem 1.5rem;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

@media (min-width: 992px) {
    .content-wrapper {
        padding: 30px;
    }
    
    .modal-dialog {
        max-width: 600px;
    }
}

.nav-tabs .nav-link {
    color: #495057;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 0.5rem 1rem;
}

.nav-tabs .nav-link.active {
    color: var(--primary-color);
    border-bottom-color: var(--primary-color);
    background: none;
}

.btn-group {
    display: flex;
    gap: 0.25rem;
}

.restore-item {
    white-space: nowrap;
}

.restore-item i {
    margin-right: 0.25rem;
}
</style>

<!-- Add jQuery first -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Then Bootstrap -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Then DataTables -->
<script src="https://cdn.datatables.net/1.10.24/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.24/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#activeItemsTable, #deletedItemsTable').DataTable({
        order: [[1, 'asc']],
        pageLength: 25
    });

    <?php if($can_manage_inventory): ?>
    // Handle edit button clicks
    $('.edit-item').on('click', function() {
        const button = $(this);
        const modal = $('#itemModal');
        
        // Set form to edit mode
        modal.find('.modal-title').text('Edit Item');
        modal.find('input[name="action"]').val('edit');
        
        // Set all form values from data attributes
        modal.find('input[name="id"]').val(button.data('id'));
        modal.find('input[name="sku"]').val(button.data('sku'));
        modal.find('input[name="name"]').val(button.data('name'));
        modal.find('textarea[name="description"]').val(button.data('description'));
        modal.find('input[name="category"]').val(button.data('category'));
        modal.find('input[name="unit"]').val(button.data('unit'));
        modal.find('input[name="quantity"]').val(button.data('quantity'));
        modal.find('input[name="minimum_stock"]').val(button.data('minimum-stock'));
        modal.find('select[name="warehouse_id"]').val(button.data('warehouse-id'));
        modal.find('select[name="supplier_id"]').val(button.data('supplier-id'));
    });
    
    // Handle form submission
    $('#itemForm').on('submit', function(e) {
        e.preventDefault();
        
        // Disable submit button to prevent double submission
        const submitButton = $(this).find('button[type="submit"]');
        submitButton.prop('disabled', true);
        
        // Show loading state
        submitButton.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...');
        
        $.ajax({
            url: 'includes/handle_inventory.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    // Show success message
                    alert(response.message);
                    // Close modal and refresh page
                    $('#itemModal').modal('hide');
                    location.reload();
                } else {
                    // Show error message
                    alert(response.message || 'An error occurred');
                    // Re-enable submit button
                    submitButton.prop('disabled', false).html('Save Changes');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                // Show detailed error message
                let errorMessage = 'An error occurred while processing your request.\n\n';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if(response.message) {
                        errorMessage += response.message;
                    }
                } catch(e) {
                    errorMessage += 'Please try again or contact support if the problem persists.';
                }
                alert(errorMessage);
                
                // Re-enable submit button
                submitButton.prop('disabled', false).html('Save Changes');
            }
        });
    });
    
    // Reset form when modal is hidden
    $('#itemModal').on('hidden.bs.modal', function() {
        const form = $(this).find('form');
        form[0].reset();
        form.find('input[name="action"]').val('add');
        form.find('input[name="id"]').val('');
        $(this).find('.modal-title').text('Add New Item');
        $(this).find('button[type="submit"]').prop('disabled', false).html('Save Changes');
    });

    // Handle delete button clicks
    $('.delete-item').on('click', function() {
        if(confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            const itemId = $(this).data('id');
            const button = $(this);
            
            // Disable button to prevent double click
            button.prop('disabled', true);
            
            $.ajax({
                url: 'includes/handle_inventory.php',
                type: 'POST',
                data: {
                    action: 'delete',
                    id: itemId
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'An error occurred');
                        button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    alert('An error occurred. Please try again.');
                    button.prop('disabled', false);
                }
            });
        }
    });

    // Handle restore button clicks
    $('.restore-item').on('click', function() {
        if(confirm('Are you sure you want to restore this item?')) {
            const itemId = $(this).data('id');
            const button = $(this);
            
            // Disable button to prevent double click
            button.prop('disabled', true);
            
            $.ajax({
                url: 'includes/handle_inventory.php',
                type: 'POST',
                data: {
                    action: 'restore',
                    id: itemId
                },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert(response.message || 'An error occurred');
                        button.prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    alert('An error occurred. Please try again.');
                    button.prop('disabled', false);
                }
            });
        }
    });
    <?php endif; ?>
});
</script>

<?php require_once "includes/footer.php"; ?> 