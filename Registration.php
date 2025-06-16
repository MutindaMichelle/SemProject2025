<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


include("connection.php");

// Get the form values
if(isset($_POST['submit']))
{
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password']; // Check if passwords match
        $phone = $_POST['phone'];
        $userType = $_POST['userType'];

        // Validate the inputs

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

        //Prevent duplicate phone number
        $check = "SELECT * FROM users WHERE phone='$phone'";
        $result = $conn->query($check);
        if ($result->num_rows > 0) {
            echo "Phone number already exists!";
            exit;
        }

        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert the data into the database

        $sql = "INSERT INTO users (name, email, password, phone, userType) VALUES ('$name', '$email', '$hashedPassword', '$phone','$userType')";

        if ($conn->query($sql) === TRUE) {
            echo "Account created successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
}

$conn->close();
?>