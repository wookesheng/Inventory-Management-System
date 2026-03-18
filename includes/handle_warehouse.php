<?php
require_once "../config.php";

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize response array
$response = ['success' => false, 'message' => '', 'debug' => []];

// Log the incoming request
$response['debug']['post_data'] = $_POST;

if(isset($_POST['action'])) {
    if($_POST['action'] == 'add') {
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $capacity = (int)$_POST['capacity'];
        
        $sql = "INSERT INTO warehouses (name, location, capacity) VALUES (?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        
        if($stmt === false) {
            $response['message'] = 'Error preparing statement: ' . mysqli_error($conn);
            $response['debug']['sql_error'] = mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "ssi", $name, $location, $capacity);
            
            if(mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = 'Warehouse added successfully!';
            } else {
                $response['message'] = 'Error adding warehouse: ' . mysqli_stmt_error($stmt);
                $response['debug']['sql_error'] = mysqli_stmt_error($stmt);
            }
        }
    } elseif($_POST['action'] == 'edit') {
        $warehouse_id = (int)$_POST['id'];
        $name = trim($_POST['name']);
        $location = trim($_POST['location']);
        $capacity = (int)$_POST['capacity'];
        
        $sql = "UPDATE warehouses SET name = ?, location = ?, capacity = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        
        if($stmt === false) {
            $response['message'] = 'Error preparing statement: ' . mysqli_error($conn);
            $response['debug']['sql_error'] = mysqli_error($conn);
        } else {
            mysqli_stmt_bind_param($stmt, "ssii", $name, $location, $capacity, $warehouse_id);
            
            if(mysqli_stmt_execute($stmt)) {
                if(mysqli_stmt_affected_rows($stmt) > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Warehouse updated successfully!';
                } else {
                    $response['message'] = 'No changes were made to the warehouse.';
                }
            } else {
                $response['message'] = 'Error updating warehouse: ' . mysqli_stmt_error($stmt);
                $response['debug']['sql_error'] = mysqli_stmt_error($stmt);
            }
        }
    } elseif($_POST['action'] == 'delete') {
        $warehouse_id = (int)$_POST['id'];
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // First update any inventory items to remove warehouse reference
            $update_sql = "UPDATE inventory_items SET warehouse_id = NULL WHERE warehouse_id = ?";
            $update_stmt = mysqli_prepare($conn, $update_sql);
            
            if(!$update_stmt) {
                throw new Exception('Error preparing update statement: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($update_stmt, "i", $warehouse_id);
            if(!mysqli_stmt_execute($update_stmt)) {
                throw new Exception('Error updating inventory items: ' . mysqli_stmt_error($update_stmt));
            }
            
            // Now delete the warehouse
            $delete_sql = "DELETE FROM warehouses WHERE id = ?";
            $delete_stmt = mysqli_prepare($conn, $delete_sql);
            
            if(!$delete_stmt) {
                throw new Exception('Error preparing delete statement: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($delete_stmt, "i", $warehouse_id);
            if(!mysqli_stmt_execute($delete_stmt)) {
                throw new Exception('Error deleting warehouse: ' . mysqli_stmt_error($delete_stmt));
            }
            
            if(mysqli_stmt_affected_rows($delete_stmt) > 0) {
                mysqli_commit($conn);
                $response['success'] = true;
                $response['message'] = 'Warehouse deleted successfully!';
            } else {
                throw new Exception('Warehouse not found or already deleted.');
            }
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['debug']['error'] = $e->getMessage();
        }
    }
}

// Send JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit; 