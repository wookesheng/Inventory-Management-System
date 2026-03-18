<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test email configuration
echo "Current PHP version: " . phpversion() . "\n";
echo "Checking mail settings...\n";
echo "SMTP: " . ini_get('SMTP') . "\n";
echo "smtp_port: " . ini_get('smtp_port') . "\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n\n";

// Test email script
$to = "kesheng@ypccollege.edu.my"; // Replace with the recipient's email
$subject = "Test Email from XAMPP";
$message = "This is a test email sent from your XAMPP server.";
$headers = "From: kesheng@ypccollege.edu.my\r\n"; // Replace with your Gmail address
$headers .= "Reply-To: kesheng@ypccollege.edu.my\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";

echo "Attempting to send email...\n";
if(mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully!\n";
} else {
    echo "Email sending failed.\n";
    
    // Check error log
    $error_log_path = "C:/xampp/sendmail/error.log";
    if(file_exists($error_log_path)) {
        $error_log = file_get_contents($error_log_path);
        echo "\nError Log:\n" . htmlspecialchars($error_log) . "\n";
    } else {
        echo "\nError log file not found at: " . $error_log_path . "\n";
    }
    
    // Check debug log
    $debug_log_path = "C:/xampp/sendmail/debug.log";
    if(file_exists($debug_log_path)) {
        $debug_log = file_get_contents($debug_log_path);
        echo "\nDebug Log:\n" . htmlspecialchars($debug_log) . "\n";
    } else {
        echo "\nDebug log file not found at: " . $debug_log_path . "\n";
    }
}
?> 