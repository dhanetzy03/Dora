<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

require_once "../../config/db_connect.php";

// ===========================================
// 1. DASHBOARD STATISTICS & DATA FETCHING
// ===========================================

// Total inventory items (still needed for the Critical Stock List on the right)
$total_inventory = $conn->query("SELECT COUNT(*) as c FROM inventory")->fetch_assoc()['c']; //

// CRITICAL / OUT OF STOCKS (Total Count)
// Checks if stock_qty is less than or equal to reorder_level (the proper inventory check)
$low_stock_count = $conn->query("SELECT COUNT(*) as c FROM inventory WHERE stock_qty <= reorder_level")->fetch_assoc()['c']; //

// PENDING VALIDATIONS (Data fetch kept, but card removed)
$pending_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE status='pending'")->fetch_assoc()['c']; //

// --- SALES TODAY (Total Revenue / Total Collection) ---
$sales_today_result = $conn->query("SELECT SUM(total_amount) AS total_sales FROM sales WHERE DATE(sale_date) = CURDATE() AND status='completed'"); //
$total_sales_today = $sales_today_result->fetch_assoc()['total_sales'] ?? 0; //
$total_sales_today = (float)$total_sales_today; // Ensure it's treated as a float //

$total_users = $conn->query("SELECT COUNT(*) as c FROM users WHERE status='active'")->fetch_assoc()['c']; //

// --- COLLECTION FOR THE DAY (Recent completed sales list) ---
// Fetches sale number, amount, and the mode of payment
$daily_collection_query = "
    SELECT sale_number, total_amount, payment_method, created_at
    FROM sales 
    WHERE DATE(sale_date) = CURDATE() AND status='completed' 
    ORDER BY created_at DESC LIMIT 5
"; //
$daily_collection = $conn->query($daily_collection_query)->fetch_all(MYSQLI_ASSOC); //

$total_completed_sales_count = $conn->query("SELECT COUNT(*) as c FROM sales WHERE DATE(sale_date) = CURDATE() AND status='completed'")->fetch_assoc()['c']; //


// CRITICAL / OUT OF STOCKS (List for the side card)
$critical_stock = $conn->query("
    SELECT * FROM inventory 
    WHERE stock_qty <= reorder_level
    ORDER BY stock_qty ASC LIMIT 5
")->fetch_all(MYSQLI_ASSOC); //
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css">
<style>
/* Custom style for the daily collection list */
.daily-collection-list {
    min-height: 200px; /* Ensure visual space */
    padding-right: 30px;
}
.daily-collection-list .list-item {
    padding: 15px 0;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.daily-collection-list .list-item:last-child {
    border-bottom: none;
}
.quick-action-link {
    background: rgba(255,255,255,0.2); 
    padding: 12px 20px; 
    border-radius: 8px; 
    color: white; 
    text-decoration: none; 
    display: flex; 
    align-items: center; 
    gap: 10px; 
    transition: all 0.3s;
    font-weight: 500;
}
.quick-action-link:hover {
    background: rgba(255,255,255,0.35);
    transform: translateY(-2px);
}
.badge-danger { background-color: #f44336; color: white; padding: 4px 8px; border-radius: 4px; }
.badge-warning { background-color: #ff9800; color: white; padding: 4px 8px; border-radius: 4px; }
.badge-primary { background-color: #5a67d8; color: white; padding: 4px 8px; border-radius: 4px; }
</style>
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

    <div class="stats-grid">
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;"><i class='bx bx-dollar-circle' style="color: #4caf50;"></i></div>
            <div class="stat-info">
                <h3>₱<?= number_format($total_sales_today, 2) ?></h3>
                <p>Sales Today (Total Collection)</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background: #ffebee;"><i class='bx bx-error-circle' style="color: #f44336;"></i></div>
            <div class="stat-info">
                <h3><?= $low_stock_count ?></h3>
                <p>Critical/Out of Stocks</p>
            </div>
        </div>
        
        </div>

    <div style="padding: 0 30px 20px;">
        <div style="background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%); padding: 30px; border-radius: 12px; color: white;">
            <h2 style="margin-bottom: 15px; font-size: 24px;">Quick Actions</h2>
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="inventory.php" class="quick-action-link">
                    <i class='bx bx-package'></i> Manage Inventory
                </a>
                <a href="reports.php" class="quick-action-link">
                    <i class='bx bx-line-chart'></i> Reports
                </a>
                <a href="adjusting_entry.php" class="quick-action-link">
                    <i class='bx bx-transfer-alt'></i> Adjusting Entry
                </a>
                <a href="sales_validation.php" class="quick-action-link">
                    <i class='bx bx-check-circle'></i> Validate Sales
                </a>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; padding: 0 30px 30px;">
        
        <div class="content-card">
            <div class="card-header">
                <h2>🧾 Collection for Today (<?= $total_completed_sales_count ?> Receipts)</h2>
                <a href="reports.php?date=today" style="color: #4a5568; text-decoration: none; font-size: 14px;">View Full Report →</a>
            </div>
            <div class="daily-collection-list" style="padding: 20px;">
                <?php if (empty($daily_collection)): ?>
                    <p style="color: #999; text-align: center; padding: 20px;">No completed sales recorded today.</p>
                <?php else: ?>
                    <?php foreach ($daily_collection as $sale): ?>
                    <div class="list-item">
                        <div>
                            <strong style="display: block; color: #333; font-size: 1.1em;"><?= htmlspecialchars($sale['sale_number']) ?></strong>
                            <small style="color: #666;"><?= date('h:i A', strtotime($sale['created_at'])) ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #4caf50; font-size: 1.2em;">₱<?= number_format($sale['total_amount'], 2) ?></strong>
                            <br>
                            <span class="badge badge-primary" style="font-size: 11px;">
                                <?= htmlspecialchars($sale['payment_method'] ?? 'N/A') ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="card-header">
                <h2>📉 Critical Stock Items</h2>
                <a href="inventory.php" style="color: #4a5568; text-decoration: none; font-size: 14px;">Manage Inventory →</a>
            </div>
            <div style="padding: 20px;">
                <?php if (empty($critical_stock)): ?>
                    <p style="color: #4caf50; text-align: center; padding: 20px;">All stock levels are sufficient! 👍</p>
                <?php else: ?>
                    <?php foreach ($critical_stock as $item): ?>
                    <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="display: block; color: #333;"><?= htmlspecialchars($item['item_name']) ?></strong>
                            <small style="color: #666;"><?= htmlspecialchars($item['category']) ?></small>
                        </div>
                        <div style="text-align: right;">
                            <strong style="color: #f44336; font-size: 1.1em;"><?= $item['stock_qty'] ?> <?= $item['unit'] ?></strong>
                            <br>
                            <span class="badge <?= $item['stock_qty'] == 0 ? 'badge-danger' : 'badge-warning' ?>" style="font-size: 11px;">
                                <?= $item['stock_qty'] == 0 ? 'Out of Stock' : 'Low Stock (Reorder: ' . $item['reorder_level'] . ')' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

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
                <p style="color: #666; margin-bottom: 5px;">Server Time</p>
                <strong style="color: #333;"><?= date('M d, Y h:i A') ?></strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>