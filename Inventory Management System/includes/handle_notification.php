<?php
require_once "../config.php";
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if(!isset($_SESSION['id'])) {
    $response['message'] = 'Not authenticated';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['id'];

if(isset($_POST['action'])) {
    switch($_POST['action']) {
        case 'mark_all_read':
            $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            
            if(mysqli_stmt_execute($stmt)) {
                $response['success'] = true;
                $response['message'] = 'All notifications marked as read';
            } else {
                $response['message'] = 'Error updating notifications: ' . mysqli_error($conn);
            }
            break;
            
        case 'delete_selected':
            if(!empty($_POST['ids'])) {
                $ids = is_array($_POST['ids']) ? $_POST['ids'] : [$_POST['ids']];
                $ids = array_map('intval', $ids);
                $placeholders = str_repeat('?,', count($ids) - 1) . '?';
                
                $sql = "DELETE FROM notifications WHERE id IN ($placeholders) AND user_id = ?";
                $stmt = mysqli_prepare($conn, $sql);
                
                if($stmt) {
                    $types = str_repeat('i', count($ids)) . 'i';
                    $params = array_merge($ids, [$user_id]);
                    mysqli_stmt_bind_param($stmt, $types, ...$params);
                    
                    if(mysqli_stmt_execute($stmt)) {
                        $affected_rows = mysqli_stmt_affected_rows($stmt);
                        $response['success'] = true;
                        $response['message'] = "Successfully deleted $affected_rows notification(s)";
                    } else {
                        $response['message'] = 'Error deleting notifications: ' . mysqli_stmt_error($stmt);
                    }
                } else {
                    $response['message'] = 'Error preparing statement: ' . mysqli_error($conn);
                }
            } else {
                $response['message'] = 'No notifications selected';
            }
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
}

echo json_encode($response);
exit; 