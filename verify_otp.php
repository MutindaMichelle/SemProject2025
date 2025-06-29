<?php
session_start();

if (!isset($_SESSION['otp']) || !isset($_SESSION['pending_user'])) {
    echo "Something went wrong. Please go back and try again.";
    exit;
}

// Show the OTP for testing (only in dev mode!)
echo "<h2>OTP sent to " . $_SESSION['pending_user']['phone'] . "</h2>";
echo "<p>Your OTP is: <strong>" . $_SESSION['otp'] . "</strong></p>";

// Optional: Create a form to enter the OTP (not yet functional)
?>

<form action="#" method="POST">
    <input type="text" name="entered_otp" placeholder="Enter OTP here" />
    <button type="submit">Verify OTP</button>
</form>
