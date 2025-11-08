<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once "../../config/db_connect.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    // Prepare SQL statement to get the user
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Since you're using plain text password
        if ($password === $user["password"]) {
            $_SESSION["username"] = $user["username"];
            $_SESSION["role"] = $user["role"]; // 'admin' or 'staff'
            $_SESSION["user_id"] = $user["user_id"];

            // Redirect based on role
            if ($user["role"] === "admin") {
                header("Location: ../admindash/admin.php");
                exit();
            } else {
                header("Location: ../dashboard/staff.php");
                exit();
            }
        } else {
            $error = "Invalid password.";
        }
    } else {
        $error = "Username not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Shukran Cafe Login Form</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background-color: #e0e5ec;
    }

    .container {
      width: 100%;
      max-width: 400px;
      padding: 40px;
      background-color: #e0e5ec;
      border-radius: 20px;
      box-shadow: 10px 10px 20px #b8b9be, -10px -10px 20px #ffffff;
    }

    .title {
      text-align: center;
      margin-bottom: 30px;
      color: #31344b;
      font-weight: 600;
      font-size: 28px;
      text-transform: uppercase;
      letter-spacing: 2px;
    }

    .input-group {
      margin-bottom: 30px;
      position: relative;
    }

    .input-group input {
      width: 100%;
      padding: 15px 20px 15px 50px;
      border: none;
      outline: none;
      background-color: #e0e5ec;
      border-radius: 15px;
      font-size: 16px;
      color: #31344b;
      box-shadow: inset 5px 5px 10px #b8b9be, inset -5px -5px 10px #ffffff;
    }

    .input-group i {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: #31344b;
      font-size: 20px;
    }

    .btn {
      width: 100%;
      padding: 15px;
      border: none;
      outline: none;
      background-color: #e0e5ec;
      border-radius: 15px;
      font-size: 16px;
      font-weight: 600;
      color: #31344b;
      cursor: pointer;
      box-shadow: 5px 5px 10px #b8b9be, -5px -5px 10px #ffffff;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .btn:hover {
      box-shadow: inset 5px 5px 10px #b8b9be, inset -5px -5px 10px #ffffff;
    }

    .error {
      color: red;
      text-align: center;
      margin-bottom: 15px;
      font-size: 14px;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="title">Login</h1>
    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="">
      <div class="input-group">
        <i class="bx bx-user"></i>
        <input type="text" name="username" placeholder="Username" required>
      </div>
      <div class="input-group">
        <i class="bx bx-lock-alt"></i>
        <input type="password" name="password" placeholder="Password" required>
      </div>
      <button type="submit" class="btn">Login</button>
    </form>
  </div>
</body>
</html>
