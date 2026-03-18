<?php
require_once "config.php";

// Check if admin user exists
$sql = "SELECT id FROM users WHERE employeeID = 'NP00001'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) == 0) {
    // Admin user doesn't exist, create it
    $password = password_hash('admin123', PASSWORD_DEFAULT);
    $sql = "INSERT INTO users (employeeID, password, fullName, email) VALUES 
            ('NP00001', ?, 'System Administrator', 'admin@nehemiahprestress.com')";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $password);
    
    if(mysqli_stmt_execute($stmt)) {
        echo "Admin user created successfully!<br>";
        echo "Employee ID: NP00001<br>";
        echo "Password: admin123";
    } else {
        echo "Error creating admin user: " . mysqli_error($conn);
    }
} else {
    echo "Admin user already exists!";
}

// Check if users table exists
$sql = "SHOW TABLES LIKE 'users'";
$result = mysqli_query($conn, $sql);
if(mysqli_num_rows($result) == 0) {
    echo "<br>Warning: 'users' table does not exist. Please import the database schema first.";
}
?> 