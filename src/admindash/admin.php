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
<style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: #f5f6fa;
        margin: 0;
        display: flex;
        height: 100vh;
    }
    .sidebar {
        width: 220px;
        background-color: #fff;
        border-right: 1px solid #ddd;
        padding: 20px 0;
    }
    .sidebar ul { list-style: none; padding: 0; }
    .sidebar li {
        padding: 12px 20px;
        margin-bottom: 5px;
        cursor: pointer;
        color: #555;
    }
    .sidebar li.active {
        background-color: #e9efff;
        color: #0066ff;
        font-weight: 600;
    }
    .sidebar li:hover { background-color: #f1f3f8; }
    .main {
        flex: 1;
        padding: 40px;
        overflow-y: auto;
    }
    h1 { font-size: 20px; margin-bottom: 20px; }
    .dashboard-cards {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
    }
    .card {
        flex: 1;
        background-color: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .card h3 { font-size: 14px; color: #666; margin: 0; }
    .card p { font-size: 28px; font-weight: 600; margin: 10px 0 0; }
    .critical { color: red; }
    .recent { color: green; }
    .inventory-header { display: flex; justify-content: space-between; margin: 20px 0 10px; }
    table {
        width: 100%;
        border-collapse: collapse;
        background-color: #fff;
        border-radius: 10px;
        overflow: hidden;
    }
    th, td {
        padding: 14px 18px;
        border-bottom: 1px solid #eee;
        text-align: left;
    }
    th {
        background-color: #fafafa;
        color: #444;
        font-weight: 600;
    }
    .status {
        padding: 6px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    .sufficient { background-color: #d4f8d4; color: #1e7a1e; }
    .low { background-color: #fff4cc; color: #b58900; }
    .out { background-color: #ffd8d8; color: #a30000; }
    form {
        background-color: #fff;
        padding: 20px;
        margin-bottom: 30px;
        border-radius: 10px;
    }
    form input, form select {
        padding: 8px;
        margin-right: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
    form button {
        padding: 8px 14px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }
    form button:hover { background-color: #218838; }
    .error {
        background-color: #ffe0e0;
        color: #a10000;
        padding: 10px 14px;
        border-radius: 8px;
        margin-bottom: 15px;
        font-weight: 500;
    }
    .logout-btn {
        position: absolute;
        top: 20px;
        right: 20px;
        background-color: #dc3545;
        color: white;
        padding: 8px 14px;
        text-decoration: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
    }
    .logout-btn:hover { background-color: #c82333; }
</style>
</head>
<body>

<div class="sidebar">
    <ul>
        <li class="active">📊 Dashboard</li>
        <li>📦 Inventory</li>
        <li>💰 Sales</li>
        <li>⚙️ Settings</li>
    </ul>
</div>

<div class="main">
    <a href="../auth/logout.php" class="logout-btn">Logout</a>
    <h1>A Web-Based Inventory Tracking System with Enhanced Stock Monitoring and Sales Validation for Shukran Café</h1>

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
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['stock_qty']) ?></td>
                    <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                    <td><?= htmlspecialchars($item['stock_in'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($item['stock_out'] ?? 0) ?></td>
                    <td><?= htmlspecialchars($item['stock_qty']) ?></td>
                    <td>
                        <?php
                            $status = strtolower(str_replace(' ', '', $item['status']));
                            $class = $status === 'sufficient' ? 'sufficient' : ($status === 'lowstock' ? 'low' : 'out');
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
