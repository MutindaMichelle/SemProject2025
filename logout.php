<?php
session_start();

// Destroy all session variables
$_SESSION = [];
session_unset();
session_destroy();

// Optional: Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1
header("Pragma: no-cache"); // HTTP 1.0
header("Expires: 0"); // Proxies

// Optional: Add a redirect message
$redirectMessage = urlencode("You have been successfully logged out.");
echo "<script>alert('You have been successfully logged out.');</script>";

// Redirect to login page (add ?loggedout=1 or message if desired)
header("Location: index.html?message=$redirectMessage");
exit();
?>
