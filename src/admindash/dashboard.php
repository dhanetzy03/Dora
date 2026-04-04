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
<meta http-equiv="Cache-Control" content="no-store, must-revalidate">
<title>Admin Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
<script>
// Apply sidebar state BEFORE body renders to prevent layout shift
(function(){
    var storedState = localStorage.getItem('sidebarCollapsed');
    if (storedState === 'true') {
        document.documentElement.classList.add('sidebar-will-collapse');
    }
})();
</script>
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <!-- Modern Gradient Top Bar -->
    <div class="dashboard-header">
        <div class="dashboard-header-content">
            <h1><i class='bx bx-grid-alt'></i> Dashboard Overview</h1>
            <p class="dashboard-subtitle">Welcome back, <?= htmlspecialchars($_SESSION["username"]) ?> • <?= date('l, F j, Y') ?></p>
        </div>

    </div>

    <!-- Modern Stat Cards -->
    <div class="modern-stats-grid">
        <div class="modern-stat-card stat-green">
            <div class="stat-icon-modern">
                <i class='bx bx-dollar-circle'></i>
            </div>
            <div class="stat-content-modern">
                <h3>₱<?= number_format($total_sales_today, 2) ?></h3>
                <p>Sales Today</p>
            </div>
        </div>

        <div class="modern-stat-card stat-red">
            <div class="stat-icon-modern">
                <i class='bx bx-error-circle'></i>
            </div>
            <div class="stat-content-modern">
                <h3><?= $low_stock_count ?></h3>
                <p>Critical Stock</p>
            </div>
        </div>

        <div class="modern-stat-card stat-blue">
            <div class="stat-icon-modern">
                <i class='bx bx-receipt'></i>
            </div>
            <div class="stat-content-modern">
                <h3><?= $total_completed_sales_count ?></h3>
                <p>Completed Sales</p>
            </div>
        </div>

        <div class="modern-stat-card stat-purple">
            <div class="stat-icon-modern">
                <i class='bx bx-package'></i>
            </div>
            <div class="stat-content-modern">
                <h3><?= $total_inventory ?></h3>
                <p>Total Products</p>
            </div>
        </div>
    </div>

    <div class="quick-actions-container">
        <div class="hero-quick-actions">
            <h2 class="hero-title">Quick Actions</h2>
            <div class="flex-gap">
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

    <div class="dashboard-grid-2-1">
        
        <div class="content-card">
            <div class="card-header">
                <h2>🧾 Collection for Today (<?= $total_completed_sales_count ?> Receipts)</h2>
                <a href="reports.php?date=today" class="link-gray">View Full Report →</a>
            </div>
            <div class="daily-collection-list">
                <?php if (empty($daily_collection)): ?>
                    <p class="muted-text">No completed sales recorded today.</p>
                <?php else: ?>
                    <?php foreach ($daily_collection as $sale): ?>
                    <div class="list-item">
                        <div>
                            <strong class="sale-number"><?= htmlspecialchars($sale['sale_number']) ?></strong>
                            <small class="muted-subtle"><?= date('h:i A', strtotime($sale['created_at'])) ?></small>
                        </div>
                        <div class="text-right">
                            <strong class="price-highlight">₱<?= number_format($sale['total_amount'], 2) ?></strong>
                            <br>
                            <span class="badge badge-primary badge-small">
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
                <a href="inventory.php" class="link-gray">Manage Inventory →</a>
            </div>
            <div class="card-inner-padding">
                <?php if (empty($critical_stock)): ?>
                    <p class="stock-sufficient-message">All stock levels are sufficient! 👍</p>
                <?php else: ?>
                    <?php foreach ($critical_stock as $item): ?>
                    <div class="stock-item">
                        <div>
                            <strong class="stock-item-name"><?= htmlspecialchars($item['item_name']) ?></strong>
                            <small class="stock-item-category"><?= htmlspecialchars($item['category']) ?></small>
                        </div>
                        <div class="text-align-right">
                            <strong class="stock-item-qty"><?= $item['stock_qty'] ?> <?= $item['unit'] ?></strong>
                            <br>
                            <span class="badge <?= $item['stock_qty'] == 0 ? 'badge-danger' : 'badge-warning' ?> badge-xsmall">
                                <?= $item['stock_qty'] == 0 ? 'Out of Stock' : 'Low Stock (Reorder: ' . $item['reorder_level'] . ')' ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="content-card card-margin-x-30">
        <div class="card-header">
            <h2>System Information</h2>
        </div>
        <div class="system-info-grid">
            <div class="system-info-item">
                <p>System Name</p>
                <strong>Shukran Café Inventory System</strong>
            </div>
            <div class="system-info-item">
                <p>Total Users</p>
                <strong><?= $total_users ?> Active Users</strong>
            </div>
            <div class="system-info-item">
                <p>Server Time</p>
                <strong><?= date('M d, Y h:i A') ?></strong>
            </div>
        </div>
    </div>
</div>

</body>
</html>