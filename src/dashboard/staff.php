<?php
session_start();
require_once "../../config/db_connect.php";

if (!isset($_SESSION["username"])) {
    header("Location: ../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Staff Dashboard</title>
</head>
<body>
  <h1>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>
  <p>This is the staff dashboard.</p>
  <a href="../logout.php">Logout</a>
</body>
</html>
