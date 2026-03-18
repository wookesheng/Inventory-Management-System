<?php
require_once "config.php";

echo "<h2>Password Test</h2>";

// Get current password hash from database
$sql = "SELECT password FROM users WHERE employeeID = 'NP00001'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
$stored_hash = $row['password'];

echo "Current stored hash: " . $stored_hash . "<br><br>";

// Test password verification
$test_password = 'admin123';
echo "Testing password: " . $test_password . "<br>";
echo "Verification result: " . (password_verify($test_password, $stored_hash) ? "Success" : "Failed") . "<br><br>";

// Create new hash
echo "Creating new hash...<br>";
$new_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "New hash: " . $new_hash . "<br>";
echo "Verification with new hash: " . (password_verify($test_password, $new_hash) ? "Success" : "Failed") . "<br><br>";

// Update password in database with new hash
$sql = "UPDATE users SET password = ? WHERE employeeID = 'NP00001'";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $new_hash);

if(mysqli_stmt_execute($stmt)) {
    echo "✓ Password updated in database<br>";
    echo "<br>Please try to login again with:<br>";
    echo "Employee ID: NP00001<br>";
    echo "Password: admin123<br>";
} else {
    echo "✗ Error updating password: " . mysqli_error($conn);
}

// Clear any existing sessions
session_start();
session_destroy();
?> 