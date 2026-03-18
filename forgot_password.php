<?php
session_start();
require_once "config.php";

// Function to generate a 6-digit code
function generateVerificationCode() {
    return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
}

// Function to send verification email
function sendVerificationEmail($to, $code) {
    $subject = "Password Reset Verification Code";
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .code { 
                font-size: 24px; 
                font-weight: bold; 
                color: #dc3545; 
                text-align: center; 
                padding: 20px; 
                background: #f8f9fa; 
                border-radius: 5px; 
                margin: 20px 0;
            }
            .footer { 
                margin-top: 30px; 
                font-size: 12px; 
                color: #666; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h2>Password Reset Verification</h2>
            <p>You have requested to reset your password. Please use the following verification code to proceed:</p>
            <div class='code'>{$code}</div>
            <p>This code will expire in 15 minutes.</p>
            <p>If you didn't request this password reset, please ignore this email.</p>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>Nehemiah Prestress Inventory Management System</p>
            </div>
        </div>
    </body>
    </html>";

    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: kesheng@ypccollege.edu.my\r\n";
    $headers .= "Reply-To: kesheng@ypccollege.edu.my\r\n";

    return mail($to, $subject, $message, $headers);
}

if(isset($_POST["reset"])) {
    $employeeID = trim($_POST["employeeID"]);
    $email = trim($_POST["email"]);
    
    error_log("Password reset attempt - Employee ID: $employeeID, Email: $email");
    
    if(!preg_match("/^NP\d{5}$/", $employeeID)) {
        $error = "Invalid employee ID format. Must be NP followed by 5 digits (e.g., NP00001)";
    } else {
        $sql = "SELECT id, fullName FROM users WHERE employeeID = ? AND email = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "ss", $employeeID, $email);
            
            if(mysqli_stmt_execute($stmt)) {
                mysqli_stmt_store_result($stmt);
                
                if(mysqli_stmt_num_rows($stmt) == 1) {
                    mysqli_stmt_bind_result($stmt, $user_id, $fullName);
                    mysqli_stmt_fetch($stmt);
                    
                    // Generate code and set expiry to 15 minutes from now
                    $code = generateVerificationCode();
                    $expires_at = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                    
                    error_log("Generated code for user $user_id: $code, Expires: $expires_at");
                    
                    // Clear any existing reset tokens
                    $clear_sql = "UPDATE users SET reset_token = NULL, reset_expiry = NULL WHERE id = ?";
                    $clear_stmt = mysqli_prepare($conn, $clear_sql);
                    mysqli_stmt_bind_param($clear_stmt, "i", $user_id);
                    mysqli_stmt_execute($clear_stmt);
                    mysqli_stmt_close($clear_stmt);
                    
                    // Store new verification code
                    $update_sql = "UPDATE users SET reset_token = ?, reset_expiry = ? WHERE id = ?";
                    if($update_stmt = mysqli_prepare($conn, $update_sql)) {
                        mysqli_stmt_bind_param($update_stmt, "ssi", $code, $expires_at, $user_id);
                        
                        if(mysqli_stmt_execute($update_stmt)) {
                            // Double check the stored values
                            $verify_sql = "SELECT reset_token, reset_expiry FROM users WHERE id = ?";
                            $verify_stmt = mysqli_prepare($conn, $verify_sql);
                            mysqli_stmt_bind_param($verify_stmt, "i", $user_id);
                            mysqli_stmt_execute($verify_stmt);
                            $verify_result = mysqli_stmt_get_result($verify_stmt);
                            $verify_row = mysqli_fetch_assoc($verify_result);
                            mysqli_stmt_close($verify_stmt);
                            
                            error_log("Stored token verification - Token: {$verify_row['reset_token']}, Expiry: {$verify_row['reset_expiry']}");
                            
                            if(sendVerificationEmail($email, $code)) {
                                // Store everything in session
                                $_SESSION['reset_user_id'] = $user_id;
                                $_SESSION['reset_email'] = $email;
                                $_SESSION['reset_code'] = $code;
                                $_SESSION['reset_expiry'] = $expires_at;
                                
                                error_log("Session data set - User ID: $user_id, Email: $email, Code: $code, Expiry: $expires_at");
                                
                                header("location: verify_code.php");
                                exit;
                            } else {
                                $error = "Failed to send verification email. Please try again later.";
                                error_log("Failed to send email to: $email");
                            }
                        } else {
                            $error = "Error storing verification code. Please try again later.";
                            error_log("Failed to update reset token. Error: " . mysqli_error($conn));
                        }
                        mysqli_stmt_close($update_stmt);
                    }
                } else {
                    $error = "No matching records found for the provided Employee ID and Email.";
                    error_log("No user found - Employee ID: $employeeID, Email: $email");
                }
            } else {
                $error = "Error checking credentials. Please try again later.";
                error_log("Database error: " . mysqli_error($conn));
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
    <title>Forgot Password - Nehemiah Prestress Inventory Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="icon" type="image/jpg" href="images/nehemiahlogo.jpg">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('images/loginbg.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Poppins', sans-serif;
            padding: 1rem;
            position: relative;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            position: relative;
            z-index: 2;
            backdrop-filter: blur(10px);
        }

        .auth-header {
            text-align: center;
            padding: 2rem;
            background: #fff;
            margin-bottom: 1rem;
        }

        .auth-header h2 {
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #dc3545;
        }

        .auth-header p {
            margin: 0;
            font-size: 1rem;
            color: #6c757d;
        }

        .auth-body {
            padding: 2rem;
        }

        .alert {
            border: none;
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-size: 0.9rem;
            background: rgba(255, 255, 255, 0.9);
        }

        .alert-info {
            background-color: rgba(232, 244, 248, 0.9);
            color: #0c5460;
            border-left: 4px solid #17a2b8;
        }

        .alert-danger {
            background-color: rgba(253, 243, 244, 0.9);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }

        .alert-success {
            background-color: rgba(232, 246, 233, 0.9);
            color: #28a745;
            border-left: 4px solid #28a745;
        }

        .form-label {
            font-weight: 500;
            color: #344767;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            padding: 0.75rem 1rem;
            border-radius: 12px;
            border: 1px solid rgba(233, 236, 239, 0.8);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.9);
        }

        .form-control:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 3px rgba(220, 53, 69, 0.15);
            background: white;
        }

        .form-control::placeholder {
            color: #adb5bd;
        }

        .btn-primary {
            background-color: #dc3545;
            border: none;
            padding: 0.75rem;
            border-radius: 12px;
            font-weight: 500;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-primary:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.2);
        }

        .auth-links {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(233, 236, 239, 0.4);
        }

        .auth-links a {
            color: #dc3545;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
        }

        .auth-links a:hover {
            color: #c82333;
            background: rgba(255, 255, 255, 0.2);
        }

        .mb-3 {
            margin-bottom: 1.25rem !important;
        }

        @media (max-width: 576px) {
            .auth-container {
                margin: 1rem;
            }

            .auth-header {
                padding: 1.5rem;
            }

            .auth-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h2>Nehemiah Prestress</h2>
            <p>Password Reset</p>
        </div>
        
        <div class="auth-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <strong>Error!</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success)): ?>
                <div class="alert alert-success">
                    <strong>Success!</strong> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info">
                Enter your Employee ID and registered email address. A verification code will be sent to your email.
            </div>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="employeeID" class="form-label">Employee ID</label>
                    <input type="text" class="form-control" id="employeeID" name="employeeID" 
                           placeholder="Enter your ID (e.g., NP00001)" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           placeholder="Enter your registered email" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="reset" class="btn btn-primary">
                        Send Verification Code
                    </button>
                </div>
            </form>
            
            <div class="auth-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 