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

      // Save previous last_login (if any) so we can show it on the dashboard
      $_SESSION['last_login'] = $user['last_login'] ?? null;

      // Ensure users table has a last_login column; add if missing
      $colCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'last_login'");
      if ($colCheck && $colCheck->num_rows === 0) {
        $conn->query("ALTER TABLE users ADD COLUMN last_login DATETIME NULL");
      }

      // Update the user's last_login to current UTC time for consistency
      if (isset($user['user_id'])) {
        $upd = $conn->prepare("UPDATE users SET last_login = UTC_TIMESTAMP() WHERE user_id = ?");
        $upd->bind_param('i', $user['user_id']);
        $upd->execute();
        // ignore errors here - not critical
      }

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
  <title>Shukran Café - Login</title>
  <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      min-height: 100vh;
      background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
      position: relative;
      overflow: hidden;
    }

    /* Animated background elements */
    body::before {
      content: '';
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-image: url('./img/tagpuanBGlogin.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      opacity: 0.15;
      z-index: -1;
    }

    .login-container {
      display: flex;
      width: 100%;
      height: 100vh;
      position: relative;
      z-index: 1;
    }

    /* Left side - Branding */
    .login-brand {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      color: white;
      padding: 40px;
      background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    }

    .brand-logo {
      width: 200px;
      height: 200px;
      margin-bottom: 30px;
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.4);
      overflow: hidden;
      border: 4px solid rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
    }

    .brand-logo img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .brand-text h1 {
      font-size: 48px;
      font-weight: 700;
      margin-bottom: 10px;
      text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    }

    .brand-text p {
      font-size: 18px;
      opacity: 0.9;
      margin-bottom: 30px;
      text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
    }

    .brand-features {
      display: flex;
      flex-direction: column;
      gap: 15px;
      margin-top: 30px;
      text-align: center;
    }

    .feature {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 12px;
      opacity: 0.85;
      font-size: 16px;
    }

    .feature i {
      font-size: 24px;
      color: #4caf50;
    }

    /* Right side - Login Form */
    .login-form-container {
      flex: 1;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 40px;
      background: #f5f7fa;
    }

    .login-form {
      width: 100%;
      max-width: 420px;
      padding: 50px;
      background: white;
      border-radius: 16px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    }

    .form-header {
      margin-bottom: 40px;
      text-align: center;
    }

    .form-header h2 {
      font-size: 32px;
      color: #2d3748;
      margin-bottom: 10px;
      font-weight: 700;
    }

    .form-header p {
      color: #718096;
      font-size: 16px;
    }

    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 10px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .input-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .input-wrapper i {
      position: absolute;
      left: 15px;
      color: #4a5568;
      font-size: 20px;
      z-index: 1;
    }

    .form-group input {
      width: 100%;
      padding: 14px 15px 14px 50px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 16px;
      font-family: inherit;
      color: #2d3748;
      transition: all 0.3s;
      background: #f9fafb;
    }

    .form-group input:focus {
      outline: none;
      border-color: #4a5568;
      background: white;
      box-shadow: 0 0 0 3px rgba(74, 85, 104, 0.1);
    }

    .form-group input::placeholder {
      color: #cbd5e0;
    }

    .error-message {
      background: rgba(244, 67, 54, 0.1);
      color: #b71c1c;
      padding: 12px 16px;
      border-radius: 8px;
      border-left: 4px solid #f44336;
      margin-bottom: 25px;
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .error-message i {
      font-size: 18px;
    }

    .login-btn {
      width: 100%;
      padding: 14px 24px;
      background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      text-transform: uppercase;
      letter-spacing: 1px;
      transition: all 0.3s;
      box-shadow: 0 4px 15px rgba(74, 85, 104, 0.3);
    }

    .login-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(74, 85, 104, 0.4);
    }

    .login-btn:active {
      transform: translateY(0);
    }

    .form-footer {
      margin-top: 30px;
      text-align: center;
      color: #718096;
      font-size: 14px;
    }

    .form-footer a {
      color: #4a5568;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s;
    }

    .form-footer a:hover {
      color: #2d3748;
    }

    /* Responsive Design */
    @media (max-width: 900px) {
      .login-container {
        flex-direction: column;
        height: auto;
      }

      .login-brand {
        min-height: 300px;
        padding: 30px 20px;
      }

      .brand-logo {
        width: 150px;
        height: 150px;
      }

      .brand-text h1 {
        font-size: 36px;
      }

      .login-form-container {
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
      }

      .login-form {
        width: 90%;
        max-width: 420px;
        padding: 40px;
      }
    }

    @media (max-width: 600px) {
      .login-brand {
        display: none;
      }

      .login-form-container {
        flex: 1;
        min-height: 100vh;
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
      }

      .login-form {
        background: rgba(255, 255, 255, 0.95);
        width: 90%;
        padding: 30px;
        backdrop-filter: blur(10px);
      }

      .form-header h2 {
        font-size: 24px;
      }

      .form-header p {
        font-size: 14px;
      }

      .form-group {
        margin-bottom: 20px;
      }
    }
  </style>
</head>
<body>
  <div class="login-container">
    <!-- Left side - Branding -->
    <div class="login-brand">
      <div class="brand-logo">
        <img src="./img/skuhranlogo.jpg" alt="Shukran Café Logo">
      </div>
      <div class="brand-text">
        <h1>☕ Shukran</h1>
        <p>Premium Café Management System</p>
      </div>
      <div class="brand-features">
        <div class="feature">
          <i class='bx bx-shield-check'></i>
          <span>Secure Login</span>
        </div>
        <div class="feature">
          <i class='bx bx-trending-up'></i>
          <span>Real-time Analytics</span>
        </div>
        <div class="feature">
          <i class='bx bx-fast-forward'></i>
          <span>Efficient Workflow</span>
        </div>
      </div>
    </div>

    <!-- Right side - Login Form -->
    <div class="login-form-container">
      <form method="POST" action="" class="login-form">
        <div class="form-header">
          <h2>Welcome Back</h2>
          <p>Sign in to your account</p>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
          <i class='bx bx-error-circle'></i>
          <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>

        <div class="form-group">
          <label for="username">Username</label>
          <div class="input-wrapper">
            <i class='bx bx-user'></i>
            <input 
              type="text" 
              id="username"
              name="username" 
              placeholder="Enter your username" 
              required
              autocomplete="username"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="password">Password</label>
          <div class="input-wrapper">
            <i class='bx bx-lock-alt'></i>
            <input 
              type="password" 
              id="password"
              name="password" 
              placeholder="Enter your password" 
              required
              autocomplete="current-password"
            >
          </div>
        </div>

        <button type="submit" class="login-btn">
          <i class='bx bx-log-in' style="margin-right: 8px;"></i> Sign In
        </button>

        <div class="form-footer">
          <p>© 2025 Shukran Café. All rights reserved.</p>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
