<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

$msg = '';
$err = '';

// Handle manual snapshot creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_snapshot'])) {
    $snapshot_date = $_POST['snapshot_date'] ?? date('Y-m-d');
    $snapshot_type = $_POST['snapshot_type'] ?? 'ending';
    $period_type = $_POST['period_type'] ?? 'daily';
    $user_id = $_SESSION['user_id'] ?? null;

    // Delete existing snapshots for this date/type to avoid duplicates
    $del = $conn->prepare("DELETE FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = ?");
    $del->bind_param('ss', $snapshot_date, $snapshot_type);
    $del->execute();
    $del->close();

    // Get all inventory items and create snapshots
    $inventory = $conn->query("SELECT id, item_name, stock_qty, cost_per_unit FROM inventory")->fetch_all(MYSQLI_ASSOC);
    $stmt = $conn->prepare("INSERT INTO inventory_snapshots (snapshot_date, snapshot_type, period_type, item_id, item_name, quantity, cost_per_unit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    $count = 0;
    foreach ($inventory as $item) {
        $item_id = $item['id'];
        $item_name = $item['item_name'];
        $qty = (float)($item['stock_qty'] ?? 0);
        $cost = (float)($item['cost_per_unit'] ?? 0);
        
        $stmt->bind_param('ssissddi', $snapshot_date, $snapshot_type, $period_type, $item_id, $item_name, $qty, $cost, $user_id);
        $stmt->execute();
        $count++;
    }
    $stmt->close();
    
    $msg = "Snapshot created successfully! Captured $count items for " . ucfirst($snapshot_type) . " Inventory on $snapshot_date.";
}

// Handle auto snapshot for today
if (isset($_GET['auto_snapshot'])) {
    $snapshot_date = date('Y-m-d');
    $snapshot_type = $_GET['type'] ?? 'ending';
    $period_type = 'daily';
    $user_id = $_SESSION['user_id'] ?? null;

    // Check if snapshot already exists
    $check = $conn->prepare("SELECT COUNT(*) as c FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = ?");
    $check->bind_param('ss', $snapshot_date, $snapshot_type);
    $check->execute();
    $exists = $check->get_result()->fetch_assoc()['c'];
    $check->close();

    if ($exists > 0) {
        $err = ucfirst($snapshot_type) . " snapshot for today already exists!";
    } else {
        $inventory = $conn->query("SELECT id, item_name, stock_qty, cost_per_unit FROM inventory")->fetch_all(MYSQLI_ASSOC);
        $stmt = $conn->prepare("INSERT INTO inventory_snapshots (snapshot_date, snapshot_type, period_type, item_id, item_name, quantity, cost_per_unit, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $count = 0;
        foreach ($inventory as $item) {
            $item_id = $item['id'];
            $item_name = $item['item_name'];
            $qty = (float)($item['stock_qty'] ?? 0);
            $cost = (float)($item['cost_per_unit'] ?? 0);
            
            $stmt->bind_param('ssissddi', $snapshot_date, $snapshot_type, $period_type, $item_id, $item_name, $qty, $cost, $user_id);
            $stmt->execute();
            $count++;
        }
        $stmt->close();
        
        $msg = ucfirst($snapshot_type) . " snapshot created! Captured $count items.";
    }
}

// Fetch available snapshot dates
$snapshot_dates = $conn->query("SELECT DISTINCT snapshot_date, snapshot_type FROM inventory_snapshots ORDER BY snapshot_date DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);

// Get beginning and ending for comparison (latest available)
$selected_date = $_GET['date'] ?? null;
if (!$selected_date && !empty($snapshot_dates)) {
    $selected_date = $snapshot_dates[0]['snapshot_date'];
}

$beginning_inventory = [];
$ending_inventory = [];
if ($selected_date) {
    $stmt_b = $conn->prepare("SELECT * FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'beginning' ORDER BY item_name");
    $stmt_b->bind_param('s', $selected_date);
    $stmt_b->execute();
    $beginning_inventory = $stmt_b->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_b->close();
    
    $stmt_e = $conn->prepare("SELECT * FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'ending' ORDER BY item_name");
    $stmt_e->bind_param('s', $selected_date);
    $stmt_e->execute();
    $ending_inventory = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_e->close();
}

// Check if today's snapshots exist
$today = date('Y-m-d');
$stmt_check = $conn->prepare("SELECT COUNT(*) as c FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'beginning'");
$stmt_check->bind_param('s', $today);
$stmt_check->execute();
$check_beginning = $stmt_check->get_result()->fetch_assoc()['c'];
$stmt_check->close();

$stmt_check2 = $conn->prepare("SELECT COUNT(*) as c FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'ending'");
$stmt_check2->bind_param('s', $today);
$stmt_check2->execute();
$check_ending = $stmt_check2->get_result()->fetch_assoc()['c'];
$stmt_check2->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Beginning & Ending Inventory - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
<script>
(function(){
    if (localStorage.getItem('sidebarCollapsed') === 'true') {
        document.documentElement.classList.add('sidebar-will-collapse');
    }
})();
</script>
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Beginning & Ending Inventory</h1>
    </div>

    <?php if ($msg): ?><div class="alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <?php if ($err): ?><div class="alert-error">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>

    <div class="snapshot-card">
        <h2>Quick Actions for Today (<?= date('M d, Y') ?>)</h2>
        <div class="quick-actions">
            <?php if ($check_beginning == 0): ?>
                <a href="?auto_snapshot&type=beginning" class="btn-primary">Capture Beginning Inventory</a>
            <?php else: ?>
                <span class="badge badge-success">✅ Beginning snapshot exists</span>
            <?php endif; ?>
            
            <?php if ($check_ending == 0): ?>
                <a href="?auto_snapshot&type=ending" class="btn-primary">Capture Ending Inventory</a>
            <?php else: ?>
                <span class="badge badge-success">✅ Ending snapshot exists</span>
            <?php endif; ?>
        </div>
    </div>

    <div class="snapshot-card">
        <h2>Create Manual Snapshot</h2>
        <form method="POST" class="display-flex gap-10 align-items-end flex-wrap">
            <div>
                <label>Date</label>
                <input type="date" name="snapshot_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div>
                <label>Type</label>
                <select name="snapshot_type" required>
                    <option value="beginning">Beginning Inventory</option>
                    <option value="ending">Ending Inventory</option>
                    <option value="periodic">Periodic Count</option>
                </select>
            </div>
            <div>
                <label>Period</label>
                <select name="period_type" required>
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                    <option value="monthly">Monthly</option>
                </select>
            </div>
            <button type="submit" name="create_snapshot" class="btn-primary">Create Snapshot</button>
        </form>
    </div>

    <div class="snapshot-card">
        <h2>View Snapshots</h2>
        <label>Select Date:</label>
        <select onchange="window.location='?date='+this.value" class="select-standard">
            <option value="">-- Select Date --</option>
            <?php foreach ($snapshot_dates as $sd): ?>
                <option value="<?= $sd['snapshot_date'] ?>" <?= $selected_date == $sd['snapshot_date'] ? 'selected' : '' ?>>
                    <?= date('M d, Y', strtotime($sd['snapshot_date'])) ?> (<?= ucfirst($sd['snapshot_type']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($selected_date): ?>
            <div class="comparison-grid">
                <div>
                    <h3>Beginning Inventory</h3>
                    <?php if (empty($beginning_inventory)): ?>
                        <p class="empty-state-message">No beginning inventory snapshot for this date</p>
                    <?php else: ?>
                        <table class="data-table table-small-text">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Cost</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_beginning = 0;
                                foreach ($beginning_inventory as $item): 
                                    $total_beginning += $item['total_value'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= number_format($item['quantity'], 2) ?></td>
                                        <td>₱<?= number_format($item['cost_per_unit'], 2) ?></td>
                                        <td>₱<?= number_format($item['total_value'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-row-total">
                                    <td colspan="3">TOTAL</td>
                                    <td>₱<?= number_format($total_beginning, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div>
                    <h3>Ending Inventory</h3>
                    <?php if (empty($ending_inventory)): ?>
                        <p class="empty-state-message">No ending inventory snapshot for this date</p>
                    <?php else: ?>
                        <table class="data-table table-small-text">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Cost</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_ending = 0;
                                foreach ($ending_inventory as $item): 
                                    $total_ending += $item['total_value'];
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= number_format($item['quantity'], 2) ?></td>
                                        <td>₱<?= number_format($item['cost_per_unit'], 2) ?></td>
                                        <td>₱<?= number_format($item['total_value'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-row-total">
                                    <td colspan="3">TOTAL</td>
                                    <td>₱<?= number_format($total_ending, 2) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($beginning_inventory) && !empty($ending_inventory)): ?>
                <div class="info-summary-box">
                    <h3>Summary for <?= date('M d, Y', strtotime($selected_date)) ?></h3>
                    <p><strong>Beginning Inventory Value:</strong> ₱<?= number_format($total_beginning ?? 0, 2) ?></p>
                    <p><strong>Ending Inventory Value:</strong> ₱<?= number_format($total_ending ?? 0, 2) ?></p>
                    <p><strong>Change:</strong> 
                        <span class="color-conditional" style="color:<?= ($total_ending - $total_beginning) < 0 ? 'red' : 'green' ?>">
                            ₱<?= number_format($total_ending - $total_beginning, 2) ?>
                            (<?= number_format((($total_ending - $total_beginning) / max($total_beginning, 1)) * 100, 1) ?>%)
                        </span>
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
