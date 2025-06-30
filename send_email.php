<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Autoload Composer dependencies
require_once __DIR__ . '/vendor/autoload.php';

// Import PHPMailer and Dotenv classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Set up PHPMailer
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

    // Grab user email from registration form or session
    $userEmail = $_SESSION['pending_user']['email'] ?? null;

    if (!$userEmail) {
        echo "User email not found in session.";
        exit;
    }

    $mail->addAddress($userEmail);

    // ✅ Generate and store OTP
    $otp = rand(100000, 999999);
    $_SESSION['email_otp'] = $otp;
    $_SESSION['otp_created_at'] = time();
    $_SESSION['otp_attempts'] = 0;

    // ✅ Compose the message
    $mail->isHTML(true);
    $mail->Subject = 'Your JuaKazi OTP Code';
    $mail->Body    = "
        <h2>OTP Verification</h2>
        <p>Hello 👋,</p>
        <p>Your JuaKazi OTP code is:</p>
        <h3 style='color: navy;'>$otp</h3>
        <p>This code will expire in 5 minutes.</p>
    ";

    $mail->send();
    echo "✅ OTP sent to your email: <strong>$userEmail</strong>";

} catch (Exception $e) {
    echo "❌ Email sending failed: {$mail->ErrorInfo}";
}
