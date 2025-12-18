<?php
session_start();
if (!isset($_SESSION["username"]) || !in_array($_SESSION['role'], ['admin'])) {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

$msg = '';
$err = '';

// Fetch product list and inventory list for selection
$products = $conn->query("SELECT p.product_id, p.product_name, COALESCE(s.quantity,0) as stock_qty FROM products p LEFT JOIN stock s ON p.product_id = s.product_id ORDER BY product_name ASC")->fetch_all(MYSQLI_ASSOC);
$inventory = $conn->query("SELECT id, item_name, stock_qty, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);

// Handle spoilage submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_spoilage'])) {
    $type = $_POST['type'] ?? 'product'; // 'product' or 'inventory'
    $qty = (float)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? 'Spoilage');
    $user_id = $_SESSION['user_id'] ?? null;

    if ($qty <= 0) {
        $err = 'Please enter a valid quantity.';
    } else {
        $reference_number = 'SPOIL-' . time();

        if ($type === 'product') {
            $product_id = (int)($_POST['product_id'] ?? 0);
            if ($product_id <= 0) { $err = 'Select a product.'; }
            else {
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
                    // create stock row with zero after spoilage
                    $new_qty = 0;
                    $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                    $ins->bind_param('id', $product_id, $new_qty);
                    $ins->execute();
                    $ins->close();
                    $prev_qty = 0;
                }

                // Insert stock_movements row
                $movement_type = 'out';
                $reference_type = 'spoilage';
                // Attempt to use product price as unit cost if available
                $unit_cost = 0.0;
                $p = $conn->prepare("SELECT price FROM products WHERE product_id = ? LIMIT 1");
                $p->bind_param('i', $product_id);
                $p->execute();
                $pinfo = $p->get_result()->fetch_assoc();
                $p->close();
                if (!empty($pinfo['price'])) $unit_cost = (float)$pinfo['price'];

                $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt_mov->bind_param('isiiissids', $product_id, $movement_type, $qty, $prev_qty, $new_qty, $reference_type, $reason, $user_id, $unit_cost, $reference_number);
                $stmt_mov->execute();
                $stmt_mov->close();

                $msg = 'Spoilage recorded for product.';
            }

        } else { // inventory item
            $item_id = (int)($_POST['inventory_id'] ?? 0);
            if ($item_id <= 0) { $err = 'Select an inventory item.'; }
            else {
                $q = $conn->prepare("SELECT stock_qty, cost_per_unit FROM inventory WHERE id = ? LIMIT 1");
                $q->bind_param('i', $item_id);
                $q->execute();
                $idata = $q->get_result()->fetch_assoc();
                $q->close();
                $prev = (float)($idata['stock_qty'] ?? 0);
                $unit_cost = (float)($idata['cost_per_unit'] ?? 0);
                $new = max(0, $prev - $qty);
                $u = $conn->prepare("UPDATE inventory SET stock_qty = ?, last_updated = NOW() WHERE id = ?");
                $u->bind_param('di', $new, $item_id);
                $u->execute();
                $u->close();

                // Insert into stock_movements (use inventory id as product_id field)
                $movement_type = 'out';
                $reference_type = 'spoilage';
                $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt_mov->bind_param('isiiissids', $item_id, $movement_type, $qty, $prev, $new, $reference_type, $reason, $user_id, $unit_cost, $reference_number);
                $stmt_mov->execute();
                $stmt_mov->close();

                $msg = 'Spoilage recorded for inventory item.';
            }
        }
    }
}

// Render page
?><!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Spoilage - Admin</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>body{font-family:Segoe UI,Arial;margin:18px} .card{background:#fff;padding:18px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.06)} label{display:block;margin:8px 0 4px} input,select,textarea{width:100%;padding:8px;border:1px solid #ddd;border-radius:6px} .row{display:flex;gap:12px} .col{flex:1} .actions{display:flex;gap:8px;justify-content:flex-end;margin-top:12px} .alert{padding:10px;border-radius:6px;margin-bottom:12px} .alert-success{background:#e6ffed;border:1px solid #b7f0c6} .alert-error{background:#ffe6e6;border:1px solid #f0b7b7}</style>
    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Record Spoilage</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="content-card">
    <div class="card">
        <?php if ($msg): ?><div class="alert alert-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="alert alert-error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
        <form method="post">
            <label>Type</label>
            <select name="type" id="typeSelect" onchange="toggleType()">
                <option value="product">Product</option>
                <option value="inventory">Inventory Item</option>
            </select>

            <div id="productBlock">
                <label>Product</label>
                <select name="product_id" id="productSelect" onchange="updatePreview()">
                    <option value="">-- Select Product --</option>
                    <?php foreach ($products as $p): ?>
                        <option value="<?= $p['product_id'] ?>" data-stock="<?= (float)$p['stock_qty'] ?>"><?= htmlspecialchars($p['product_name']) ?> (Current: <?= number_format($p['stock_qty'],2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="inventoryBlock" style="display:none">
                <label>Inventory Item</label>
                <select name="inventory_id" id="inventorySelect" onchange="updatePreview()">
                    <option value="">-- Select Item --</option>
                    <?php foreach ($inventory as $it): ?>
                        <option value="<?= $it['id'] ?>" data-stock="<?= (float)($it['stock_qty'] ?? 0) ?>" data-cost="<?= (float)($it['cost_per_unit'] ?? 0) ?>"><?= htmlspecialchars($it['item_name']) ?> (Current: <?= number_format($it['stock_qty'] ?? 0,2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <label>Quantity</label>
            <input type="number" step="0.01" name="quantity" id="spoilQty" required oninput="updatePreview()">

            <div style="margin-top:8px;font-weight:600">Preview: <span id="previewText">No selection</span></div>

            <label>Reason / Remarks</label>
            <textarea name="reason" rows="2">Spoilage</textarea>

            <div class="actions">
                <button type="button" onclick="window.location='raw_materials.php'">Back</button>
                <button type="submit" name="record_spoilage">Record Spoilage</button>
            </div>
        </form>
    </div>
    </div>

<script>
function toggleType(){
    var t = document.getElementById('typeSelect').value;
    document.getElementById('productBlock').style.display = (t==='product')?'block':'none';
    document.getElementById('inventoryBlock').style.display = (t==='inventory')?'block':'none';
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
        out.innerText = 'Current ' + stock.toFixed(2) + ' → After spoilage ' + after;
    } else {
        var sel = document.getElementById('inventorySelect');
        var opt = sel.options[sel.selectedIndex];
        if (!opt || !opt.value) { out.innerText = 'Select inventory item'; return; }
        var stock = parseFloat(opt.getAttribute('data-stock') || 0);
        var after = Math.max(0, stock - qty).toFixed(2);
        out.innerText = 'Current ' + stock.toFixed(2) + ' → After spoilage ' + after;
    }
}

toggleType();
</script>
</body>
</html>
