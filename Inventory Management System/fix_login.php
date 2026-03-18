<?php
require_once "config.php";

echo "<h2>Database Connection Check</h2>";
if($conn) {
    echo "✓ Database connection successful<br>";
} else {
    echo "✗ Database connection failed<br>";
    exit;
}

echo "<h2>Database Schema Check</h2>";
$sql = "SHOW TABLES LIKE 'users'";
$result = mysqli_query($conn, $sql);
if(mysqli_num_rows($result) > 0) {
    echo "✓ Users table exists<br>";
} else {
    echo "✗ Users table does not exist. Please import database.sql first<br>";
    exit;
}

echo "<h2>Admin User Check</h2>";
$sql = "SELECT * FROM users WHERE employeeID = 'NP00001'";
$result = mysqli_query($conn, $sql);

if($row = mysqli_fetch_assoc($result)) {
    echo "✓ Admin user exists<br>";
    echo "Current details:<br>";
    echo "- Employee ID: " . $row['employeeID'] . "<br>";
    echo "- Full Name: " . $row['fullName'] . "<br>";
    echo "- Email: " . $row['email'] . "<br>";
} else {
    echo "✗ Admin user does not exist<br>";
}

echo "<h2>Fixing Admin User</h2>";
// Delete existing admin user if exists
mysqli_query($conn, "DELETE FROM users WHERE employeeID = 'NP00001'");

// Create new admin user with fresh password
$password = password_hash('admin123', PASSWORD_DEFAULT);
$sql = "INSERT INTO users (employeeID, password, fullName, email) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);
$employeeID = 'NP00001';
$fullName = 'System Administrator';
$email = 'admin@nehemiahprestress.com';
mysqli_stmt_bind_param($stmt, "ssss", $employeeID, $password, $fullName, $email);

if(mysqli_stmt_execute($stmt)) {
    echo "✓ Admin user recreated successfully<br>";
    echo "<br>You can now login with:<br>";
    echo "Employee ID: NP00001<br>";
    echo "Password: admin123<br>";
    
    // Verify the password hash
    $sql = "SELECT password FROM users WHERE employeeID = 'NP00001'";
    $result = mysqli_query($conn, $sql);
    $row = mysqli_fetch_assoc($result);
    
    if(password_verify('admin123', $row['password'])) {
        echo "<br>✓ Password hash verification successful";
    } else {
        echo "<br>✗ Password hash verification failed";
    }
} else {
    echo "✗ Error creating admin user: " . mysqli_error($conn);
}

// Clear any existing sessions
session_start();
session_destroy();
?> 