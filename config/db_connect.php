<?php
$servername = "localhost";
$username = "root"; // default XAMPP phpMyAdmin user
$password = ""; // leave blank unless you set one
$dbname = "shukran_cafe"; // change if your database name is different

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>