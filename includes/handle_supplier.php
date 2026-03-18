<?php
require_once "../config.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to perform this action.']);
    exit;
}

// Check user's position/role for actions that modify suppliers
if(isset($_POST['action']) && in_array($_POST['action'], ['delete', 'edit', 'create'])) {
    $current_user_sql = "SELECT position FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $current_user_sql);
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $current_user = mysqli_fetch_assoc($result);
    $current_position = strtolower($current_user['position'] ?? '');
    
    // Check if user has permission to manage suppliers
    if(!in_array($current_position, ['administrator', 'manager', 'supervisor'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to manage suppliers.']);
        exit;
    }
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'data' => null];

// Debug logging
error_log('Request received: ' . print_r($_POST, true));

if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case 'get_details':
            if(isset($_POST['supplier_id'])) {
                $supplier_id = (int)$_POST['supplier_id'];
                error_log('Getting details for supplier ID: ' . $supplier_id);
                
                // Get supplier details with items
                $sql = "SELECT s.*, 
                        GROUP_CONCAT(
                            DISTINCT CONCAT_WS('|', i.name, i.sku)
                            ORDER BY i.name ASC
                            SEPARATOR '||'
                        ) as items_data
                        FROM suppliers s 
                        LEFT JOIN inventory_items i ON i.supplier_id = s.id 
                        WHERE s.id = ?
                        GROUP BY s.id, s.name, s.contact_person, s.email, s.phone";
                
                error_log('SQL Query: ' . $sql);
                
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, "i", $supplier_id);
                
                if(mysqli_stmt_execute($stmt)) {
                    $result = mysqli_stmt_get_result($stmt);
                    if($supplier = mysqli_fetch_assoc($result)) {
                        error_log('Raw supplier data: ' . print_r($supplier, true));
                        
                        // Format items array
                        $items = [];
                        if(!empty($supplier['items_data'])) {
                            $itemsList = explode('||', $supplier['items_data']);
                            foreach($itemsList as $item) {
                                $itemData = explode('|', $item);
                                if(count($itemData) == 2) {
                                    $items[] = [
                                        'name' => $itemData[0],
                                        'sku' => $itemData[1]
                                    ];
                                }
                            }
                        }
                        
                        // Remove items_data from response and add formatted items
                        unset($supplier['items_data']);
                        $supplier['items'] = $items;
                        
                        error_log('Formatted supplier data: ' . print_r($supplier, true));
                        
                        $response['success'] = true;
                        $response['data'] = $supplier;
                    } else {
                        error_log('Supplier not found');
                        $response['message'] = 'Supplier not found';
                    }
                } else {
                    $error = mysqli_error($conn);
                    error_log('Database error: ' . $error);
                    $response['message'] = 'Error fetching supplier details: ' . $error;
                }
            } else {
                error_log('Supplier ID not provided');
                $response['message'] = 'Supplier ID is required';
            }
            break;
            
        case 'delete':
            if(isset($_POST['supplier_id'])) {
                $supplier_id = (int)$_POST['supplier_id'];
                
                // Start transaction
                mysqli_begin_transaction($conn);
                
                try {
                    // First, update any items to remove this supplier reference
                    $update_sql = "UPDATE inventory_items SET supplier_id = NULL WHERE supplier_id = ?";
                    $update_stmt = mysqli_prepare($conn, $update_sql);
                    mysqli_stmt_bind_param($update_stmt, "i", $supplier_id);
                    mysqli_stmt_execute($update_stmt);
                    
                    // Then delete the supplier
                    $sql = "DELETE FROM suppliers WHERE id = ?";
                    $stmt = mysqli_prepare($conn, $sql);
                    mysqli_stmt_bind_param($stmt, "i", $supplier_id);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        if(mysqli_affected_rows($conn) > 0) {
                            mysqli_commit($conn);
                            $response['success'] = true;
                            $response['message'] = 'Supplier deleted successfully';
                        } else {
                            throw new Exception('Supplier not found');
                        }
                    } else {
                        throw new Exception('Error deleting supplier: ' . mysqli_error($conn));
                    }
                } catch (Exception $e) {
                    mysqli_rollback($conn);
                    $response['message'] = $e->getMessage();
                }
            } else {
                $response['message'] = 'Supplier ID is required';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
}

echo json_encode($response);
exit; 