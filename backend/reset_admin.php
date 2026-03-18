<?php
require_once "config.php";

// New password for admin (admin123)
$new_password = password_hash('admin123', PASSWORD_DEFAULT);

// Update admin password
$sql = "UPDATE users SET password = ? WHERE employeeID = 'NP00001'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $new_password);

if(mysqli_stmt_execute($stmt)) {
    echo "Admin password has been reset successfully!<br>";
    echo "Employee ID: NP00001<br>";
    echo "Password: admin123";
} else {
    echo "Error resetting password: " . mysqli_error($conn);
}
?> 