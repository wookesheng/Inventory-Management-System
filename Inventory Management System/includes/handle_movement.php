<?php
session_start();
require_once "../config.php";

// Set JSON header
header('Content-Type: application/json');

// Check if database connection is established
if (!isset($conn) || $conn === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed'
    ]);
    exit;
}

$response = ['success' => false, 'message' => ''];

// Add email sending function at the top
function sendMovementNotificationEmail($to, $cc, $movement_details) {
    error_log("Attempting to send email notification to: " . $to . " with CC: " . implode(", ", $cc));
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
                <p><strong>Delivery Type:</strong> {$movement_details['delivery_type']}</p>
                <p><strong>Delivery Address:</strong> {$movement_details['delivery_address']}</p>
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
        'Reply-To: kesheng@ypccollege.edu.my'
    );

    // Add CC if there are administrators
    if (!empty($cc)) {
        $headers[] = 'Cc: ' . implode(', ', $cc);
    }

    $headers[] = 'X-Mailer: PHP/' . phpversion();

    error_log("Sending email with headers: " . print_r($headers, true));

    // Try to send email and log the result
    $mail_result = mail($to, $subject, $message, implode("\r\n", $headers));
    
    if ($mail_result) {
        error_log("Email sent successfully to: " . $to . " with CC: " . implode(", ", $cc));
        return true;
    } else {
        error_log("Failed to send email");
        error_log("Mail error info: " . error_get_last()['message']);
        return false;
    }
}

try {
    if (!isset($_POST['action'])) {
        throw new Exception('No action specified');
    }

    if ($_POST['action'] === 'add_movement') {
        // Validate required fields
        if (empty($_POST['items']) || !is_array($_POST['items'])) throw new Exception("Please select at least one item");
        if (empty($_POST['movement_type'])) throw new Exception("Please select a movement type");
        if (empty($_POST['delivery_type'])) throw new Exception("Please select a delivery type");

        $movement_type = $_POST['movement_type'];
        $delivery_type = $_POST['delivery_type'];
        $delivery_address = '';
        
        // Check if delivery address is required
        if ($delivery_type === 'Delivery To Site') {
            if (empty($_POST['delivery_address'])) {
                throw new Exception("Please enter a delivery address for site delivery");
            }
            $delivery_address = trim($_POST['delivery_address']);
        }
        
        $user_id = $_SESSION['id'] ?? 0;

        if ($user_id === 0) {
            throw new Exception("User session not found. Please log in again.");
        }

        mysqli_begin_transaction($conn);

        // Check if there's enough stock for OUT movements
        if ($movement_type === 'OUT') {
            foreach ($_POST['items'] as $item) {
                $item_id = (int)$item['id'];
                $quantity = (int)$item['quantity'];

            $check_sql = "SELECT quantity FROM inventory_items WHERE id = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            if (!$check_stmt) {
                throw new Exception("Error preparing stock check statement: " . mysqli_error($conn));
            }
            mysqli_stmt_bind_param($check_stmt, "i", $item_id);
            mysqli_stmt_execute($check_stmt);
            $result = mysqli_stmt_get_result($check_stmt);
            $current_stock = mysqli_fetch_assoc($result)['quantity'];
            
            if ($current_stock < $quantity) {
                    throw new Exception("Not enough stock available for item ID $item_id. Current stock: $current_stock");
            }
        }
        }

        $movement_ids = [];
        $affected_items = [];

        // Process each item
        foreach ($_POST['items'] as $item) {
            $item_id = (int)$item['id'];
            $quantity = (int)$item['quantity'];

        // Insert movement with pending status
            $sql = "INSERT INTO inventory_movements (item_id, movement_type, delivery_type, delivery_address, quantity, user_id, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            throw new Exception("Error preparing movement insert statement: " . mysqli_error($conn));
        }
        
            mysqli_stmt_bind_param($stmt, "issssi", $item_id, $movement_type, $delivery_type, $delivery_address, $quantity, $user_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error recording movement: " . mysqli_stmt_error($stmt));
        }

            $movement_ids[] = mysqli_insert_id($conn);

            // Get item details
        $item_sql = "SELECT name FROM inventory_items WHERE id = ?";
        $stmt = mysqli_prepare($conn, $item_sql);
        mysqli_stmt_bind_param($stmt, "i", $item_id);
        mysqli_stmt_execute($stmt);
        $item_result = mysqli_stmt_get_result($stmt);
            $item_data = mysqli_fetch_assoc($item_result);
            $affected_items[] = $item_data['name'];
        }

        // Get user details
        $user_sql = "SELECT fullName FROM users WHERE id = ?";
        $user_stmt = mysqli_prepare($conn, $user_sql);
        mysqli_stmt_bind_param($user_stmt, "i", $user_id);
        mysqli_stmt_execute($user_stmt);
        $user_result = mysqli_stmt_get_result($user_stmt);
        $user = mysqli_fetch_assoc($user_result);

        // Prepare movement details for email
        $movement_details = [
            'item_name' => implode(", ", $affected_items),
            'movement_type' => $movement_type,
            'delivery_type' => $delivery_type,
            'delivery_address' => $delivery_address,
            'quantity' => count($_POST['items']) . " items",
            'user_name' => $user['fullName']
        ];

        // Get managers and administrators separately
        $manager_sql = "SELECT id, email, fullName FROM users WHERE LOWER(position) = 'manager'";
        $admin_sql = "SELECT id, email, fullName FROM users WHERE LOWER(position) = 'administrator'";
        
        $manager_result = mysqli_query($conn, $manager_sql);
        $admin_result = mysqli_query($conn, $admin_sql);
        
        // Collect admin emails for CC
        $admin_emails = [];
        $admin_ids = [];
        while ($admin = mysqli_fetch_assoc($admin_result)) {
            $admin_emails[] = $admin['email'];
            $admin_ids[] = $admin['id'];
        }
        
        // Send notifications to managers with admins in CC
        $email_success = false;
        while ($manager = mysqli_fetch_assoc($manager_result)) {
            if (sendMovementNotificationEmail($manager['email'], $admin_emails, $movement_details)) {
                $email_success = true;
                
                // Create notification in database for this manager
                $notification_sql = "INSERT INTO notifications (type, message, user_id, created_at) VALUES (?, ?, ?, NOW())";
                $notification_stmt = mysqli_prepare($conn, $notification_sql);
                $notification_type = 'MOVEMENT_REVIEW';
                $notification_message = "New movement recorded for items: " . implode(", ", $affected_items) . " requires your review.";
                mysqli_stmt_bind_param($notification_stmt, "ssi", $notification_type, $notification_message, $manager['id']);
                mysqli_stmt_execute($notification_stmt);
            }
        }
        
        // Create database notifications for administrators
        foreach ($admin_ids as $admin_id) {
            $notification_sql = "INSERT INTO notifications (type, message, user_id, created_at) VALUES (?, ?, ?, NOW())";
            $notification_stmt = mysqli_prepare($conn, $notification_sql);
            $notification_type = 'MOVEMENT_REVIEW';
            $notification_message = "New movement recorded for items: " . implode(", ", $affected_items) . " requires review.";
            mysqli_stmt_bind_param($notification_stmt, "ssi", $notification_type, $notification_message, $admin_id);
            mysqli_stmt_execute($notification_stmt);
        }

        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Movements recorded successfully and pending approval";

    } elseif ($_POST['action'] === 'update_status') {
        // Validate required fields
        if (empty($_POST['movement_id'])) throw new Exception("Movement ID is required");
        if (empty($_POST['status'])) throw new Exception("Status is required");

        $movement_id = (int)$_POST['movement_id'];
        $status = $_POST['status'];
        $user_id = $_SESSION['id'];

        mysqli_begin_transaction($conn);

        // Get movement details
        $sql = "SELECT m.*, i.name as item_name, u.id as creator_id 
                FROM inventory_movements m 
                JOIN inventory_items i ON m.item_id = i.id 
                JOIN users u ON m.user_id = u.id 
                WHERE m.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "i", $movement_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $movement = mysqli_fetch_assoc($result);

        if (!$movement) {
            throw new Exception("Movement not found");
        }

        // Update movement status
        $sql = "UPDATE inventory_movements SET status = ? WHERE id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "si", $status, $movement_id);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error updating movement: " . mysqli_stmt_error($stmt));
        }

        // Update inventory quantity if confirmed
        if ($status === 'confirmed') {
            $quantity_change = $movement['movement_type'] === 'IN' ? $movement['quantity'] : -$movement['quantity'];
            
            $sql = "UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $quantity_change, $movement['item_id']);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception("Error updating inventory: " . mysqli_stmt_error($stmt));
            }

            // Check if item is now low in stock
            $sql = "SELECT quantity, minimum_stock FROM inventory_items WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $movement['item_id']);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $item = mysqli_fetch_assoc($result);
            
            if($item['quantity'] <= $item['minimum_stock']) {
                // Create low stock notification
                $notify_sql = "INSERT INTO notifications (type, message, user_id, created_at) 
                             SELECT 'LOW_STOCK', 
                                    CONCAT(?, ' is running low on stock (', ?, ' units remaining)'),
                                    id,
                                    NOW()
                             FROM users 
                             WHERE LOWER(position) IN ('administrator', 'manager', 'supervisor', 'worker', 'staff')";
                $stmt = mysqli_prepare($conn, $notify_sql);
                mysqli_stmt_bind_param($stmt, "si", $movement['item_name'], $item['quantity']);
                mysqli_stmt_execute($stmt);
            }
        }

        // Create notification for movement creator
        $notification_type = 'MOVEMENT_' . strtoupper($status);
        $notification_message = "Your {$movement['movement_type']} movement for {$movement['item_name']} has been {$status}";

        $sql = "INSERT INTO notifications (type, message, user_id, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssi", $notification_type, $notification_message, $movement['creator_id']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating notification: " . mysqli_stmt_error($stmt));
        }

        // Create notifications for all supervisors and workers
        $notification_message_all = "A {$movement['movement_type']} movement for {$movement['item_name']} has been {$status} by " . $_SESSION['fullName'];

        $sql = "INSERT INTO notifications (type, message, user_id, created_at) 
                SELECT ?, ?, id, NOW()
                FROM users 
                WHERE LOWER(position) IN ('supervisor', 'worker', 'staff')
                AND id != ? AND id != ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "ssii", $notification_type, $notification_message_all, $movement['creator_id'], $_SESSION['id']);
        
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Error creating notifications for supervisors and workers: " . mysqli_stmt_error($stmt));
        }

        mysqli_commit($conn);
        $response['success'] = true;
        $response['message'] = "Movement has been " . $status;
    }

} catch (Exception $e) {
    if (isset($conn) && mysqli_connect_errno() === 0) {
        mysqli_rollback($conn);
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log("Movement error: " . $e->getMessage());
}

echo json_encode($response);
exit; 