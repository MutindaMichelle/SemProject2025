<?php
session_start();
include("connection.php");

if (isset($_POST['submit'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $countryCode = $_POST['countryCode'];
    $halfphone = $_POST['halfphone'];
    $phone = $countryCode . $halfphone;
    $userType = $_POST['userType'];

    // Basic validation
    if(empty($name) || empty($email) || empty($password) || empty($confirm) || empty($phone) || empty($userType)) {
        echo "All fields are required!";
        exit;
    }

    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid email format!";
        exit;
    }

    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        echo "Invalid phone number format!";
        exit;
    }

    if(strlen($password) < 8) {
        echo "Password must be at least 8 characters long!";
        exit;
    }

    if($password !== $confirm) {
        echo "Passwords do not match!";
        exit;
    }

    // Check for duplicate phone
    $stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        echo "Phone number already exists! Log in instead.";
        exit;
    }
    $stmt->close();

    // ✅ Generate OTP and store everything in session
    $otp = rand(100000, 999999);

    $_SESSION['pending_user'] = [
        'name' => $name,
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'phone' => $phone,
        'userType' => $userType
    ];
    $_SESSION['otp'] = $otp;

    // ✅ Send OTP using Africa’s Talking
    $username = "sandbox"; // or your app username
    $apiKey = "atsk_eb4d50822f35464bb3646818b4acfc150965fc95c9148847674e7037023255c13793d8c9";
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

    // ✅ Go to OTP verification page
    header("Location: verify_otp.php");
    exit;
}
?>
