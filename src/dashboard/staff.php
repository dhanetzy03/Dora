<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "staff") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Handle Sale Recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
    $sale_number = 'SALE-' . date('Ymd') . '-' . rand(1000, 9999);
    $sale_date = $_POST['sale_date'];
    $customer_name = trim($_POST['customer_name']);
    $total_amount = (float)$_POST['total_amount'];
    $payment_method = $_POST['payment_method'];
    $staff_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("INSERT INTO sales (sale_number, sale_date, customer_name, total_amount, payment_method, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("sssdsi", $sale_number, $sale_date, $customer_name, $total_amount, $payment_method, $staff_id);
    
    if ($stmt->execute()) {
        $success = "Sale recorded successfully! Waiting for admin validation.";
    } else {
        $error = "Error recording sale.";
    }
}

// Fetch staff's sales
$staff_id = $_SESSION['user_id'];
$my_sales = $conn->query("
    SELECT * FROM sales 
    WHERE created_by = $staff_id 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$total_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id")->fetch_assoc()['c'];
$pending_validation = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id AND status='pending'")->fetch_assoc()['c'];
$validated_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id AND status='completed'")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/staff-style.css">
</head>
<body>

<div class="sidebar">
    <div class="sidebar-header">
        <h2>☕ Shukran Café</h2>
        <p>Staff Panel</p>
    </div>
    <nav class="sidebar-nav">
        <a href="staff.php" class="nav-item active">
            <i class='bx bx-home'></i>
            <span>Dashboard</span>
        </a>
        <a href="#" class="nav-item">
            <i class='bx bx-receipt'></i>
            <span>My Sales</span>
        </a>
    </nav>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>
        <a href="../auth/logout.php" class="btn-logout">Logout</a>
    </div>

    <?php if (isset($success)): ?>
    <div style="margin: 20px 30px; padding: 15px; background: #d4edda; color: #155724; border-radius: 8px;">
        ✅ <?= $success ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div style="margin: 20px 30px; padding: 15px; background: #f8d7da; color: #721c24; border-radius: 8px;">
        ❌ <?= $error ?>
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;"><i class='bx bx-receipt' style="color: #2196f3;"></i></div>
            <div class="stat-info">
                <h3><?= $total_sales ?></h3>
                <p>Total Sales Recorded</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3cd;"><i class='bx bx-time' style="color: #856404;"></i></div>
            <div class="stat-info">
                <h3><?= $pending_validation ?></h3>
                <p>Pending Validation</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d4edda;"><i class='bx bx-check-circle' style="color: #155724;"></i></div>
            <div class="stat-info">
                <h3><?= $validated_sales ?></h3>
                <p>Validated Sales</p>
            </div>
        </div>
    </div>

    <!-- Record Sale Form -->
    <div class="content-card">
        <div class="card-header">
            <h2>Record New Sale</h2>
        </div>
        <form method="POST" style="padding: 25px;">
            <div class="form-row">
                <div class="form-group">
                    <label>Sale Date & Time *</label>
                    <input id="sale_date_input" type="datetime-local" name="sale_date" value="" required>
                </div>
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" placeholder="Walk-in customer">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Total Amount *</label>
                    <input type="number" step="0.01" name="total_amount" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Payment Method *</label>
                    <select name="payment_method" required>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="record_sale" class="btn-primary">
                <i class='bx bx-save'></i> Record Sale
            </button>
        </form>
    </div>

    <!-- My Sales History -->
    <div class="content-card">
        <div class="card-header">
            <h2>My Sales History</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_sales)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">No sales recorded yet</td></tr>
                    <?php else: ?>
                    <?php foreach ($my_sales as $sale): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td><?= ucfirst($sale['payment_method']) ?></td>
                        <td>
                            <?php
                                $status = $sale['status'];
                                $badge = $status == 'completed' ? 'badge-success' : ($status == 'pending' ? 'badge-warning' : 'badge-danger');
                            ?>
                            <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>

<script>
// Fill the datetime-local input with the user's local current date/time
document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('sale_date_input');
    if (!el) return;
    // If the field already has a value (e.g., editing), don't overwrite
    if (el.value && el.value.trim() !== '') return;
    var now = new Date();
    function pad(n){ return n.toString().padStart(2,'0'); }
    var local = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    el.value = local;
});
</script>
