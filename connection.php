<?php
$servername = "localhost";
$username = "root";
$password = "";
$db_name = "jua_kazi";
$port = "3307";


$conn = new mysqli($servername, $username, $password, $db_name, 3307);
if ($conn->connect_error) {
  die("Connection failed" . $conn->connect_error);
}
echo "";
