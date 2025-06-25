<?php
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_name'])) {
    header("Location: login.php"); // Redirect to login page
    exit();
}

$userName = htmlspecialchars($_SESSION['user_name']); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JuaKazi</title>
    <link rel="stylesheet" href="style.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
     <section class="registration_bg">
       <div class="white_cover"></div>
    </section>
    <div class="created_container" id="created_container">
        <i class="fa-solid fa-circle-check"></i>
        <p>Welcome, <?php echo $userName; ?>!</p>
        <h1>Account Creation Complete!</h1>
        
        <button onclick="window.location.href='ArtisanProfile.html'">Set Up Profile</button>
        </div>
</body>
</html>