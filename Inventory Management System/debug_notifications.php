<?php
require_once "includes/header.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

// Get current user's position
$current_user_sql = "SELECT id, fullName, position FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $current_user_sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$current_user = mysqli_fetch_assoc($result);
$current_position = strtolower($current_user['position'] ?? '');

// Get all users
$users_sql = "SELECT id, fullName, position FROM users ORDER BY position, fullName";
$users_result = mysqli_query($conn, $users_sql);
$users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users[] = $user;
}

// Get notifications for the current user
$notifications_sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
$stmt = mysqli_prepare($conn, $notifications_sql);
mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
mysqli_stmt_execute($stmt);
$notifications_result = mysqli_stmt_get_result($stmt);
$notifications = [];
while ($notification = mysqli_fetch_assoc($notifications_result)) {
    $notifications[] = $notification;
}

// Test sending a notification to all users
$test_sent = false;
if (isset($_POST['send_test'])) {
    $test_message = "Test notification from " . $current_user['fullName'] . " at " . date('Y-m-d H:i:s');
    
    // Insert notifications for all users
    $insert_sql = "INSERT INTO notifications (type, message, user_id, created_at) 
                  SELECT 'TEST', ?, id, NOW() FROM users";
    $stmt = mysqli_prepare($conn, $insert_sql);
    mysqli_stmt_bind_param($stmt, "s", $test_message);
    
    if (mysqli_stmt_execute($stmt)) {
        $test_sent = true;
    }
}
?>

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <h1 class="page-title">Notification System Debug</h1>
            <p>This page helps diagnose issues with the notification system.</p>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">
            <?php if ($test_sent): ?>
            <div class="alert alert-success">
                Test notification sent to all users successfully!
            </div>
            <?php endif; ?>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Current User Information</h5>
                        </div>
                        <div class="card-body">
                            <p><strong>ID:</strong> <?php echo $current_user['id']; ?></p>
                            <p><strong>Name:</strong> <?php echo $current_user['fullName']; ?></p>
                            <p><strong>Position:</strong> <?php echo $current_user['position']; ?></p>
                            
                            <form method="post" class="mt-3">
                                <button type="submit" name="send_test" class="btn btn-primary">
                                    Send Test Notification to All Users
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>Your Recent Notifications</h5>
                        </div>
                        <div class="card-body">
                            <?php if (count($notifications) > 0): ?>
                                <ul class="list-group">
                                <?php foreach ($notifications as $notification): ?>
                                    <li class="list-group-item">
                                        <div><strong>Type:</strong> <?php echo $notification['type']; ?></div>
                                        <div><strong>Message:</strong> <?php echo $notification['message']; ?></div>
                                        <div><small>Created: <?php echo $notification['created_at']; ?></small></div>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No notifications found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header">
                    <h5>All Users</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Position</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['fullName']; ?></td>
                                    <td><?php echo $user['position']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "includes/footer.php"; ?> 