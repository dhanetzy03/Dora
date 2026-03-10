<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}

// admin.php is a legacy entry point — redirect to the proper dashboard
header("Location: dashboard.php");
exit();


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

    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
    <link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
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
