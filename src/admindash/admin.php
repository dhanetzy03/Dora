<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

// Include DB connection
require_once __DIR__ . '/../../config/db_connect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $category = trim($_POST['category']);
    $stock_qty = (int)$_POST['stock_qty'];
    $unit = trim($_POST['unit'] ?? 'pcs');
    $reorder_level_input = trim($_POST['reorder_level']);
    $reorder_level = ($reorder_level_input === "") ? null : (int)$reorder_level_input;

    // Check if item already exists (case-insensitive)
    $check = $conn->prepare("SELECT id, stock_qty, reorder_level, stock_in, stock_out FROM inventory WHERE LOWER(item_name) = LOWER(?)");
    $check->bind_param("s", $item_name);
    $check->execute();
    $result = $check->get_result();

    if ($result && $result->num_rows > 0) {
        // Item exists — update stock and keep reorder level if not changed
        $item = $result->fetch_assoc();
        $new_stock = $item['stock_qty'] + $stock_qty;
        $reorder_level_final = $reorder_level ?? $item['reorder_level'];
        $new_stock_in = $item['stock_in'] + $stock_qty;

        // Determine status
        if ($new_stock <= 0) {
            $status = "Out of Stock";
        } elseif ($reorder_level_final !== null && $new_stock <= $reorder_level_final) {
            $status = "Low Stock";
        } else {
            $status = "Sufficient";
        }

        if ($reorder_level !== null) {
            $update = $conn->prepare("UPDATE inventory SET stock_qty=?, reorder_level=?, stock_in=?, status=?, last_updated=NOW() WHERE id=?");
            $update->bind_param("iiisi", $new_stock, $reorder_level_final, $new_stock_in, $status, $item['id']);
        } else {
            $update = $conn->prepare("UPDATE inventory SET stock_qty=?, stock_in=?, status=?, last_updated=NOW() WHERE id=?");
            $update->bind_param("iisi", $new_stock, $new_stock_in, $status, $item['id']);
        }

        $update->execute();
        $update->close();
        header("Location: admin.php");
        exit();

    } else {
        // New item — reorder level is required
        if ($reorder_level === null) {
            $error = "⚠️ Please enter a reorder level for new items.";
        } else {
            $status = ($stock_qty <= 0)
                ? "Out of Stock"
                : (($stock_qty <= $reorder_level) ? "Low Stock" : "Sufficient");
            $stmt = $conn->prepare("INSERT INTO inventory (item_name, category, stock_qty, reorder_level, status, unit, stock_in, stock_out, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stock_in = $stock_qty;
            $stock_out = 0;
            $stmt->bind_param("ssiissii", $item_name, $category, $stock_qty, $reorder_level, $status, $unit, $stock_in, $stock_out);
            $stmt->execute();
            $stmt->close();
            header("Location: admin.php");
            exit();
        }
    }

    if ($check) {
        $check->close();
    }
}

// --- Fetch Inventory Items ---
$result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");
$inventory = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// --- Dashboard Stats ---
$totalItems = count($inventory);
$criticalItems = $conn->query("SELECT COUNT(*) AS c FROM inventory WHERE status = 'Low Stock' OR status = 'Out of Stock'")->fetch_assoc()['c'] ?? 0;
$recentChanges = $conn->query("SELECT COUNT(*) AS c FROM inventory WHERE DATE(last_updated) = CURDATE()")->fetch_assoc()['c'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard</title>
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

.close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

.close:hover {
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
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
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

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
    <h1>A Web-Based Inventory Tracking System with Enhanced Stock Monitoring and Sales Validation for Shukran Café</h1>

    <div class="quick-actions">
        <a href="inventory.php" class="qa-link primary">Manage Inventory</a>
        <a href="sales_validation.php" class="qa-link success">Sales Validation</a>
        <a href="stock_monitoring.php" class="qa-link warn">Stock Monitoring</a>
        <a href="reports.php" class="qa-link purple">Reports</a>
    </div>

    <div class="dashboard-cards">
        <div class="card"><h3>Total Items</h3><p><?= $totalItems ?></p></div>
        <div class="card"><h3>Critical Items</h3><p class="critical"><?= $criticalItems ?></p></div>
        <div class="card"><h3>Recent Stock Changes</h3><p class="recent">↑ <?= $recentChanges ?></p></div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="item_name" placeholder="Item Name" required>
        <select name="category" required>
            <option value="">Select Category</option>
            <option value="Beverage">Beverage</option>
            <option value="Meat">Meat</option>
            <option value="Dry Goods">Dry Goods</option>
            <option value="Condiments">Condiments</option>
        </select>
        <input type="number" name="stock_qty" placeholder="Stock Qty" required>
        <input type="text" name="unit" placeholder="Unit (e.g., pcs, kg, ml)">
        <input type="number" name="reorder_level" placeholder="Reorder Level (Optional)">
        <button type="submit">Add Item</button>
    </form>

    <div class="inventory-section">
        <div class="inventory-header"><h2>Inventory</h2></div>

        <table>
            <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Stock In</th>
                <th>Stock Out</th>
                <th>Current Stock</th>
                <th>Status</th>
                <th>Last Updated</th>
            </tr>
            <?php foreach ($inventory as $item): ?>
                <tr>
                    <td><a href="inventory.php?view_id=<?= urlencode($item['id']) ?>" class="link-inventory"><?= htmlspecialchars($item['item_name']) ?></a></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['stock_qty']) ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                    <td><?= htmlspecialchars($item['stock_in'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($item['stock_out'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($item['stock_qty']) ?></td>
                    <td>
                        <?php
                            $statusRaw = $item['status'] ?? '';
                            $statusKey = strtolower(str_replace(' ', '', $statusRaw));
                            if ($statusKey === 'sufficient') {
                                $class = 'sufficient';
                            } elseif ($statusKey === 'lowstock' || $statusKey === 'low') {
                                $class = 'low';
                            } else {
                                $class = 'out';
                            }
                        ?>
                        <span class="status <?= $class ?>"><?= htmlspecialchars($item['status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($item['last_updated'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

</body>
</html>
