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
$can_manage_warehouse = in_array($current_position, ['administrator', 'manager']);

// Handle warehouse actions
if(isset($_POST['action'])) {
    if(!$can_manage_warehouse) {
        $error = "Only administrators and managers can perform warehouse management actions.";
    } else {
        if($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
            $warehouse_id = (int)$_POST['warehouse_id'];
            $name = trim($_POST['name']);
            $location = trim($_POST['location']);
            $capacity = (int)$_POST['capacity'];
            
            if($_POST['action'] == 'add') {
                $sql = "INSERT INTO warehouses (name, location, capacity) VALUES (?, ?, ?)";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssi", $name, $location, $capacity);
            } else {
                $sql = "UPDATE warehouses SET name = ?, location = ?, capacity = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "ssii", $name, $location, $capacity, $warehouse_id);
            }
            
            if(mysqli_stmt_execute($stmt)) {
                $success = "Warehouse " . ($_POST['action'] == 'add' ? "added" : "updated") . " successfully!";
            } else {
                $error = "Error: " . mysqli_error($conn);
            }
        } elseif($_POST['action'] == 'delete') {
            $warehouse_id = (int)$_POST['warehouse_id'];
            
            // Check if warehouse has items
            $check_sql = "SELECT COUNT(*) as count FROM inventory_items WHERE warehouse_id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "i", $warehouse_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $item_count = mysqli_fetch_assoc($result)['count'];
            
            if($item_count > 0) {
                $error = "Cannot delete warehouse: There are items stored in this warehouse.";
            } else {
                $sql = "DELETE FROM warehouses WHERE id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $warehouse_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $success = "Warehouse deleted successfully!";
                } else {
                    $error = "Error: " . mysqli_error($conn);
                }
            }
        }
    }
}

// Get warehouses with item counts
$sql = "SELECT w.*, 
        COUNT(DISTINCT CASE WHEN i.is_deleted = 0 THEN i.id ELSE NULL END) as item_count,
        COALESCE(SUM(CASE WHEN i.is_deleted = 0 THEN i.quantity ELSE 0 END), 0) as total_items
        FROM warehouses w 
        LEFT JOIN inventory_items i ON w.id = i.warehouse_id 
        GROUP BY w.id 
        ORDER BY w.name";
$warehouses = mysqli_query($conn, $sql);
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="page-title">Warehouse Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Warehouses</li>
                        </ol>
                    </nav>
                </div>
                <?php if($can_manage_warehouse): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#warehouseModal">
                    <i class='bx bx-plus me-1'></i> Add New Warehouse
                </button>
                <?php endif; ?>
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
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="warehousesTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Location</th>
                                    <th>Capacity</th>
                                    <th>Items Stored</th>
                                    <th>Storage Usage</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while($warehouse = mysqli_fetch_assoc($warehouses)): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-medium"><?php echo htmlspecialchars($warehouse['name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($warehouse['location']); ?></td>
                                        <td><?php echo number_format($warehouse['capacity']); ?></td>
                                        <td><?php echo number_format($warehouse['item_count']); ?> items</td>
                                        <td>
                                            <?php 
                                            $usage = $warehouse['capacity'] > 0 ? 
                                                    ($warehouse['total_items'] / $warehouse['capacity']) * 100 : 0;
                                            $usage = min(100, round($usage, 1)); // Cap at 100% and round to 1 decimal
                                            $usage_class = $usage > 90 ? 'danger' : ($usage > 70 ? 'warning' : 'success');
                                            ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-<?php echo $usage_class; ?>" 
                                                     role="progressbar" 
                                                     style="width: <?php echo $usage; ?>%"
                                                     aria-valuenow="<?php echo $usage; ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100"></div>
                                            </div>
                                            <small class="text-muted"><?php echo number_format($usage, 1); ?>% used</small>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button type="button" class="btn btn-sm btn-outline-info view-details" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#warehouseDetailsModal"
                                                        data-id="<?php echo $warehouse['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($warehouse['name']); ?>">
                                                    <i class='bx bx-info-circle'></i>
                                                </button>
                                                <?php if($can_manage_warehouse): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary edit-warehouse" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#warehouseModal"
                                                        data-id="<?php echo $warehouse['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($warehouse['name']); ?>"
                                                        data-location="<?php echo htmlspecialchars($warehouse['location']); ?>"
                                                        data-capacity="<?php echo $warehouse['capacity']; ?>">
                                                    <i class='bx bx-edit'></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger delete-warehouse" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#deleteWarehouseModal"
                                                        data-id="<?php echo $warehouse['id']; ?>">
                                                    <i class='bx bx-trash'></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
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

<!-- Add/Edit Warehouse Modal -->
<div class="modal fade" id="warehouseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="id" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Warehouse Name</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Storage Capacity</label>
                        <input type="number" class="form-control" name="capacity" required min="0">
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

<!-- Delete Warehouse Modal -->
<div class="modal fade" id="deleteWarehouseModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Delete Warehouse</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="deleteWarehouseForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteWarehouseId">
                    <p>Are you sure you want to delete this warehouse?</p>
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

<!-- Warehouse Details Modal -->
<div class="modal fade" id="warehouseDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="warehouse-info mb-4">
                    <h4 class="warehouse-name text-center mb-4"></h4>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-icon">
                                    <i class='bx bx-package'></i>
                                </div>
                                <span class="detail-label">Total Items</span>
                                <span class="detail-value total-items">0</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-icon">
                                    <i class='bx bx-bar-chart-alt-2'></i>
                                </div>
                                <span class="detail-label">Storage Used</span>
                                <span class="detail-value storage-used">0%</span>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="detail-card">
                                <div class="detail-icon">
                                    <i class='bx bx-box'></i>
                                </div>
                                <span class="detail-label">Available Space</span>
                                <span class="detail-value available-space">0</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="items-section">
                    <h5 class="section-title">Warehouse Items</h5>
                    <div class="table-responsive">
                        <table class="table table-sm" id="warehouseItemsTable">
                            <thead>
                                <tr>
                                    <th>Product Code</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Quantity</th>
                                    <th>Last Updated</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Items will be loaded dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.action-buttons .btn {
    padding: 0.25rem 0.5rem;
}

.action-buttons .btn i {
    font-size: 1.1rem;
}

.progress {
    margin-bottom: 0.25rem;
}

.table td {
    vertical-align: middle;
}

/* Make the warehouse details modal wider */
#warehouseDetailsModal .modal-dialog {
    max-width: 90%;
    width: 1200px;
}

.detail-card {
    background: #fff;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    height: 100%;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    border: 1px solid rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.detail-icon {
    font-size: 2rem;
    color: var(--main-color);
    margin-bottom: 1rem;
}

.detail-label {
    display: block;
    color: #6c757d;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    display: block;
    font-size: 1.75rem;
    font-weight: 600;
    color: #2d3436;
}

.warehouse-name {
    font-size: 1.75rem;
    font-weight: 600;
    color: #2d3436;
    position: relative;
    padding-bottom: 1rem;
}

.warehouse-name:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    width: 60px;
    height: 3px;
    background: var(--main-color);
    border-radius: 2px;
}

.section-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: #2d3436;
    margin: 2rem 0 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #eee;
}

#warehouseItemsTable {
    margin-bottom: 0;
}

#warehouseItemsTable th {
    background: #f8f9fa;
    font-weight: 500;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    padding: 0.75rem;
    border-top: none;
}

#warehouseItemsTable td {
    padding: 0.75rem;
    vertical-align: middle;
    font-size: 0.875rem;
}

#warehouseItemsTable tbody tr:hover {
    background-color: rgba(0, 0, 0, 0.02);
}

.table-responsive {
    border-radius: 8px;
    border: 1px solid #eee;
}

/* Custom scrollbar for table */
.table-responsive::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.table-responsive::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb {
    background: #ccc;
    border-radius: 3px;
}

.table-responsive::-webkit-scrollbar-thumb:hover {
    background: #999;
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
    $('#warehousesTable').DataTable({
        order: [[0, 'asc']],
        pageLength: 10,
        lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
        language: {
            search: "",
            searchPlaceholder: "Search warehouses..."
        }
    });

    // Function to load warehouse details
    function loadWarehouseDetails(warehouseId) {
        // Show loading state
        $('.warehouse-name').text('Loading...');
        $('.total-items, .storage-used, .available-space').text('...');
        $('#warehouseItemsTable tbody').html('<tr><td colspan="5" class="text-center">Loading...</td></tr>');
        
        $.ajax({
            url: 'includes/get_warehouse_details.php',
            type: 'POST',
            data: { warehouse_id: warehouseId },
            dataType: 'json',
            success: function(response) {
                console.log('Warehouse details response:', response); // Debug log
                
                if (response.success) {
                    // Update warehouse name and details
                    $('.warehouse-name').text(response.warehouse.name);
                    
                    // Update statistics with proper formatting
                    $('.total-items').text(response.total_items || '0');
                    $('.storage-used').text((response.storage_used || '0') + '%');
                    $('.available-space').text(response.available_space || '0');
                    
                    // Clear and populate items table
                    var itemsTable = $('#warehouseItemsTable tbody');
                    itemsTable.empty();
                    
                    if (response.items && response.items.length > 0) {
                        response.items.forEach(function(item) {
                            var row = `<tr>
                                <td>${item.product_code || 'N/A'}</td>
                                <td>${item.product_name || 'N/A'}</td>
                                <td>${item.category || 'N/A'}</td>
                                <td>${item.quantity || '0'}</td>
                                <td>${item.last_updated || 'N/A'}</td>
                            </tr>`;
                            itemsTable.append(row);
                        });
                    } else {
                        itemsTable.html('<tr><td colspan="5" class="text-center">No items found in this warehouse</td></tr>');
                    }
                } else {
                    // Show error in the modal
                    $('.warehouse-name').text('Error');
                    $('.total-items, .storage-used, .available-space').text('0');
                    $('#warehouseItemsTable tbody').html(
                        '<tr><td colspan="5" class="text-center text-danger">' + 
                        (response.message || 'Error loading warehouse details') + 
                        '</td></tr>'
                    );
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                
                // Show error in the modal
                $('.warehouse-name').text('Error');
                $('.total-items, .storage-used, .available-space').text('0');
                $('#warehouseItemsTable tbody').html(
                    '<tr><td colspan="5" class="text-center text-danger">' +
                    'Error loading warehouse details. Please try again.' +
                    '</td></tr>'
                );
            }
        });
    }

    // Attach click handler to view buttons
    $('.view-details').on('click', function() {
        var warehouseId = $(this).data('id');
        loadWarehouseDetails(warehouseId);
    });

    <?php if($can_manage_warehouse): ?>
    // Handle edit warehouse modal
    $('#warehouseModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const modal = $(this);
        
        // Reset form
        modal.find('form')[0].reset();
        
        if (button.hasClass('edit-warehouse')) {
            // Edit mode
            modal.find('.modal-title').text('Edit Warehouse');
            modal.find('input[name="action"]').val('edit');
            modal.find('input[name="id"]').val(button.data('id'));
            modal.find('input[name="name"]').val(button.data('name'));
            modal.find('input[name="location"]').val(button.data('location'));
            modal.find('input[name="capacity"]').val(button.data('capacity'));

            // Debug log
            console.log('Edit warehouse clicked. Data:', {
                id: button.data('id'),
                name: button.data('name'),
                location: button.data('location'),
                capacity: button.data('capacity')
            });
        } else {
            // Add new mode
            modal.find('.modal-title').text('Add New Warehouse');
            modal.find('input[name="action"]').val('add');
            modal.find('input[name="id"]').val('');
        }
    });

    // Handle form submission
    $('form[method="post"]').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serializeArray();
        console.log('Submitting form data:', formData);
        
        $.ajax({
            url: 'includes/handle_warehouse.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                console.log('Server response:', response);
                if(response.success) {
                    $('#warehouseModal').modal('hide');
                    alert(response.message);
                    location.reload();
                } else {
                    alert(response.message || 'An error occurred');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('An error occurred. Please check the console for details.');
            }
        });
    });

    // Handle delete warehouse
    $('.delete-warehouse').on('click', function() {
        const warehouseId = $(this).data('id');
        console.log('Delete warehouse clicked for ID:', warehouseId);
        $('#deleteWarehouseId').val(warehouseId);
    });

    // Handle delete warehouse form submission
    $('#deleteWarehouseForm').on('submit', function(e) {
        e.preventDefault();
        const warehouseId = $('#deleteWarehouseId').val();
        const submitBtn = $(this).find('button[type="submit"]');
        const modal = $('#deleteWarehouseModal');
        
        // Disable submit button
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'includes/handle_warehouse.php',
            type: 'POST',
            data: {
                action: 'delete',
                id: warehouseId
            },
            dataType: 'json',
            success: function(response) {
                console.log('Server response for delete:', response);
                if(response.success) {
                    modal.modal('hide');
                    alert(response.message);
                    location.reload();
                } else {
                    alert(response.message || 'An error occurred while deleting the warehouse.');
                    submitBtn.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('Delete AJAX Error:', error);
                console.error('Status:', status);
                console.error('Response:', xhr.responseText);
                alert('An error occurred while deleting the warehouse. Please check the console for details.');
                submitBtn.prop('disabled', false);
            }
        });
    });
    <?php endif; ?>
});
</script>

<?php require_once "includes/footer.php"; ?> 