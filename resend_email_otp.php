<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
require_once 'connection.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Check session
if (!isset($_SESSION['pending_user'])) {
    $_SESSION['error_message'] = "Session expired. Please register again.";
    header("Location: registration.php");
    exit;
}

$user = $_SESSION['pending_user'];
$email = $user['email'];
$name = $user['name'];

//  Cooldown check
if (isset($_SESSION['last_resend']) && (time() - $_SESSION['last_resend'] < 60)) {
    $_SESSION['error_message'] = "Please wait at least 1 minute before resending OTP.";
    header("Location: verify_email.php");
    exit;
}

// 🔁 Generate and store OTP as string
$newOtp = strval(rand(100000, 999999)); // ✅ FIX: Store OTP as string
$_SESSION['email_otp'] = $newOtp;
$_SESSION['otp_created_at'] = time();
$_SESSION['otp_attempts'] = 0; // ✅ Reset attempts
$_SESSION['last_resend'] = time();

$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = $_ENV['EMAIL_HOST'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $_ENV['EMAIL_USERNAME'];
    $mail->Password   = $_ENV['EMAIL_PASSWORD'];
    $mail->SMTPSecure = 'tls';
    $mail->Port       = $_ENV['EMAIL_PORT'];

    $mail->setFrom($_ENV['EMAIL_FROM'], $_ENV['EMAIL_FROM_NAME']);
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = "Your Resent OTP - JuaKazi";
    $mail->Body    = "
        <h3>Hello again, $name 👋</h3>
        <p>Your new OTP is: <strong>$newOtp</strong></p>
        <p>This one expires in 5 minutes too. Don’t keep it waiting!</p>
    ";

    $mail->send();

    // ✅ Show success message on next page load
    $_SESSION['resend_success'] = "A new OTP has been sent to <strong>$email</strong>.";

    header("Location: verify_email.php");
    exit;

} catch (Exception $e) {
    echo "<p style='color: red;'>Failed to resend OTP: {$mail->ErrorInfo}</p>";
}
