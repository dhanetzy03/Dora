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
<!-- Inline full admin CSS to prevent FOUC on first load -->
<style>
body.shukran-admin * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body.shukran-admin {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fa;
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styles */
.sidebar {
    width: 260px;
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 1002;
    transition: transform 0.25s ease, width 0.25s ease;
}

/* Sidebar header / toggle */
.sidebar-header {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

/* Sidebar header / toggle */
.sidebar-header {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h2 {
    font-size: 24px;
    margin-bottom: 5px;
}

.sidebar-header p {
    font-size: 12px;
    opacity: 0.8;
}

.sidebar-nav {
    padding: 20px 0;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 15px 25px;
    color: rgba(255,255,255,0.8);
    text-decoration: none;
    transition: all 0.3s;
    border-left: 3px solid transparent;
}

.nav-item:hover {
    background: rgba(255,255,255,0.1);
    color: white;
}

.nav-item.active {
    background: rgba(255,255,255,0.15);
    color: white;
    border-left-color: white;
}

.nav-item i {
    font-size: 20px;
    margin-right: 15px;
}

/* Main Content (support both .main-content and legacy .main) */
.main-content,
.main {
    margin-left: 260px !important; /* account for fixed sidebar width */
    flex: 1;
    padding: 18px 16px; /* reduce horizontal padding so content aligns closer to sidebar */
    min-height: 100vh;
    background: transparent;
    position: relative;
    transition: margin-left 0.22s ease, width 0.22s ease;
}

/* Ensure main area fills remaining width beside the fixed sidebar */
.main-content, .main {
    width: calc(100% - 260px) !important;
}

/* Responsive: collapse sidebar on small screens and let main be full width */
@media (max-width: 900px) {
    .sidebar { transform: translateX(-260px); }
    .main-content, .main { margin-left: 0 !important; width: 100% !important; }
}

/* Collapsed sidebar state (toggle on body.collapsed) */
body.collapsed .sidebar {
    transform: translateX(-220px);
}

body.collapsed .main-content,
body.collapsed .main {
    margin-left: 60px;
}

body.collapsed .main-content,
body.collapsed .main {
    width: calc(100% - 60px);
}

/* Make sure sidebar nav stays scrollable and doesn't overlap content */
.sidebar-nav { padding: 18px 0 30px; }
.sidebar { box-shadow: 2px 0 8px rgba(0,0,0,0.08); }

/* Ensure sidebar header sticks on top when scrolling */
.sidebar-header { position: sticky; top: 0; z-index: 2; }

/* Ensure top bar spacing when using fixed header */
.main .top-bar,
.main-content .top-bar {
    margin-bottom: 18px;
}

.top-bar {
    background: white;
    padding: 20px 30px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.top-bar h1 {
    font-size: 24px;
    color: #333;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-info span {
    color: #666;
}

.btn-logout {
    background: #f44336;
    color: white;
    padding: 8px 16px;
    border-radius: 6px;
    text-decoration: none;
    transition: all 0.3s;
}

.btn-logout:hover {
    background: #d32f2f;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    padding: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon i {
    font-size: 28px;
}

.stat-info h3 {
    font-size: 32px;
    color: #333;
    margin-bottom: 5px;
}

.stat-info p {
    color: #666;
    font-size: 14px;
}

/* Content Card */
.content-card {
    margin: 0 0 30px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    padding: 25px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h2 {
    font-size: 20px;
    color: #333;
}

.btn-primary {
    background: #4a5568;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
}

.btn-primary:hover {
    background: #2d3748;
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.btn-secondary:hover {
    background: #5a6268;
}

/* Table Styles */
.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: #f8f9fa;
}

.data-table th,
.data-table td {
    padding: 15px;
    text-align: left;
    /* Remove blue focus ring on links and buttons for defense polish */
    a:focus, a:active, .qa-link:focus, .qa-link:active, .btn:focus, .btn:active, .btn-primary:focus, .btn-primary:active, .btn-secondary:focus, .btn-secondary:active {
        outline: none !important;
        box-shadow: none !important;
        background: inherit;
        color: inherit;
    }
    border-bottom: 1px solid #eee;
}

.data-table th {
    font-weight: 600;
    color: #666;
    font-size: 14px;
}

.data-table tbody tr:hover {
    background: #f8f9fa;
}

/* Badges */
.badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success {
    background: #d4edda;
    color: #155724;
}

.badge-warning {
    background: #fff3cd;
    color: #856404;
}

.badge-danger {
    background: #f8d7da;
    color: #721c24;
}

.badge-info {
    background: #d1ecf1;
    color: #0c5460;
}

/* Button Icons */
.btn-icon {
    background: none;
    border: none;
    color: #4a5568;
    cursor: pointer;
    padding: 5px;
    font-size: 18px;
    transition: all 0.3s;
}

.btn-icon:hover {
    color: #2d3748;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: white;
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 25px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    font-size: 20px;
    color: #333;
}

.modal-content form {
    padding: 25px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #333;
    font-weight: 500;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 14px;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    outline: none;
    border-color: #4a5568;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.modal-footer {
    padding: 20px 25px;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Quick Actions */
.quick-actions { margin: 18px 0; }
.qa-link { display:inline-block; padding:10px 14px; margin-right:8px; border-radius:6px; text-decoration:none; color:#fff; font-weight:600; }
.qa-link.primary { background:#0066ff; }
.qa-link.success { background:#28a745; }
.qa-link.warn { background:#ffc107; color:#222; }
.qa-link.purple { background:#6f42c1; }

.link-inventory { color:#0066ff; text-decoration:none; }

/* Stat icon background helpers */
.stat-icon.bg-success-light { background: #e8f5e9; }
.stat-icon.bg-danger-light { background: #ffebee; }
.stat-icon.bg-warning-light { background: #fff3cd; }
.stat-icon.bg-purple-light { background: #f0e6ff; }
.stat-icon.bg-info-light { background: #e0f7fa; }
.stat-icon i.icon-success { color: #4caf50; }
.stat-icon i.icon-danger { color: #f44336; }
.stat-icon i.icon-warning { color: #856404; }
.stat-icon i.icon-purple { color: #6200ea; }
.stat-icon i.icon-info { color: #00bcd4; }

/* Alerts and empty messages */
.alert-success { margin: 20px 30px; padding: 15px; background: #d4edda; color: #155724; border-radius: 8px; }
.empty-message { text-align: center; padding: 30px; color: #999; }

/* Button small */
.btn-sm { padding: 6px 12px; font-size: 12px; }

.table-margin-top { margin-top: 20px; }

.inline-form { display: inline-block; }

/* Utilities */
.text-center { text-align: center; }
.table-wrapper { overflow: auto; }
.form-gap { gap: 20px; }

/* Alerts */
.alert-error { margin: 12px 24px; padding: 12px; background: #f8d7da; color: #721c24; border-radius: 8px; }

/* Label helpers */
.label-normal { font-weight: normal; }

/* Page container */
.page-container { max-width: 1100px; margin: 24px 0; padding: 0 12px; }

/* Ensure page container aligns with .main offset instead of adding extra left margin */
.main .page-container { margin-left: 0; }

/* Hero / Quick actions block */
.hero-quick-actions { background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%); padding: 30px; border-radius: 12px; color: white; }
.hero-title { margin-bottom: 15px; font-size: 24px; }
.flex-gap { display:flex; gap:15px; flex-wrap:wrap; }
.daily-collection-list { padding: 20px; }
.muted-text { color: #999; text-align: center; padding: 20px; }

/* Small helpers for dashboard */
.link-simple { color: #4a5568; text-decoration: none; font-size: 14px; }
.text-right { text-align: right; }
.sale-number { display: block; color: #333; font-size: 1.1em; }
.muted-subtle { color: #666; }
.price-highlight { color: #4caf50; font-size: 1.2em; }
.badge-small { font-size: 11px; }
.card-inner { padding: 20px; }
.center-success { color: #4caf50; text-align: center; padding: 20px; }
.modal-lg { max-width: 900px; }
.mt-14 { margin-top: 14px; }
</style>
<!-- External CSS still loaded for browser cache and dev tools (with cache-busting) -->
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<script>
// Apply sidebar state BEFORE body renders to prevent layout shift
// DEFAULT: Sidebar is EXPANDED unless explicitly saved as collapsed
(function(){
    var storedState = localStorage.getItem('sidebarCollapsed');
    // Only collapse if explicitly set to 'true' in localStorage
    if (storedState === 'true') {
        document.documentElement.classList.add('sidebar-will-collapse');
    }
    // Otherwise default is expanded (no class needed)
})();
</script>
</head>
<body class="shukran-admin">

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
            <div class="stat-icon bg-success-light"><i class='bx bx-dollar-circle icon-success'></i></div>
            <div class="stat-info">
                <h3>₱<?= number_format($total_sales_today, 2) ?></h3>
                <p>Sales Today (Total Collection)</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon bg-danger-light"><i class='bx bx-error-circle icon-danger'></i></div>
            <div class="stat-info">
                <h3><?= $low_stock_count ?></h3>
                <p>Critical/Out of Stocks</p>
            </div>
        </div>
        
        </div>

    <div class="page-container">
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

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 30px; padding: 0 30px 30px;">
        
        <div class="content-card">
            <div class="card-header">
                <h2>🧾 Collection for Today (<?= $total_completed_sales_count ?> Receipts)</h2>
                <a href="reports.php?date=today" style="color: #4a5568; text-decoration: none; font-size: 14px;">View Full Report →</a>
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