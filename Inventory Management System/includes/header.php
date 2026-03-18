<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: login.php");
    exit;
}

require_once "config.php";

// Function to get unread notifications count
function getUnreadNotificationsCount($conn) {
    $user_id = $_SESSION["id"];
    $sql = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            $row = mysqli_fetch_assoc($result);
            return $row['count'];
        }
    }
    return 0;
}

$unread_notifications = getUnreadNotificationsCount($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nehemiah Prestress Inventory Management System</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/jpg" href="images/nehemiahlogo.jpg">
    
    <!-- JavaScript -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        :root {
            --nav-width: 250px;
            --main-color: #8B0000;
            --main-color-hover: #660000;
            --bg-color: #f5f5f5;
            --card-bg: #ffffff;
            --text-color: #333333;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--nav-width);
            height: 100vh;
            background-color: #212529;
            padding: 1rem;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }

        .logo {
            padding: 1rem 0;
            text-align: center;
            color: #fff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 1rem;
        }

        .logo h4 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }

        .logo small {
            font-size: 0.875rem;
            opacity: 0.7;
        }

        /* Navigation */
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
        }

        .nav-link:hover,
        .nav-link.active {
            background-color: var(--main-color);
            color: #fff;
            text-decoration: none;
        }

        .nav-icon {
            font-size: 1.25rem;
            margin-right: 1rem;
            min-width: 24px;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        /* Content Area */
        .content-wrapper {
            flex: 1;
            margin-left: var(--nav-width);
            padding: 2rem;
            width: calc(100% - var(--nav-width));
            min-height: 100vh;
        }

        /* Cards */
        .content-card {
            background: var(--card-bg);
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .card-title {
            color: var(--main-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #eee;
        }

        /* Profile Styles */
        .profile-content {
            display: flex;
            gap: 2rem;
        }

        .profile-image-section {
            flex: 0 0 auto;
            text-align: center;
        }

        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            overflow: hidden;
            margin: 0 auto 1rem;
            border: 3px solid #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-form {
            flex: 1;
            max-width: 600px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            font-size: 0.95rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            border-color: var(--main-color);
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
            outline: none;
        }

        /* Button Styles */
        .btn-primary {
            background-color: var(--main-color);
            border-color: var(--main-color);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: var(--main-color-hover);
            border-color: var(--main-color-hover);
            transform: translateY(-1px);
        }

        /* Password Form */
        .password-form {
            max-width: 500px;
        }

        /* Alert Messages */
        .alert {
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Notification Badge */
        .notification-badge {
            background-color: var(--main-color);
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 50%;
            font-size: 0.75rem;
            margin-left: auto;
        }

        /* Modal Styles */
        .modal-backdrop.show {
            opacity: 0.5;
            background-color: #000;
        }

        .modal-dialog {
            margin: 1.75rem auto;
            max-width: 400px;
        }

        .modal.fade .modal-dialog {
            transform: scale(0.95);
            transition: transform 0.2s ease-out;
        }

        .modal.show .modal-dialog {
            transform: scale(1);
        }

        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            background: #fff;
            position: relative;
        }

        .modal-header {
            padding: 1.25rem 1.5rem 0.75rem;
            border: none;
            align-items: flex-start;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3436;
            line-height: 1.5;
        }

        .modal-header .btn-close {
            padding: 1rem;
            margin: -0.5rem -0.5rem -0.5rem auto;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #adb5bd;
            opacity: 0.75;
            transition: opacity 0.2s;
            cursor: pointer;
        }

        .modal-header .btn-close:hover {
            opacity: 1;
        }

        .modal-body {
            padding: 0.75rem 1.5rem 1.5rem;
            text-align: center;
            color: #495057;
            font-size: 1rem;
        }

        .modal-footer {
            padding: 0 1.5rem 1.5rem;
            border: none;
            justify-content: center;
            gap: 0.75rem;
        }

        .modal-footer .btn {
            min-width: 120px;
            padding: 0.625rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .modal-footer .btn-danger {
            background-color: var(--main-color);
            border: none;
            color: #fff;
        }

        .modal-footer .btn-danger:hover {
            background-color: var(--main-color-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.2);
        }

        .modal-footer .btn-light {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            color: #495057;
        }

        .modal-footer .btn-light:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }

        /* Logout Link */
        .logout-link {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .logout-link .nav-link {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
        }

        .logout-link .nav-link:hover {
            background-color: #dc3545;
            color: #fff;
        }

        .logout-link .nav-icon {
            color: inherit;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <!-- Sidebar -->
        <nav class="sidebar">
            <div class="logo">
                <h4>Nehemiah Prestress</h4>
                <small>Inventory System</small>
            </div>
            
            <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                <i class='bx bx-grid-alt nav-icon'></i> Dashboard
            </a>
            
            <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
                <i class='bx bx-package nav-icon'></i> Inventory
            </a>
            
            <a href="movements.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'movements.php' ? 'active' : ''; ?>">
                <i class='bx bx-transfer nav-icon'></i> Movements
            </a>
            
            <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                <i class='bx bx-bell nav-icon'></i> Notifications
                <?php if($unread_notifications > 0): ?>
                    <span class="notification-badge"><?php echo $unread_notifications; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                <i class='bx bx-file nav-icon'></i> Reports
            </a>
            
            <a href="users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class='bx bx-user nav-icon'></i> Users
            </a>
            
            <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class='bx bx-cog nav-icon'></i> Profile Settings
            </a>

            <div class="logout-link">
                <a href="logout.php" class="nav-link">
                    <i class='bx bx-log-out nav-icon'></i> Logout
                </a>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Content will be injected here -->
        </main>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <a href="logout.php" class="btn btn-danger">Yes, Logout</a>
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Get all elements with logout links
        const logoutLinks = document.querySelectorAll('a[href="logout.php"]');
        
        // Add click event listener to each logout link
        logoutLinks.forEach(link => {
            if (link.closest('.modal-footer') === null) { // Skip the link inside the modal
                link.addEventListener('click', function(e) {
                    e.preventDefault(); // Prevent default link behavior
                    const logoutModal = new bootstrap.Modal(document.getElementById('confirmationModal'));
                    logoutModal.show();
                });
            }
        });
    });
    </script>
</body>
</html> 