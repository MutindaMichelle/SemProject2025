<?php
session_start();
require_once __DIR__ . '/vendor/autoload.php';
include("connection.php");

// Load .env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $countryCode = $_POST['countryCode'];
    $halfphone = $_POST['halfphone'];
    $phone = $countryCode . $halfphone;
    $userType = $_POST['userType'];

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm) || empty($phone) || empty($userType)) {
        echo "All fields are required!";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format!";
        exit;
    }

    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        echo "Invalid phone number format!";
        exit;
    }

    if (strlen($password) < 8) {
        echo "Password must be at least 8 characters long!";
        exit;
    }

    if ($password !== $confirm) {
        echo "Passwords do not match!";
        exit;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "Phone number already exists! Log in instead.";
        exit;
    }
    $stmt->close();

    // Store session
    $otp = rand(100000, 999999);
    $_SESSION['pending_user'] = [
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone,
        'userType' => $userType
    ];
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_created_at'] = time();
    $_SESSION['otp_attempts'] = 0;

    // Send SMS
    $username = $_ENV['AFRICASTALKING_USERNAME'];
    $apiKey = $_ENV['AFRICASTALKING_API_KEY'];
    $from = "AFRICASTKNG";
    $message = "Your JuaKazi verification code is: $otp";

    $data = http_build_query([
        "username" => $username,
        "to" => $phone,
        "message" => $message,
        "from" => $from
    ]);

    $headers = [
        "apiKey: $apiKey",
        "Content-Type: application/x-www-form-urlencoded"
    ];

    $ch = curl_init("https://api.africastalking.com/version1/messaging");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        echo "Failed to send OTP: " . curl_error($ch);
        exit;
    }
    curl_close($ch);

    header("Location: verify_otp.php");
    exit;
}
?>
