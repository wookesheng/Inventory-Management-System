<?php
session_start();
require_once "config.php";

// Check if user is in the reset process
if(!isset($_SESSION['reset_user_id'])) {
    header("location: forgot_password.php");
    exit;
}

if(isset($_POST["reset"])) {
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    
    // Validate password
    if(strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Update password
        $sql = "UPDATE users SET password = ? WHERE id = ?";
        if($stmt = mysqli_prepare($conn, $sql)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            mysqli_stmt_bind_param($stmt, "si", $hashed_password, $_SESSION['reset_user_id']);
            
            if(mysqli_stmt_execute($stmt)) {
                // Clear session variables
                unset($_SESSION['reset_user_id']);
                unset($_SESSION['reset_email']);
                
                // Redirect to login page with success message
                $_SESSION['success'] = "Password has been reset successfully. Please login with your new password.";
                header("location: login.php");
                exit;
            } else {
                $error = "Error resetting password. Please try again later.";
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
    <title>Reset Password - Nehemiah Prestress Inventory Management System</title>
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

        .alert-danger {
            background-color: rgba(253, 243, 244, 0.9);
            color: #dc3545;
            border-left: 4px solid #dc3545;
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
            <p>Reset Password</p>
        </div>
        
        <div class="auth-body">
            <?php if(!empty($error)): ?>
                <div class="alert alert-danger">
                    <strong>Error!</strong> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           placeholder="Enter new password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           placeholder="Confirm new password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" name="reset" class="btn btn-primary">
                        Reset Password
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