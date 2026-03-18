<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

// Get user's position and approval status
$sql = "SELECT position, approval_status FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

// If user is a manager and not approved, redirect to waiting page
if(strtolower($user['position']) === 'manager' && $user['approval_status'] !== 'approved') {
    // Create waiting page message based on status
    if($user['approval_status'] === 'pending') {
        $_SESSION['approval_message'] = "Your manager account is pending administrator approval. Please wait for approval to access the system.";
    } elseif($user['approval_status'] === 'rejected') {
        $_SESSION['approval_message'] = "Your manager account access has been rejected. Please contact the administrator.";
    }
    
    // Destroy session except for the message
    $approval_message = $_SESSION['approval_message'];
    session_destroy();
    session_start();
    $_SESSION['approval_message'] = $approval_message;
    
    header("location: waiting_approval.php");
    exit;
}
?> 