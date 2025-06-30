<?php
session_start();
require_once 'connection.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 🧠 Validate session data
if (
    !isset($_SESSION['pending_user']) ||
    !isset($_SESSION['email_otp']) ||
    !isset($_SESSION['otp_created_at'])
) {
      // REDIRECT instead of inline message
    $_SESSION['error_message'] = "Session expired. Please register again.";
    header("Location: registration.php");
    exit;
}

// ⏰ Check if OTP expired (5 minutes)
$otpCreated = $_SESSION['otp_created_at'];
if (time() - $otpCreated > 300) {
     $_SESSION['error_message'] = "OTP has expired. Please register again.";
    session_unset(); session_destroy();
    header("Location: registration.php");
    exit;
}

// 🚫 Max attempt limit
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}
if ($_SESSION['otp_attempts'] >= 3) {
       $_SESSION['error_message'] = "Too many incorrect OTP attempts. Please register again.";
    session_unset(); session_destroy();
    header("Location: registration.php");
    exit;
}

$user = $_SESSION['pending_user'];
$storedOtp = (string)$_SESSION['email_otp']; // ✅ FIX: Cast OTP to string

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = trim($_POST['entered_otp']);

    // ✅ FIX: Ensure both values are strings before comparison
    if ((string)$enteredOtp === $storedOtp) {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, userType) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt) {
              $_SESSION['error_message'] = "Database error (prepare failed).";
            header("Location: registration.php");
            exit;
        }

        $stmt->bind_param(
            "sssss",
            $user['name'],
            $user['email'],
            $user['password'],
            $user['phone'],
            $user['userType']
        );

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['userType'] = $user['userType'];

            // 🎯 Clean session
            unset($_SESSION['pending_user']);
            unset($_SESSION['email_otp']);
            unset($_SESSION['otp_created_at']);
            unset($_SESSION['otp_attempts']);
            unset($_SESSION['last_resend']);
            unset($_SESSION['resend_success']);

            $stmt->close();
            $conn->close();
            header("Location: created.php");
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to insert into database.";
            $stmt->close(); $conn->close();
            header("Location: registration.php");
            exit;
        }
    } else {
       $_SESSION['otp_attempts']++;
        $remaining = 3 - $_SESSION['otp_attempts'];
        $_SESSION['error_message'] = "Incorrect OTP. You have $remaining attempt(s) left.";
        header("Location: verify_email.php");
        exit;
    }
}
?>

<!-- 💅 UI -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email OTP Verification</title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: #f3f3f3;
            padding: 40px;
            display: flex;
            justify-content: center;
        }
        .container {
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 0 12px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 {
            margin-bottom: 20px;
            color: #444;
        }
        input, button {
            width: 100%;
            padding: 10px;
            margin-top: 12px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        input {
            border: 1px solid #ccc;
        }
        button {
            background: #007bff;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #0056b3;
        }
        .resend {
            margin-top: 10px;
            text-align: center;
        }
        .message {
            color: green;
            margin-top: -10px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Verify Your Email</h2>
    <p>We sent an OTP to <strong><?= htmlspecialchars($user['email']) ?></strong>.</p>

    <?php if (isset($_SESSION['resend_success'])): ?>
        <p class="message"><?= $_SESSION['resend_success']; unset($_SESSION['resend_success']); ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="entered_otp" placeholder="Enter 6-digit OTP" maxlength="6" required />
        <button type="submit">Verify</button>
    </form>

    <form method="POST" action="resend_email_otp.php" class="resend">
        <button type="submit" style="background: #6c757d;">Resend OTP</button>
    </form>
</div>
</body>
</html>
