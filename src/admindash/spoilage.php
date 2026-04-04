<?php
session_start();
if (!isset($_SESSION["username"]) || !in_array($_SESSION['role'], ['admin'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

$msg = '';
$err = '';

// Load session messages if exist
if (isset($_SESSION['success'])) {
    $msg = $_SESSION['success'];
    unset($_SESSION['success']);
}
if (isset($_SESSION['error'])) {
    $err = $_SESSION['error'];
    unset($_SESSION['error']);
}

// Fetch product list and inventory list for selection
$products = $conn->query("SELECT p.product_id, p.product_name, COALESCE(s.quantity,0) as stock_qty FROM products p LEFT JOIN stock s ON p.product_id = s.product_id ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);
$inventory = $conn->query("SELECT id, item_name, stock_qty, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);

// Handle spoilage deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    if ($delete_id > 0) {
        $stmt = $conn->prepare("DELETE FROM spoilage_records WHERE spoilage_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $delete_id);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Spoilage record deleted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to delete spoilage record.';
            }
            $stmt->close();
        }
    }
    header('Location: spoilage.php');
    exit();
}

// Handle spoilage submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_spoilage'])) {
    $type = $_POST['type'] ?? 'inventory'; // 'product' or 'inventory'
    $qty = (float)($_POST['quantity'] ?? 0);
    $reason_enum = trim($_POST['reason_enum'] ?? 'expired');
    $reason_details = trim($_POST['reason_details'] ?? '');
    $date_spoiled = !empty($_POST['date_spoiled']) ? $_POST['date_spoiled'] : date('Y-m-d');
    $user_id = $_SESSION['user_id'] ?? null;

    if ($qty <= 0) {
        $err = 'Please enter a valid quantity.';
    } else {
        $reference_number = 'SPOIL-' . date('Ymd') . '-' . rand(1000, 9999);

        if ($type === 'product') {
            $product_id = (int)($_POST['product_id'] ?? 0);
            if ($product_id <= 0) { $err = 'Select a product.'; }
            else {
                // Get product info
                $p = $conn->prepare("SELECT product_name, unit, price FROM products WHERE product_id = ? LIMIT 1");
                $p->bind_param('i', $product_id);
                $p->execute();
                $pinfo = $p->get_result()->fetch_assoc();
                $p->close();
                $product_name = $pinfo['product_name'] ?? 'Unknown Product';
                $unit = $pinfo['unit'] ?? 'pcs';
                $unit_cost = (float)($pinfo['price'] ?? 0);
                
                // Ensure there is a stock row for this product
                $s = $conn->prepare("SELECT stock_id, quantity FROM stock WHERE product_id = ? LIMIT 1");
                $s->bind_param('i', $product_id);
                $s->execute();
                $res = $s->get_result();
                $prev_qty = 0;
                if ($row = $res->fetch_assoc()) {
                    $stock_id = $row['stock_id'];
                    $prev_qty = (float)$row['quantity'];
                    $s->close();
                    $new_qty = max(0, $prev_qty - $qty);
                    $u = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                    $u->bind_param('di', $new_qty, $stock_id);
                    $u->execute();
                    $u->close();
                } else {
                    $s->close();
                    $new_qty = 0;
                    $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                    $ins->bind_param('id', $product_id, $new_qty);
                    $ins->execute();
                    $ins->close();
                    $prev_qty = 0;
                }

                // Insert into spoilage_records table
                $stmt_spoil = $conn->prepare("INSERT INTO spoilage_records (item_type, item_id, item_name, quantity_spoiled, unit, cost_per_unit, spoilage_reason, reason_details, date_spoiled, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_spoil->bind_param('sissdssssi', $type, $product_id, $product_name, $qty, $unit, $unit_cost, $reason_enum, $reason_details, $date_spoiled, $user_id);
                $stmt_spoil->execute();
                $stmt_spoil->close();

                // Insert stock_movements row
                $movement_type = 'out';
                $reference_type = 'spoilage';
                $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt_mov->bind_param('isddssisds', $product_id, $movement_type, $qty, $prev_qty, $new_qty, $reference_type, $reason_details, $user_id, $unit_cost, $reference_number);
                $stmt_mov->execute();
                $stmt_mov->close();

                $msg = 'Spoilage recorded for product: ' . $product_name;
            }

        } else { // inventory item
            $item_id = (int)($_POST['inventory_id'] ?? 0);
            if ($item_id <= 0) { $err = 'Select an inventory item.'; }
            else {
                $q = $conn->prepare("SELECT item_name, stock_qty, stock_in, stock_out, cost_per_unit, unit FROM inventory WHERE id = ? LIMIT 1");
                $q->bind_param('i', $item_id);
                $q->execute();
                $idata = $q->get_result()->fetch_assoc();
                $q->close();
                $item_name = $idata['item_name'] ?? 'Unknown Item';
                $prev = (float)($idata['stock_qty'] ?? 0);
                $unit_cost = (float)($idata['cost_per_unit'] ?? 0);
                $unit = $idata['unit'] ?? 'pcs';
                $new = max(0, $prev - $qty);
                $new_stock_out = ((int)($idata['stock_out'] ?? 0)) + (int)$qty;
                
                // Update inventory with stock_out increment
                $u = $conn->prepare("UPDATE inventory SET stock_qty = ?, stock_out = ?, last_updated = NOW() WHERE id = ?");
                $u->bind_param('dii', $new, $new_stock_out, $item_id);
                $u->execute();
                $u->close();

                // Insert into spoilage_records table
                $stmt_spoil = $conn->prepare("INSERT INTO spoilage_records (item_type, item_id, item_name, quantity_spoiled, unit, cost_per_unit, spoilage_reason, reason_details, date_spoiled, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_spoil->bind_param('sissdssssi', $type, $item_id, $item_name, $qty, $unit, $unit_cost, $reason_enum, $reason_details, $date_spoiled, $user_id);
                $stmt_spoil->execute();
                $stmt_spoil->close();

                // Insert into stock_movements (use inventory id as product_id field)
                $movement_type = 'out';
                $reference_type = 'spoilage';
                $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt_mov->bind_param('isddssisds', $item_id, $movement_type, $qty, $prev, $new, $reference_type, $reason_details, $user_id, $unit_cost, $reference_number);
                $stmt_mov->execute();
                $stmt_mov->close();

                $msg = 'Spoilage recorded for inventory item: ' . $item_name;
            }
        }
    }
}

// Fetch recent spoilage records
$spoilage_records = $conn->query("SELECT s.*, u.username FROM spoilage_records s LEFT JOIN users u ON s.recorded_by = u.user_id ORDER BY s.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);

// Render page
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Spoilage - Admin</title>
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
        <h1>Record Spoilage</h1>
    </div>

    <div class="content-card">
    <div class="card">
        <h2>Record Spoilage</h2>
        <?php if ($msg): ?><div class="alert alert-success">✅ <?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error">⚠️ <?= htmlspecialchars($err) ?></div><?php endif; ?>
        <form method="post">
            <label>Type</label>
            <select name="type" id="typeSelect" onchange="toggleType()">
                <option value="inventory">Inventory Item (Raw Materials)</option>
                <option value="product">Product (Menu Items)</option>
            </select>

            <div id="productBlock" class="display-none">
                <label>Product</label>
                <select name="product_id" id="productSelect" onchange="updatePreview()">
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['product_id'] ?>" data-stock="<?= (float)$p['stock_qty'] ?>"><?= htmlspecialchars($p['product_name']) ?> (Stock: <?= number_format($p['stock_qty'],2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="inventoryBlock">
                <label>Inventory Item</label>
                <select name="inventory_id" id="inventorySelect" onchange="updatePreview()">
                    <option value="">-- Select Item --</option>
                    <?php foreach ($inventory as $it): ?>
                        <option value="<?= $it['id'] ?>" data-stock="<?= (float)($it['stock_qty'] ?? 0) ?>" data-cost="<?= (float)($it['cost_per_unit'] ?? 0) ?>"><?= htmlspecialchars($it['item_name']) ?> (Stock: <?= number_format($it['stock_qty'] ?? 0,2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="row">
                <div class="col">
                    <label>Quantity Spoiled *</label>
                    <input type="number" step="0.01" name="quantity" id="spoilQty" required oninput="updatePreview()">
                </div>
                <div class="col">
                    <label>Date Spoiled *</label>
                    <input type="date" name="date_spoiled" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>

            <label>Spoilage Reason *</label>
            <select name="reason_enum" required>
                <option value="expired">Expired / Past Shelf Life</option>
                <option value="damaged">Damaged / Broken</option>
                <option value="contaminated">Contaminated</option>
                <option value="overstock">Overstock / Excess</option>
                <option value="other">Other</option>
            </select>

            <div class="info-box">
                Preview: <span id="previewText">Select an item</span>
            </div>

            <label>Additional Details / Remarks</label>
            <textarea name="reason_details" rows="2" placeholder="Additional information about the spoilage..."></textarea>

            <div class="actions">
                <button type="button" onclick="window.location='inventory.php'" class="btn-bg-666">Back to Inventory</button>
                <button type="submit" name="record_spoilage" class="btn-bg-red">🗑️ Record Spoilage</button>
            </div>
        </form>
    </div>

    <!-- Recent Spoilage Records -->
    <div class="card margin-top-20">
        <h2>Recent Spoilage Records</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item Name</th>
                        <th>Type</th>
                        <th>Qty Spoiled</th>
                        <th>Unit Cost</th>
                        <th>Total Loss</th>
                        <th>Reason</th>
                        <th>Details</th>
                        <th>Recorded By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($spoilage_records)): ?>
                        <tr><td colspan="10" class="td-center-gray">No spoilage records found</td></tr>
                    <?php else: ?>
                        <?php foreach ($spoilage_records as $rec): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($rec['date_spoiled'])) ?></td>
                                <td><?= htmlspecialchars($rec['item_name']) ?></td>
                                <td><span class="badge badge-info"><?= ucfirst($rec['item_type']) ?></span></td>
                                <td><?= number_format($rec['quantity_spoiled'], 2) ?> <?= htmlspecialchars($rec['unit']) ?></td>
                                <td>₱<?= number_format($rec['cost_per_unit'], 2) ?></td>
                                <td><strong class="loss-amount">₱<?= number_format($rec['total_loss'], 2) ?></strong></td>
                                <td><span class="badge badge-warning"><?= ucfirst($rec['spoilage_reason']) ?></span></td>
                                <td><?= htmlspecialchars($rec['reason_details'] ?: '-') ?></td>
                                <td><?= htmlspecialchars($rec['username'] ?? 'System') ?></td>
                                <td><a href="spoilage.php?delete_id=<?= $rec['spoilage_id'] ?>" class="btn-sm btn-danger" onclick="return confirm('Delete this spoilage record?');">Delete</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>

<script>
function toggleType(){
    var t = document.getElementById('typeSelect').value;
    document.getElementById('productBlock').style.display = (t==='product')?'block':'none';
    document.getElementById('inventoryBlock').style.display = (t==='inventory')?'block':'none';
    updatePreview();
}

function updatePreview(){
    var t = document.getElementById('typeSelect').value;
    var qty = parseFloat(document.getElementById('spoilQty').value) || 0;
    var out = document.getElementById('previewText');
    if (t === 'product'){
        var sel = document.getElementById('productSelect');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) { out.innerText = 'Select a product'; return; }
        var stock = parseFloat(opt.getAttribute('data-stock') || 0);
        var after = Math.max(0, stock - qty).toFixed(2);
        out.innerHTML = '<strong class="loss-amount">Current: ' + stock.toFixed(2) + ' → After spoilage: ' + after + '</strong>';
    } else {
        var sel = document.getElementById('inventorySelect');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) { out.innerText = 'Select inventory item'; return; }
        var stock = parseFloat(opt.getAttribute('data-stock') || 0);
        var cost = parseFloat(opt.getAttribute('data-cost') || 0);
        var after = Math.max(0, stock - qty).toFixed(2);
        var loss = (qty * cost).toFixed(2);
        out.innerHTML = '<strong class="loss-amount">Current: ' + stock.toFixed(2) + ' → After: ' + after + ' | Loss: ₱' + loss + '</strong>';
    }
}

toggleType();
</script>
</body>
</html>
