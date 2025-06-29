<?php
session_start();

// ✅ Load Composer autoloader and env
require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
use AfricasTalking\SDK\AfricasTalking;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad(); // ✅ Prevents crashing if .env is missing (bonus tip)

// ✅ Connect to DB
require_once 'connection.php';

// ✅ Make sure user is in the middle of registration
if (!isset($_SESSION['pending_user'])) {
    http_response_code(403);
    exit("Session expired. Please restart registration.");
}

$user = $_SESSION['pending_user'];
$phone = $user['phone'] ?? '';

if (empty($phone)) {
    http_response_code(400);
    exit("Invalid phone number.");
}

// ✅ Throttle resend: 1 OTP every 60 seconds
if (isset($_SESSION['last_resend']) && (time() - $_SESSION['last_resend'] < 60)) {
    $wait = 60 - (time() - $_SESSION['last_resend']);
    exit("Please wait $wait seconds before resending.");
}

// ✅ Generate new OTP
$otp = rand(100000, 999999);

// ✅ Update session with fresh OTP data
$_SESSION['otp'] = $otp;
$_SESSION['otp_created_at'] = time();
$_SESSION['otp_attempts'] = 0;
$_SESSION['last_resend'] = time();

// ✅ Fetch Africa's Talking credentials from .env
$username = $_ENV['AT_USERNAME'] ?? '';
$apiKey = $_ENV['AT_API_KEY'] ?? '';

if (empty($username) || empty($apiKey)) {
    http_response_code(500);
    exit("Africa's Talking credentials are missing. Check your .env file.");
}

// ✅ Initialize Africa's Talking
$AT = new AfricasTalking($username, $apiKey);
$sms = $AT->sms();

// ✅ Send OTP SMS
$message = "JuaKazi OTP (Resent): $otp";

try {
    $result = $sms->send([
        'to'      => $phone,
        'message' => $message,
        'from'    => 'AFRICASTKNG'
    ]);
    echo "OTP resent successfully.";
} catch (Exception $e) {
    http_response_code(500);
    echo "Failed to resend OTP: " . $e->getMessage();
}
