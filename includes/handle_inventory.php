<?php
session_start();
require_once "../config.php";

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action']);
    exit;
}

// Check user's position/role
$current_user_sql = "SELECT position FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $current_user_sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$current_user = mysqli_fetch_assoc($result);
$current_position = strtolower($current_user['position'] ?? '');

// Define allowed positions for inventory management
$allowed_positions = ['administrator', 'manager', 'supervisor'];

// Check if user has permission
if (!in_array($current_position, $allowed_positions)) {
    echo json_encode(['success' => false, 'message' => 'You do not have permission to perform this action']);
    exit;
}

// Handle the request
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch($action) {
        case 'add':
            // Validate required fields
            $required_fields = ['sku', 'name', 'category', 'unit', 'quantity', 'minimum_stock', 'warehouse_id', 'supplier_id'];
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    exit;
                }
            }
            
            // Check for duplicate SKU
            $check_sql = "SELECT id FROM inventory_items WHERE sku = ? AND is_deleted = 0";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "s", $_POST['sku']);
            mysqli_stmt_execute($check_stmt);
            if(mysqli_stmt_get_result($check_stmt)->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'An item with this SKU already exists']);
                exit;
            }
            
            // Insert new item
            $sql = "INSERT INTO inventory_items (sku, name, description, category, unit, quantity, minimum_stock, warehouse_id, supplier_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);
            
            // Set description to empty string if not provided
            $description = isset($_POST['description']) ? trim($_POST['description']) : '';
            $quantity = (int)$_POST['quantity'];
            $minimum_stock = (int)$_POST['minimum_stock'];
            $warehouse_id = (int)$_POST['warehouse_id'];
            $supplier_id = (int)$_POST['supplier_id'];
            
            mysqli_stmt_bind_param($stmt, "sssssiiii", 
                $_POST['sku'],
                $_POST['name'],
                $description,
                $_POST['category'],
                $_POST['unit'],
                $quantity,
                $minimum_stock,
                $warehouse_id,
                $supplier_id
            );
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Item added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error adding item: ' . mysqli_error($conn)]);
            }
            break;
            
        case 'edit':
            // Validate required fields
            $required_fields = ['id', 'sku', 'name', 'category', 'unit', 'quantity', 'minimum_stock', 'warehouse_id', 'supplier_id'];
            foreach ($required_fields as $field) {
                if (!isset($_POST[$field]) || trim($_POST[$field]) === '') {
                    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
                    exit;
                }
            }
            
            // Check for duplicate SKU, excluding the current item
            $check_sql = "SELECT id FROM inventory_items WHERE sku = ? AND id != ? AND is_deleted = 0";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "si", $_POST['sku'], $_POST['id']);
            mysqli_stmt_execute($check_stmt);
            if(mysqli_stmt_get_result($check_stmt)->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'An item with this SKU already exists']);
                exit;
            }
            
            // Update item
            $sql = "UPDATE inventory_items SET 
                    sku = ?, 
                    name = ?, 
                    description = ?, 
                    category = ?, 
                    unit = ?, 
                    quantity = ?, 
                    minimum_stock = ?, 
                    warehouse_id = ?, 
                    supplier_id = ? 
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "sssssiiiii", 
                $_POST['sku'],
                $_POST['name'],
                $_POST['description'],
                $_POST['category'],
                $_POST['unit'],
                $_POST['quantity'],
                $_POST['minimum_stock'],
                $_POST['warehouse_id'],
                $_POST['supplier_id'],
                $_POST['id']
            );
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Item updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error updating item: ' . mysqli_error($conn)]);
            }
            break;
            
        case 'delete':
            if (!isset($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'Item ID is required']);
                exit;
            }
            
            // Soft delete - update is_deleted flag and set deleted_at timestamp
            $sql = "UPDATE inventory_items SET is_deleted = 1, deleted_at = NOW() WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_POST['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . mysqli_error($conn)]);
            }
            break;
            
        case 'restore':
            if (!isset($_POST['id'])) {
                echo json_encode(['success' => false, 'message' => 'Item ID is required']);
                exit;
            }
            
            // Check if user is administrator or manager
            if (!in_array($current_position, ['administrator', 'manager'])) {
                echo json_encode(['success' => false, 'message' => 'Only administrators and managers can restore items']);
                exit;
            }
            
            // Restore item - clear is_deleted flag and deleted_at timestamp
            $sql = "UPDATE inventory_items SET is_deleted = 0, deleted_at = NULL WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $_POST['id']);
            
            if (mysqli_stmt_execute($stmt)) {
                echo json_encode(['success' => true, 'message' => 'Item restored successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error restoring item: ' . mysqli_error($conn)]);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No action specified']);
}
?> 