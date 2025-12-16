<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Fetch stock movements
$movements = $conn->query("
    SELECT sm.*, i.item_name, u.username 
    FROM stock_movements sm
    LEFT JOIN inventory i ON sm.product_id = i.id
    LEFT JOIN users u ON sm.created_by = u.user_id
    ORDER BY sm.created_at DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// Calculate stats
$total_in = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM stock_movements WHERE movement_type='in'")->fetch_assoc()['total'];
$total_out = $conn->query("SELECT COALESCE(SUM(quantity), 0) as total FROM stock_movements WHERE movement_type='out'")->fetch_assoc()['total'];
$low_stock = $conn->query("SELECT COUNT(*) as c FROM inventory WHERE stock_qty <= reorder_level")->fetch_assoc()['c'];

// --- NEW FEATURE: Fast/Slow Moving Calculations ---

// Fast Moving (Condition 1: stock meets critical level in 2 days | Condition 2: 80% within the week)
// Simplified: 80% of current stock quantity was moved OUT in the last 7 days.
$fast_moving_count = $conn->query("SELECT COUNT(i.id) as c 
    FROM inventory i
    INNER JOIN (
        SELECT product_id, SUM(quantity) as weekly_out 
        FROM stock_movements 
        WHERE movement_type='out' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY product_id
    ) sm ON i.id = sm.product_id
    WHERE sm.weekly_out >= (i.stock_qty * 0.8)
")->fetch_assoc()['c'];

// Slow Moving (Condition 1: 10 days not critical | Condition 2: stock is ordered after 2 months)
// Simplified: No OUT movements in the last 60 days AND the current stock is NOT low/critical.
$slow_moving_count = $conn->query("SELECT COUNT(i.id) as c 
    FROM inventory i
    LEFT JOIN (
        SELECT DISTINCT product_id 
        FROM stock_movements 
        WHERE movement_type='out' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) 
    ) sm ON i.id = sm.product_id
    WHERE sm.product_id IS NULL AND i.stock_qty > 0 AND i.stock_qty > i.reorder_level
")->fetch_assoc()['c'];

// ----------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Stock Monitoring - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Stock Monitoring</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;"><i class='bx bx-download' style="color: #4caf50;"></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_in) ?></h3>
                <p>Total Stock In</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffebee;"><i class='bx bx-upload' style="color: #f44336;"></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_out) ?></h3>
                <p>Total Stock Out</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3cd;"><i class='bx bx-error' style="color: #856404;"></i></div>
            <div class="stat-info">
                <h3><?= $low_stock ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="background: #f0e6ff;"><i class='bx bx-run' style="color: #6200ea;"></i></div>
            <div class="stat-info">
                <h3><?= $fast_moving_count ?></h3>
                <p>Fast Moving Items</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e0f7fa;"><i class='bx bx-walk' style="color: #00bcd4;"></i></div>
            <div class="stat-info">
                <h3><?= $slow_moving_count ?></h3>
                <p>Slow Moving Items</p>
            </div>
        </div>
        </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Transaction Monitoring (Stock Movement History)</h2>
            <button class="btn-primary" onclick="showAddMovement()"><i class='bx bx-plus'></i> Add Movement</button>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Previous Stock</th>
                        <th>New Stock</th>
                        <th>Reference</th>
                        <th>By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($movements as $mov): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($mov['created_at'])) ?></td>
                        <td><strong><?= htmlspecialchars($mov['item_name'] ?? 'N/A') ?></strong></td>
                        <td>
                            <?php
                                $type = $mov['movement_type'];
                                $badge = $type == 'in' ? 'badge-success' : ($type == 'out' ? 'badge-danger' : 'badge-info');
                            ?>
                            <span class="badge <?= $badge ?>"><?= strtoupper($type) ?></span>
                        </td>
                        <td><?= number_format($mov['quantity']) ?></td>
                        <td><?= number_format($mov['previous_quantity']) ?></td>
                        <td><?= number_format($mov['new_quantity']) ?></td>
                        <td><?= ucfirst($mov['reference_type']) ?></td>
                        <td><?= htmlspecialchars($mov['username'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($mov['remarks'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function showAddMovement() {
    alert('Add Stock Movement - Feature coming soon!');
}
</script>

</body>
</html>