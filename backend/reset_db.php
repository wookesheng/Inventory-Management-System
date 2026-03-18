<?php
require_once "config.php";

echo "<h2>Database Reset</h2>";

// Drop and recreate the users table
$sql = "DROP TABLE IF EXISTS users";
if(mysqli_query($conn, $sql)) {
    echo "✓ Old users table dropped<br>";
} else {
    echo "✗ Error dropping users table: " . mysqli_error($conn) . "<br>";
}

$sql = "CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employeeID VARCHAR(7) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    fullName VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    profile_picture VARCHAR(255),
    reset_token VARCHAR(64),
    reset_expiry DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if(mysqli_query($conn, $sql)) {
    echo "✓ New users table created<br>";
} else {
    echo "✗ Error creating users table: " . mysqli_error($conn) . "<br>";
}

// Insert admin user with known hash
// This is the hash for 'admin123'
$known_hash = '$2y$10$8KzO8Nzv0RrHzUzW.DxX8.XKFhxT8dGqBJY9A3LOxKPCN0YZ3tKPi';
$sql = "INSERT INTO users (employeeID, password, fullName, email) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $sql);

$employeeID = 'NP00001';
$fullName = 'System Administrator';
$email = 'admin@nehemiahprestress.com';

mysqli_stmt_bind_param($stmt, "ssss", $employeeID, $known_hash, $fullName, $email);

if(mysqli_stmt_execute($stmt)) {
    echo "✓ Admin user created with known password hash<br>";
} else {
    echo "✗ Error creating admin user: " . mysqli_error($conn) . "<br>";
}

// Verify the password
$sql = "SELECT password FROM users WHERE employeeID = 'NP00001'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

if(password_verify('admin123', $row['password'])) {
    echo "✓ Password verification successful<br>";
} else {
    echo "✗ Password verification failed<br>";
}

echo "<br>Now try to login with:<br>";
echo "Employee ID: NP00001<br>";
echo "Password: admin123<br>";

// Clear any existing sessions
session_start();
session_destroy();
?> 