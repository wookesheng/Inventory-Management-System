<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waiting for Approval - Nehemiah Prestress Inventory System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="icon" type="image/jpg" href="images/nehemiahlogo.jpg">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: url('images/warehouse.jpg') no-repeat center center fixed;
            background-size: cover;
            font-family: 'Poppins', sans-serif;
            padding: 1rem;
        }

        .waiting-container {
            width: 100%;
            max-width: 500px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            text-align: center;
            padding: 2rem;
        }

        .waiting-icon {
            font-size: 4rem;
            color: #ffc107;
            margin-bottom: 1rem;
        }

        .waiting-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 1rem;
        }

        .waiting-message {
            color: #636e72;
            margin-bottom: 2rem;
        }

        .back-to-login {
            color: #dc3545;
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-to-login:hover {
            color: #c82333;
        }
    </style>
</head>
<body>
    <div class="waiting-container">
        <div class="waiting-icon">⏳</div>
        <h1 class="waiting-title">Account Approval Required</h1>
        <p class="waiting-message">
            <?php
            session_start();
            echo $_SESSION['approval_message'] ?? "Your account requires administrator approval to access the system.";
            ?>
        </p>
        <a href="login.php" class="back-to-login">
            ← Back to Login
        </a>
    </div>
</body>
</html> 