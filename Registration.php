<?php
include("connection.php");
// Get the form values
if(isset($_POST['submit']))
{
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = $_POST['password'];
        $phone = $_POST['phone'];
        $userType = $_POST['userType'];

        $sql = "INSERT INTO users (name, email, password, phone, userType) VALUES ('$name', '$email', '$password', '$phone','$userType')";

        if ($conn->query($sql) === TRUE) {
            echo "Account created successfully!";
        } else {
            echo "Error: " . $conn->error;
        }
}

$conn->close();
?>