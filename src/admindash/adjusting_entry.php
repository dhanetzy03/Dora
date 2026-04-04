<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int)($_POST['item_id'] ?? 0);
    $adjustment_type = trim($_POST['adjustment_type'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? 'ADJ-' . date('YmdHis'));

    if ($item_id <= 0 || !in_array($adjustment_type, ['in', 'out']) || $quantity <= 0 || $reason === '') {
        $_SESSION['error'] = 'All fields are required and quantity must be positive.';
        header('Location: adjusting_entry.php');
        exit();
    }

    // Get current stock and name
    $stmt = $conn->prepare("SELECT stock_qty, item_name, stock_in, stock_out FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $current_stock = (int)$item['stock_qty'];
    $current_stock_in = (int)($item['stock_in'] ?? 0);
    $current_stock_out = (int)($item['stock_out'] ?? 0);
    $stmt->close();

    // Calculate new stock
    if ($adjustment_type === 'in') {
        $new_stock = $current_stock + $quantity;
        $new_stock_in = $current_stock_in + (int)$quantity;
        $new_stock_out = $current_stock_out;
        $movement_qty = $quantity;
    } else {
        $new_stock = $current_stock - $quantity;
        $new_stock_in = $current_stock_in;
        $new_stock_out = $current_stock_out + (int)$quantity;
        $movement_qty = $quantity;
    }

    if ($new_stock < 0) {
        $_SESSION['error'] = 'Cannot reduce stock below zero.';
        header('Location: adjusting_entry.php');
        exit();
    }

    // Start transaction
    db_begin_transaction();
    
    try {
        // Update inventory
        $stmt = $conn->prepare("UPDATE inventory SET stock_qty = ?, stock_in = ?, stock_out = ?, last_updated = NOW() WHERE id = ?");
        $stmt->bind_param("iiii", $new_stock, $new_stock_in, $new_stock_out, $item_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update inventory');
        }
        $stmt->close();

        // Sync to products.stock if a product with same name exists
        if (!empty($item['item_name'])) {
            $prodStmt = $conn->prepare("SELECT product_id FROM products WHERE LOWER(product_name) = LOWER(?) LIMIT 1");
            $prodStmt->bind_param("s", $item['item_name']);
            $prodStmt->execute();
            $prodRes = $prodStmt->get_result();
            if ($prodRes && $prodRes->num_rows > 0) {
                $prodId = $prodRes->fetch_assoc()['product_id'];
                $prodStmt->close();
                // upsert into stock
                $s = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
                $s->bind_param("i", $prodId);
                $s->execute();
                $sr = $s->get_result();
                if ($sr && $sr->num_rows > 0) {
                    $sid = $sr->fetch_assoc()['stock_id'];
                    $s->close();
                    $u = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                    $u->bind_param("di", $new_stock, $sid);
                    if (!$u->execute()) {
                        throw new Exception('Failed to sync stock quantity');
                    }
                    $u->close();
                } else {
                    $s->close();
                    $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                    $ins->bind_param("id", $prodId, $new_stock);
                    if (!$ins->execute()) {
                        throw new Exception('Failed to create stock entry');
                    }
                    $ins->close();
                }
            } else {
                $prodStmt->close();
            }
        }

        // Insert stock movement
        $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, reference_number, remarks, created_by) VALUES (?, ?, ?, ?, ?, 'adjustment', ?, ?, ?)");
        $stmt->bind_param("isddsssi", $item_id, $adjustment_type, $movement_qty, $current_stock, $new_stock, $reference_number, $reason, $_SESSION['user_id']);
        if (!$stmt->execute()) {
            throw new Exception('Failed to create stock movement');
        }
        $stmt->close();

        db_commit();
        $_SESSION['success'] = 'Stock adjustment recorded successfully.';
        header('Location: adjusting_entry.php');
        exit();
    } catch (Exception $e) {
        db_rollback();
        $_SESSION['error'] = 'Database operation failed: ' . $e->getMessage();
        header('Location: adjusting_entry.php');
        exit();
    }
}

// Fetch inventory items for dropdown
$inventory = [];
$stmt = $conn->prepare("SELECT id, item_name, stock_qty FROM inventory ORDER BY item_name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $inventory[] = $row;
}
$stmt->close();

$page_title = "Adjusting Entry";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($page_title) ?> - Shukran Café</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
    <link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
</head>
<body class="shukran-admin">
<?php include 'sidebar.php'; ?>
<div class="main-content">
    <div class="content-header">
        <h1>Stock Adjusting Entry</h1>
        <p>Manually adjust inventory stock levels</p>
    </div>

    <div class="content-card">
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($_SESSION['error']) ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($_SESSION['success']) ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <form method="POST" class="form-grid">
            <div class="form-group">
                <label for="item_id">Select Item</label>
                <select name="item_id" id="item_id" required>
                    <option value="">Choose an item...</option>
                    <?php foreach ($inventory as $item): ?>
                        <option value="<?= $item['id'] ?>" data-current-stock="<?= $item['stock_qty'] ?>">
                            <?= htmlspecialchars($item['item_name']) ?> (Current: <?= $item['stock_qty'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="currentStockDisplay" class="margin-top-8 font-size-14 color-333">Current stock: <strong id="currentStockValue">-</strong></div>
            </div>

            <div class="form-group">
                <label for="adjustment_type">Adjustment Type</label>
                <select name="adjustment_type" id="adjustment_type" required>
                    <option value="">Select type...</option>
                    <option value="in">Stock In (+)</option>
                    <option value="out">Stock Out (-)</option>
                </select>
            </div>

            <div class="form-group">
                <label for="quantity">Quantity</label>
                <input type="number" name="quantity" id="quantity" step="0.01" min="0.01" required>
            </div>

            <div class="form-group">
                <label for="reason">Reason</label>
                <textarea name="reason" id="reason" rows="3" placeholder="Enter reason for adjustment..." required></textarea>
            </div>

            <div class="form-group">
                <label for="reference_number">Reference Number (Optional)</label>
                <input type="text" name="reference_number" id="reference_number" value="ADJ-<?= date('YmdHis') ?>">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Record Adjustment</button>
                <a href="inventory.php" class="btn btn-secondary">Back to Inventory</a>
            </div>
        </form>
    </div>
</div>

<script>
// Optional: Update current stock display when item changes
document.getElementById('item_id').addEventListener('change', function() {
    const selected = this.options[this.selectedIndex];
    const currentStock = selected.getAttribute('data-current-stock');
    // Could add display logic here if needed
});

// Populate current stock display on load and when item changes
function updateCurrentStockDisplay() {
    const sel = document.getElementById('item_id');
    if (!sel) return;
    const opt = sel.options[sel.selectedIndex];
    const val = opt ? opt.getAttribute('data-current-stock') : null;
    const el = document.getElementById('currentStockValue');
    if (el) el.innerText = (val !== null && val !== undefined && val !== '') ? val : '-';
}
document.getElementById('item_id').addEventListener('change', updateCurrentStockDisplay);
window.addEventListener('DOMContentLoaded', updateCurrentStockDisplay);
</script>

</body>
</html></content>
<parameter name="filePath">c:\xampp\htdocs\f4\Dora\src\admindash\adjusting_entry.php