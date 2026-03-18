<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "../config.php";
header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

try {
    if (!isset($_POST['warehouse_id'])) {
        throw new Exception('Warehouse ID is required');
    }

    $warehouse_id = (int)$_POST['warehouse_id'];

    // Get warehouse details
    $sql = "SELECT * FROM warehouses WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        throw new Exception("Error preparing warehouse query: " . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($stmt, "i", $warehouse_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $warehouse = mysqli_fetch_assoc($result);

    if (!$warehouse) {
        throw new Exception("Warehouse not found");
    }

    // Get total items and storage statistics
    $stats_sql = "SELECT 
        COUNT(*) as total_items,
        COALESCE(SUM(quantity), 0) as total_quantity
        FROM inventory_items 
        WHERE warehouse_id = ? AND is_deleted = 0";
    
    $stmt = mysqli_prepare($conn, $stats_sql);
    mysqli_stmt_bind_param($stmt, "i", $warehouse_id);
    mysqli_stmt_execute($stmt);
    $stats = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    // Calculate storage usage
    $total_quantity = (int)$stats['total_quantity'];
    $capacity = (int)$warehouse['capacity'];
    $storage_used = $capacity > 0 ? ($total_quantity / $capacity) * 100 : 0;
    $storage_used = min(100, round($storage_used, 1)); // Cap at 100% and round to 1 decimal
    $available_space = max(0, $capacity - $total_quantity);

    // Get detailed item list
    $items_sql = "SELECT 
        i.sku as product_code,
        i.name as product_name,
        i.category,
        i.quantity,
        i.created_at as last_updated
        FROM inventory_items i
        WHERE i.warehouse_id = ? AND i.is_deleted = 0
        ORDER BY i.name";

    $stmt = mysqli_prepare($conn, $items_sql);
    mysqli_stmt_bind_param($stmt, "i", $warehouse_id);
    mysqli_stmt_execute($stmt);
    $items = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);

    // Format dates in the items array
    foreach ($items as &$item) {
        $item['last_updated'] = date('M d, Y H:i', strtotime($item['last_updated']));
    }

    // Format the response
    $response = [
        'success' => true,
        'warehouse' => $warehouse,
        'total_items' => number_format($stats['total_items']),
        'storage_used' => $storage_used,
        'available_space' => number_format($available_space),
        'items' => $items
    ];

} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    error_log("Warehouse details error: " . $e->getMessage());
}

echo json_encode($response);
exit;
?> 