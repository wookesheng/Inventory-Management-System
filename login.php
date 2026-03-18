<?php
session_start();
require_once "config.php";

if(isset($_POST["login"])) {
    $employeeID = trim($_POST["employeeID"]);
    $password = trim($_POST["password"]);
    
    // Debug information
    $debug_info = "";
    $debug_info .= "Attempting login with:<br>";
    $debug_info .= "Employee ID: " . htmlspecialchars($employeeID) . "<br>";
    $debug_info .= "Password length: " . strlen($password) . " characters<br><br>";
    
    // Validate employee ID format (NP followed by 5 digits)
    if(!preg_match("/^NP\d{5}$/", $employeeID)) {
        $login_err = "Invalid employee ID format. Must be NP followed by 5 digits (e.g., NP00001)";
        $debug_info .= "Failed at employee ID format validation<br>";
    } else {
        $debug_info .= "Employee ID format validation passed<br>";
        
        $sql = "SELECT id, employeeID, password, fullName FROM users WHERE employeeID = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "s", $employeeID);
            
            if(mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $id, $employeeID, $hashed_password, $fullName);
                    if(mysqli_stmt_fetch($stmt)) {
                        $debug_info .= "Found user in database<br>";
                        $debug_info .= "Database hash: " . $hashed_password . "<br>";
                        $debug_info .= "Testing password_verify...<br>";
                        
                        // Create a new hash with the input password for comparison
                        $test_hash = password_hash($password, PASSWORD_DEFAULT);
                        $debug_info .= "New hash of input: " . $test_hash . "<br>";
                        
                        if(password_verify($password, $hashed_password)) {
                            $debug_info .= "Password verification successful<br>";
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["employeeID"] = $employeeID;
                            $_SESSION["fullName"] = $fullName;
                            
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $login_err = "Invalid password.";
                            $debug_info .= "Password verification failed<br>";
                            $debug_info .= "Input password: " . $password . "<br>";
                        }
                    }
                } else {
                    $login_err = "Invalid employee ID.";
                    $debug_info .= "User not found in database<br>";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
                $debug_info .= "SQL execution failed: " . mysqli_error($conn) . "<br>";
            }
            mysqli_stmt_close($stmt);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Nehemiah Prestress Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" type="image/jpg" href="images/nehemiahlogo.jpg">
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Nehemiah Prestress</h2>
            <p>Inventory Management System</p>
        </div>
        
        <div class="auth-body">
            <?php if(!empty($login_err)): ?>
                <div class="alert alert-danger"><?php echo $login_err; ?></div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="employeeID" class="form-label">Employee ID</label>
                    <input type="text" class="form-control" id="employeeID" name="employeeID" placeholder="NP00000" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="login" class="btn btn-primary">Login</button>
                </div>
            </form>
            
            <div class="auth-links">
                <a href="forgot_password.php">Forgot Your Password?</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
