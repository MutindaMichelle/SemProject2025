<?php
session_start();
include("connection.php");

if (
    !isset($_SESSION['otp']) || 
    !isset($_SESSION['pending_user']) || 
    !isset($_SESSION['otp_created_at'])
) {
    echo "Session expired or invalid access. Please register again.";
    exit;
}

// Timeout logic (5 minutes)
$otp_created_at = $_SESSION['otp_created_at'];
$current_time = time();
if (($current_time - $otp_created_at) > (5 * 60)) {
    echo "<p style='color: red;'>OTP has expired. Please restart registration.</p>";
    session_unset(); session_destroy();
    exit;
}

// Max attempts logic
if (!isset($_SESSION['otp_attempts'])) {
    $_SESSION['otp_attempts'] = 0;
}

if ($_SESSION['otp_attempts'] >= 3) {
    echo "<p style='color: red;'>Too many failed attempts. Please restart registration.</p>";
    session_unset(); session_destroy();
    exit;
}

$storedOtp = $_SESSION['otp'];
$user = $_SESSION['pending_user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredOtp = trim($_POST['entered_otp']);

    if ($enteredOtp == $storedOtp) {
        // Successful match — insert user into DB
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, userType) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $user['name'], $user['email'], $user['password'], $user['phone'], $user['userType']);

        if ($stmt->execute()) {
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['userType'] = $user['userType'];

            // Clear temporary session vars
            unset($_SESSION['otp']);
            unset($_SESSION['pending_user']);
            unset($_SESSION['otp_created_at']);
            unset($_SESSION['otp_attempts']);

            header("Location: created.php");
            exit;
        } else {
            echo "Database error: " . $stmt->error;
        }
    } else {
        $_SESSION['otp_attempts'] += 1;
        $remaining = 3 - $_SESSION['otp_attempts'];
        echo "<p style='color: red;'>Incorrect OTP. You have $remaining attempt(s) left.</p>";
    }
}
?>

<h2>OTP Verification</h2>
<p>We sent a 6-digit OTP to <?= htmlspecialchars($user['phone']) ?></p>
<form method="POST">
    <input type="text" name="entered_otp" placeholder="Enter OTP" required />
    <button type="submit">Verify OTP</button>
</form>

<!-- Resend OTP Section -->
<form method="POST" action="resend_otp.php" style="margin-top: 15px;">
    <button type="submit">Resend OTP</button>
</form>

