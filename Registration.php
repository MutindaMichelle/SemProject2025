<?php
session_start(); // ADDED: Start the session at the very beginning of the script.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include("connection.php"); // Ensure connection.php correctly establishes $conn for MySQLi

// Get the form values
if(isset($_POST['submit']))
{
    // ADDED: Trim whitespace from user inputs for cleaner data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm = $_POST['confirm_password'];
    $countryCode = $_POST['countryCode'];
    $halfphone = $_POST['halfphone'];
    $phone = $countryCode . $halfphone;

    $userType = $_POST['userType'];

    // Validate the inputs (YOUR EXISTING VALIDATION CODE IS KEPT)
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

    if(strlen($password) < 6) {
        echo "Password must be at least 6 characters long!";
        exit;
    }

    if($password !== $confirm) {
        echo "Passwords do not match!";
        exit;
    }

    // UPDATED: Prevent duplicate phone number using Prepared Statements (Security Fix!)
    // Your original: $check = "SELECT * FROM users WHERE phone='$phone'";
    // Your original: $result = $conn->query($check);
    $check_stmt = $conn->prepare("SELECT id FROM users WHERE phone = ?");
    if (!$check_stmt) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit; // Exit if statement preparation fails
    }
    $check_stmt->bind_param("s", $phone); // 's' for string parameter
    $check_stmt->execute();
    $check_stmt->store_result(); // Store the result set for num_rows

    if ($check_stmt->num_rows > 0) {
        echo "Phone number already exists! Log In instead.";
        $check_stmt->close(); // Close the statement
        exit;
    }
    $check_stmt->close(); // Close the statement


    // Hash the password (YOUR EXISTING CODE IS KEPT)
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // UPDATED: Insert the data into the database using Prepared Statements (Security Fix!)
    // Your original: $sql = "INSERT INTO users (name, email, password, phone, userType) VALUES ('$name', '$email', '$hashedPassword', '$phone','$userType')";
    // Your original: if ($conn->query($sql) === TRUE) {

    $insert_stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, userType) VALUES (?, ?, ?, ?, ?)");
    if (!$insert_stmt) {
        echo "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        exit; // Exit if statement preparation fails
    }
    $insert_stmt->bind_param("sssss", $name, $email, $hashedPassword, $phone, $userType); // 'sssss' for five string parameters

    if ($insert_stmt->execute()) {
        // ADDED: Registration successful! Now, automatically log the user in.
        // Get the ID of the newly inserted user (mysqli_insert_id is a property of the connection object)
        $new_user_id = $conn->insert_id;

        // Set session variables
        $_SESSION['user_id'] = $new_user_id;
        $_SESSION['user_name'] = $name; // Using the name from the form for immediate use
        $_SESSION['userType'] = $userType; // Crucial for profile redirection logic

        // UPDATED: Redirect to the profile creation page instead of created.html
        header("Location: created.php");
        exit(); // Always call exit() after header() redirects
    } else {
        // UPDATED: Use $insert_stmt->error for errors from prepared statements
        echo "Error: " . $insert_stmt->error;
    }

    $insert_stmt->close(); // Close the statement
}

$conn->close(); // Close the database connection
?>