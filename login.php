<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include database connection
include("connection.php");

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $countryCode = $_POST['countryCode'];
    $halfphone = $_POST['halfphone'];
    $phone = $countryCode . $halfphone;
    //$phone = trim($_POST['phone']);
    $password = $_POST['password'];

    // Validate input
    if (empty($phone) || empty($password)) {
        echo "Please enter both phone number and password.";
        exit;
    }

    // Lookup user by phone
    $stmt = $conn->prepare("SELECT * FROM users WHERE phone = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if user exists
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Verify password
        if (password_verify($password, $user['password'])) {
            // Start session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['userType'] = $user['userType'];

           if ($_SESSION['userType'] === 'client') {
                header("Location: ClientDashboard.php"); 
            } elseif ($_SESSION['userType'] === 'artisan') {
                header("Location: viewArtisanProfile.php"); 
            } else {
                header("Location: dashboard.php"); 
            }
        } else {
            echo "Invalid password!";
        }
    } else {
        echo "No user found with that phone number!";
    }

    $stmt->close();
    $conn->close();
}
?>
