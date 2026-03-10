<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// helper
function back($key, $msg) {
    $_SESSION[$key] = $msg;
    header('Location: inventory.php');
    exit();
}

$id = isset($_REQUEST['id']) ? (int)$_REQUEST['id'] : 0;
if ($id <= 0) {
    back('error', 'Invalid item id.');
}

// Fetch existing item
$stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    back('error', 'Item not found.');
}
$item = $res->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name'] ?? $item['item_name']);
    $category = trim($_POST['category'] ?? $item['category']);
    $unit = trim($_POST['unit'] ?? $item['unit']);
    $stock_qty = isset($_POST['stock_qty']) ? (int)$_POST['stock_qty'] : (int)$item['stock_qty'];
    $reorder_input = isset($_POST['reorder_level']) ? trim($_POST['reorder_level']) : '';
    // If empty, keep existing reorder level (reorder required only for new items)
    $reorder_level = ($reorder_input === '') ? $item['reorder_level'] : (int)$reorder_input;

    // Determine status
    if ($stock_qty <= 0) {
        $status = 'Out of Stock';
    } elseif ($reorder_level !== null && $reorder_level !== '' && $stock_qty <= $reorder_level) {
        $status = 'Low Stock';
    } else {
        $status = 'Sufficient';
    }

    // Update stock_in/out logic: if stock increased, add to stock_in; if decreased, increment stock_out
    $prev_qty = (int)$item['stock_qty'];
    $stock_in = (int)$item['stock_in'];
    $stock_out = (int)$item['stock_out'];
    if ($stock_qty > $prev_qty) {
        $stock_in += ($stock_qty - $prev_qty);
    } elseif ($stock_qty < $prev_qty) {
        $stock_out += ($prev_qty - $stock_qty);
    }

    $u = $conn->prepare("UPDATE inventory SET item_name=?, category=?, stock_qty=?, reorder_level=?, status=?, unit=?, stock_in=?, stock_out=?, last_updated=NOW() WHERE id=?");
    // types: item_name (s), category (s), stock_qty (i), reorder_level (i), status (s), unit (s), stock_in (i), stock_out (i), id (i)
    $u->bind_param('ssiissiii', $item_name, $category, $stock_qty, $reorder_level, $status, $unit, $stock_in, $stock_out, $id);
    if ($u->execute()) {
        back('success', 'Item updated successfully.');
    } else {
        back('error', 'Failed to update item.');
    }
}

// Show edit form (simple page)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Edit Item</title>

    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
    <link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">

</head>
<body class="shukran-admin">
<?php include 'sidebar.php'; ?>
<div class="page-container">
    <div class="card">
        <h2>Edit Item</h2>
        <form method="POST" action="">
            <label>Item Name *</label>
            <input type="text" name="item_name" required value="<?= htmlspecialchars($item['item_name']) ?>">
            <label>Category *</label>
            <input type="text" name="category" required value="<?= htmlspecialchars($item['category']) ?>">
            <label>Unit</label>
            <input type="text" name="unit" value="<?= htmlspecialchars($item['unit']) ?>">
            <label>Stock Quantity *</label>
            <input type="number" name="stock_qty" required value="<?= htmlspecialchars($item['stock_qty']) ?>">
            <label>Reorder Level (leave empty to keep existing)</label>
            <input type="number" name="reorder_level" placeholder="Current: <?= htmlspecialchars($item['reorder_level']) ?>">
            <div class="margin-top-14">
                <a href="inventory.php" class="btn-secondary">Cancel</a>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
</body>
</html>
