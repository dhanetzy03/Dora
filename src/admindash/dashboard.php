<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../../config/db_connect.php";

// Dashboard Statistics
$total_inventory = $conn->query("SELECT COUNT(*) as c FROM inventory")->fetch_assoc()['c'];
$low_stock = $conn->query("SELECT COUNT(*) as c FROM inventory WHERE status='Low Stock' OR status='Out of Stock'")->fetch_assoc()['c'];
$pending_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE status='pending'")->fetch_assoc()['c'];
$total_sales_today = $conn->query("SELECT COUNT(*) as c FROM sales WHERE DATE(sale_date) = CURDATE()")->fetch_assoc()['c'];
$total_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active'")->fetch_assoc()['c'];

// Recent Activity
$recent_sales = $conn->query("SELECT s.*, u.username FROM sales s LEFT JOIN users u ON s.created_by=u.user_id ORDER BY s.created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
$critical_stock = $conn->query("SELECT * FROM inventory WHERE status='Low Stock' OR status='Out of Stock' ORDER BY stock_qty ASC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Dashboard Overview</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;"><i class='bx bx-package' style="color: #2196f3;"></i></div>
            <div class="stat-info">
                <h3><?= $total_inventory ?></h3>
                <p>Total Inventory Items</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffebee;"><i class='bx bx-error-circle' style="color: #f44336;"></i></div>
            <div class="stat-info">
                <h3><?= $low_stock ?></h3>
                <p>Low/Out of Stock</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3cd;"><i class='bx bx-time-five' style="color: #856404;"></i></div>
            <div class="stat-info">
                <h3><?= $pending_sales ?></h3>
                <p>Pending Validations</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;"><i class='bx bx-receipt' style="color: #4caf50;"></i></div>
            <div class="stat-info">
                <h3><?= $total_sales_today ?></h3>
                <p>Sales Today</p>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div style="padding: 0 30px 20px;">
        <div style="background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%); padding: 30px; border-radius: 12px; color: white;">
            <h2 style="margin-bottom: 15px; font-size: 24px;">Quick Actions</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="inventory.php" style="background: rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 8px; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                    <i class='bx bx-package'></i> Manage Inventory
                </a>
                <a href="sales_validation.php" style="background: rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 8px; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                    <i class='bx bx-check-circle'></i> Validate Sales
                </a>
                <a href="stock_monitoring.php" style="background: rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 8px; color: white; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: all 0.3s;">
                    <i class='bx bx-line-chart'></i> View Reports
                </a>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; padding: 0 30px 30px;">
        <!-- Recent Sales -->
        <div class="content-card">
            <div class="card-header">
                <h2>Recent Sales</h2>
                <a href="sales_validation.php" style="color: #4a5568; text-decoration: none; font-size: 14px;">View All →</a>
            </div>
            <div style="padding: 20px;">
                <?php if (empty($recent_sales)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No recent sales</p>
                <?php else: ?>
                    <?php foreach ($recent_sales as $sale): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="display: block; color: #333;"><?= htmlspecialchars($sale['sale_number']) ?></strong>
                            <small style="color: #666;">by <?= htmlspecialchars($sale['username']) ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #4caf50;">₱<?= number_format($sale['total_amount'], 2) ?></strong>
                            <br>
                            <span class="badge <?= $sale['status']=='pending' ? 'badge-warning' : 'badge-success' ?>" style="font-size: 11px;">
                                <?= ucfirst($sale['status']) ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Critical Stock Items -->
        <div class="content-card">
            <div class="card-header">
                <h2>⚠️ Critical Stock</h2>
                <a href="inventory.php" style="color: #4a5568; text-decoration: none; font-size: 14px;">View All →</a>
            </div>
            <div style="padding: 20px;">
                <?php if (empty($critical_stock)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">All stock levels are good! 👍</p>
                <?php else: ?>
                    <?php foreach ($critical_stock as $item): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="display: block; color: #333;"><?= htmlspecialchars($item['item_name']) ?></strong>
                            <small style="color: #666;"><?= htmlspecialchars($item['category']) ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #f44336;"><?= $item['stock_qty'] ?> <?= $item['unit'] ?></strong>
                            <br>
                            <span class="badge <?= $item['status']=='Out of Stock' ? 'badge-danger' : 'badge-warning' ?>" style="font-size: 11px;">
                                <?= $item['status'] ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- System Info -->
    <div class="content-card" style="margin: 0 30px 30px;">
        <div class="card-header">
            <h2>System Information</h2>
        </div>
        <div style="padding: 25px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px;">
            <div>
                <p style="color: #666; margin-bottom: 5px;">System Name</p>
                <strong style="color: #333;">Shukran Café Inventory System</strong>
            </div>
            <div>
                <p style="color: #666; margin-bottom: 5px;">Total Users</p>
                <strong style="color: #333;"><?= $total_users ?> Active Users</strong>
            </div>
            <div>
                <p style="color: #666; margin-bottom: 5px;">Last Login</p>
                <strong style="color: #333;"><?= date('M d, Y H:i') ?></strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>
