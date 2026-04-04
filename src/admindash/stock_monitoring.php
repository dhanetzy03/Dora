<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";

function sync_inventory_row_to_raw_materials(mysqli $conn, int $inventoryId): void {
    $stmt = $conn->prepare("SELECT id, item_code, item_name, category, unit, stock_qty, reorder_level, COALESCE(cost_per_unit,0) AS cost_per_unit FROM inventory WHERE id = ? LIMIT 1");
    if (!$stmt) return;
    $stmt->bind_param("i", $inventoryId);
    if (!$stmt->execute()) {
        $stmt->close();
        return;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) return;

    $itemCode = trim((string)($row['item_code'] ?? ''));
    $itemName = trim((string)($row['item_name'] ?? ''));
    $category = trim((string)($row['category'] ?? ''));
    $unit = trim((string)($row['unit'] ?? 'pcs'));
    $stockQty = (float)($row['stock_qty'] ?? 0);
    $reorderLevel = (int)($row['reorder_level'] ?? 0);
    $costPerUnit = (float)($row['cost_per_unit'] ?? 0);

    $isRaw = (strcasecmp($category, 'Raw') === 0) || (stripos($itemCode, 'RM-') === 0);
    if (!$isRaw || $itemName === '') return;

    if ($itemCode === '') {
        $itemCode = 'RM-' . strtoupper(substr(md5($itemName . '-' . $inventoryId), 0, 10));
        $u = $conn->prepare("UPDATE inventory SET item_code = ? WHERE id = ?");
        if ($u) {
            $u->bind_param("si", $itemCode, $inventoryId);
            $u->execute();
            $u->close();
        }
    }

    $find = $conn->prepare("SELECT material_id FROM raw_materials WHERE material_code = ? OR LOWER(TRIM(material_name)) = LOWER(TRIM(?)) LIMIT 1");
    if (!$find) return;
    $find->bind_param("ss", $itemCode, $itemName);
    if (!$find->execute()) {
        $find->close();
        return;
    }
    $existing = $find->get_result()->fetch_assoc();
    $find->close();

    if ($existing && isset($existing['material_id'])) {
        $materialId = (int)$existing['material_id'];
        $up = $conn->prepare("UPDATE raw_materials SET material_code = ?, material_name = ?, category = 'Raw', unit = ?, quantity = ?, cost_per_unit = ?, reorder_level = ?, last_updated = NOW() WHERE material_id = ?");
        if ($up) {
            $up->bind_param("sssddii", $itemCode, $itemName, $unit, $stockQty, $costPerUnit, $reorderLevel, $materialId);
            $up->execute();
            $up->close();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO raw_materials (material_code, material_name, category, unit, quantity, cost_per_unit, reorder_level, supplier_id, last_updated) VALUES (?, ?, 'Raw', ?, ?, ?, ?, NULL, NOW())");
        if ($ins) {
            $ins->bind_param("sssddi", $itemCode, $itemName, $unit, $stockQty, $costPerUnit, $reorderLevel);
            $ins->execute();
            $ins->close();
        }
    }
}

function ensure_product_id_for_inventory(mysqli $conn, int $inventoryId): int {
    $q = $conn->prepare("SELECT item_code, item_name, unit, reorder_level, COALESCE(cost_per_unit,0) as cost_per_unit, stock_qty FROM inventory WHERE id = ? LIMIT 1");
    if (!$q) {
        throw new Exception('Failed to prepare inventory-product mapping lookup');
    }
    $q->bind_param("i", $inventoryId);
    if (!$q->execute()) {
        $err = $q->error;
        $q->close();
        throw new Exception('Failed to execute inventory-product mapping lookup: ' . $err);
    }
    $inv = $q->get_result()->fetch_assoc();
    $q->close();
    if (!$inv) {
        throw new Exception('Inventory item not found for product mapping');
    }

    $itemCode = trim((string)($inv['item_code'] ?? ''));
    $itemName = trim((string)($inv['item_name'] ?? ''));
    $unit = trim((string)($inv['unit'] ?? 'pcs'));
    $reorderLevel = (int)($inv['reorder_level'] ?? 0);
    $price = (float)($inv['cost_per_unit'] ?? 0);
    $stockQty = (float)($inv['stock_qty'] ?? 0);

    if ($itemName === '') {
        throw new Exception('Inventory item name is empty');
    }

    if ($itemCode === '') {
        $itemCode = 'INV-' . str_pad((string)$inventoryId, 6, '0', STR_PAD_LEFT);
        $u = $conn->prepare("UPDATE inventory SET item_code = ? WHERE id = ?");
        if ($u) {
            $u->bind_param("si", $itemCode, $inventoryId);
            $u->execute();
            $u->close();
        }
    }

    $p = $conn->prepare("SELECT product_id FROM products WHERE product_code = ? OR LOWER(TRIM(product_name)) = LOWER(TRIM(?)) LIMIT 1");
    if (!$p) {
        throw new Exception('Failed to prepare product lookup');
    }
    $p->bind_param("ss", $itemCode, $itemName);
    if (!$p->execute()) {
        $err = $p->error;
        $p->close();
        throw new Exception('Failed to execute product lookup: ' . $err);
    }
    $prod = $p->get_result()->fetch_assoc();
    $p->close();

    if ($prod && isset($prod['product_id'])) {
        $productId = (int)$prod['product_id'];
        $up = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, unit = ?, reorder_level = ?, price = ?, updated_at = NOW() WHERE product_id = ?");
        if ($up) {
            $up->bind_param("sssidi", $itemCode, $itemName, $unit, $reorderLevel, $price, $productId);
            $up->execute();
            $up->close();
        }
    } else {
        $ins = $conn->prepare("INSERT INTO products (product_code, product_name, description, category_id, unit, reorder_level, price, created_at, updated_at) VALUES (?, ?, '', NULL, ?, ?, ?, NOW(), NOW())");
        if (!$ins) {
            throw new Exception('Failed to prepare product insert for mapping');
        }
        $ins->bind_param("sssid", $itemCode, $itemName, $unit, $reorderLevel, $price);
        if (!$ins->execute()) {
            $err = $ins->error;
            $ins->close();
            throw new Exception('Failed to create linked product: ' . $err);
        }
        $productId = (int)$ins->insert_id;
        $ins->close();
    }

    $s = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
    if ($s) {
        $s->bind_param("i", $productId);
        if ($s->execute()) {
            $sr = $s->get_result();
            if ($sr && $sr->num_rows > 0) {
                $sid = (int)$sr->fetch_assoc()['stock_id'];
                $s->close();
                $su = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                if ($su) {
                    $su->bind_param("di", $stockQty, $sid);
                    $su->execute();
                    $su->close();
                }
            } else {
                $s->close();
                $si = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                if ($si) {
                    $si->bind_param("id", $productId, $stockQty);
                    $si->execute();
                    $si->close();
                }
            }
        } else {
            $s->close();
        }
    }

    return $productId;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['movement_type'])) {
    $inventory_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $movement_type = trim($_POST['movement_type'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $reference_number = trim($_POST['reference_number'] ?? 'N/A');

    $created_by = $_SESSION['user_id'] ?? null;
    if (empty($created_by) && !empty($_SESSION['username'])) {
        $u = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        if ($u) {
            $uname = $_SESSION['username'];
            $u->bind_param("s", $uname);
            if ($u->execute()) {
                $ur = $u->get_result()->fetch_assoc();
                if ($ur && isset($ur['user_id'])) {
                    $created_by = (int)$ur['user_id'];
                    $_SESSION['user_id'] = $created_by;
                }
            }
            $u->close();
        }
    }

    if ($inventory_id <= 0 || $quantity <= 0 || !in_array($movement_type, ['in', 'out'], true)) {
        $_SESSION['error'] = 'Please select item, movement type, and valid quantity.';
        header('Location: stock_monitoring.php');
        exit();
    }
    if (empty($created_by)) {
        $_SESSION['error'] = 'Stock movement failed: missing user session. Please log in again.';
        header('Location: stock_monitoring.php');
        exit();
    }

    db_begin_transaction();
    try {
        $stmt_current = $conn->prepare("SELECT stock_qty, reorder_level, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory WHERE id = ? LIMIT 1");
        if (!$stmt_current) throw new Exception('Failed to prepare inventory lookup');
        $stmt_current->bind_param("i", $inventory_id);
        if (!$stmt_current->execute()) throw new Exception('Failed to fetch inventory item');
        $item_data = $stmt_current->get_result()->fetch_assoc();
        $stmt_current->close();

        if (!$item_data) throw new Exception('Selected item not found');

        $previous_quantity = (float)$item_data['stock_qty'];
        $reorder_level = (float)$item_data['reorder_level'];
        $current_unit_cost = (float)$item_data['cost_per_unit'];
        $new_quantity = $previous_quantity;

        if ($movement_type === 'in') {
            $new_quantity += $quantity;
        } else {
            if ($previous_quantity < $quantity) {
                throw new Exception('Insufficient stock for stock out movement');
            }
            $new_quantity -= $quantity;
        }

        $new_status = 'Sufficient';
        if ($new_quantity <= 0) {
            $new_status = 'Out of Stock';
        } elseif ($new_quantity <= $reorder_level) {
            $new_status = 'Low Stock';
        }

        $stmt_update = $conn->prepare("UPDATE inventory SET stock_qty = ?, status = ?, last_updated = NOW() WHERE id = ?");
        if (!$stmt_update) throw new Exception('Failed to prepare inventory update');
        $stmt_update->bind_param("dsi", $new_quantity, $new_status, $inventory_id);
        if (!$stmt_update->execute()) throw new Exception('Failed to update inventory stock');
        $stmt_update->close();

        $movement_product_id = ensure_product_id_for_inventory($conn, $inventory_id);

        $reference_type = 'adjustment';
        $stmt_log = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
        if (!$stmt_log) throw new Exception('Failed to prepare stock movement log');
        $stmt_log->bind_param("isdddssids", $movement_product_id, $movement_type, $quantity, $previous_quantity, $new_quantity, $reference_type, $remarks, $created_by, $current_unit_cost, $reference_number);
        if (!$stmt_log->execute()) {
            $logError = $stmt_log->error;
            $stmt_log->close();
            throw new Exception('Failed to log stock movement: ' . $logError);
        }
        $stmt_log->close();

        sync_inventory_row_to_raw_materials($conn, $inventory_id);
        db_commit();

        $_SESSION['success'] = 'Stock movement posted successfully.';
    } catch (Exception $e) {
        db_rollback();
        $_SESSION['error'] = 'Stock movement failed: ' . $e->getMessage();
    }

    header('Location: stock_monitoring.php');
    exit();
}

// Fetch stock movements
$movements = $conn->query("
    SELECT sm.*, COALESCE(i.item_name, p.product_name) as item_name, u.username 
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.product_id
    LEFT JOIN inventory i ON LOWER(TRIM(i.item_name)) = LOWER(TRIM(p.product_name))
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
        SELECT p.product_name, SUM(smv.quantity) as weekly_out 
        FROM stock_movements smv
        JOIN products p ON smv.product_id = p.product_id
        WHERE smv.movement_type='out' AND smv.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
        GROUP BY p.product_name
    ) sm ON LOWER(TRIM(i.item_name)) = LOWER(TRIM(sm.product_name))
    WHERE sm.weekly_out >= (i.stock_qty * 0.8)
")->fetch_assoc()['c'];

// Slow Moving (Condition 1: 10 days not critical | Condition 2: stock is ordered after 2 months)
// Simplified: No OUT movements in the last 60 days AND the current stock is NOT low/critical.
$slow_moving_count = $conn->query("SELECT COUNT(i.id) as c 
    FROM inventory i
    LEFT JOIN (
        SELECT DISTINCT p.product_name 
        FROM stock_movements smv
        JOIN products p ON smv.product_id = p.product_id
        WHERE smv.movement_type='out' AND smv.created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) 
    ) sm ON LOWER(TRIM(i.item_name)) = LOWER(TRIM(sm.product_name))
    WHERE sm.product_name IS NULL AND i.stock_qty > 0 AND i.stock_qty > i.reorder_level
")->fetch_assoc()['c'];

// Additional: Total inventory cost and inventory list for Full Stock Monitoring
$costColumnExists = false;
$res_col = $conn->query("SHOW COLUMNS FROM inventory LIKE 'cost_per_unit'");
if ($res_col && $res_col->num_rows > 0) {
    $costColumnExists = true;
    $total_inventory_cost = (float)($conn->query("SELECT COALESCE(SUM(stock_qty * COALESCE(cost_per_unit,0)), 0) as total_cost FROM inventory")->fetch_assoc()['total_cost'] ?? 0);
} else {
    // Keep page functional even when cost_per_unit column is missing
    $total_inventory_cost = 0.0;
}
$inventory_list = $conn->query("SELECT * FROM inventory ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);

// ----------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-store, must-revalidate">
<title>Stock Monitoring - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
<script>
// Apply sidebar state BEFORE body renders to prevent layout shift
// DEFAULT: Sidebar is EXPANDED unless explicitly saved as collapsed
(function(){
    var storedState = localStorage.getItem('sidebarCollapsed');
    if (storedState === 'true') {
        document.documentElement.classList.add('sidebar-will-collapse');
    }
})();
</script>
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Stock Monitoring</h1>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert-success">✅ <?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert-error">⚠️ <?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bg-success-light"><i class='bx bx-download icon-success'></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_in) ?></h3>
                <p>Total Stock In</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-danger-light"><i class='bx bx-upload icon-danger'></i></div>
            <div class="stat-info">
                <h3><?= number_format($total_out) ?></h3>
                <p>Total Stock Out</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-warning-light"><i class='bx bx-error icon-warning'></i></div>
            <div class="stat-info">
                <h3><?= $low_stock ?></h3>
                <p>Low Stock Items</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-purple-light"><i class='bx bx-run icon-purple'></i></div>
            <div class="stat-info">
                <h3><?= $fast_moving_count ?></h3>
                <p>Fast Moving Items</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-info-light"><i class='bx bx-walk icon-info'></i></div>
            <div class="stat-info">
                <h3><?= $slow_moving_count ?></h3>
                <p>Slow Moving Items</p>
            </div>
        </div>
        </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Full Stocks Monitoring</h2>
            <div class="display-flex gap-12 align-items-center">
                <div class="cost-display">Total Inventory Cost: <span class="cost-amount">₱<?= number_format($total_inventory_cost, 2) ?></span>
                    <?php if (!$costColumnExists): ?>
                        <div class="muted-text cost-missing-text">Cost data missing — add `cost_per_unit` column to `inventory` to enable totals.</div>
                    <?php endif; ?>
                </div>
                <button class="btn-primary" onclick="refreshInventory()"><i class='bx bx-refresh'></i> Refresh</button>
                <a class="btn-secondary" href="reports.php?export=inventory">Export CSV</a>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock</th>
                        <th>Cost / Unit</th>
                        <th>Amount</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory_list as $it): ?>
                    <?php $amount = ($it['stock_qty'] ?? 0) * (float)($it['cost_per_unit'] ?? 0); ?>
                    <tr>
                        <td><a href="#" onclick="viewTransactions(<?= (int)$it['id'] ?>);return false;" class="item-code-link"><?= htmlspecialchars($it['item_code'] ?? '—') ?></a></td>
                        <td><?= htmlspecialchars($it['item_name']) ?></td>
                        <td><?= htmlspecialchars($it['category']) ?></td>
                        <td><?= htmlspecialchars($it['unit'] ?? 'pcs') ?></td>
                        <td><?= number_format($it['stock_qty'] ?? 0) ?></td>
                        <td><?= number_format((float)($it['cost_per_unit'] ?? 0), 2) ?></td>
                        <td><?= number_format($amount, 2) ?></td>
                        <td><?= htmlspecialchars($it['reorder_level'] ?? '—') ?></td>
                        <td><span class="badge <?= strtolower(str_replace(' ','',$it['status'] ?? 'sufficient')) ?>"><?= htmlspecialchars($it['status'] ?? 'Sufficient') ?></span></td>
                        <td>
                            <a href="#" class="action-link" onclick="viewTransactions(<?= (int)$it['id'] ?>);return false;">View Movements</a>
                            <a href="#" class="action-link" onclick="showAddMovement(<?= (int)$it['id'] ?>, <?= json_encode($it['item_name']) ?>);return false;">Add</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
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
                        <td><?= htmlspecialchars($mov['reference_number'] ?? ucfirst($mov['reference_type'])) ?></td>
                        <td><?= htmlspecialchars($mov['username'] ?? 'System') ?></td>
                        <td><?= htmlspecialchars($mov['remarks'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Transactions Modal -->
<div id="transactionsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="txnModalTitle">Transactions</h2>
            <span class="close" onclick="closeModal('transactionsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <table class="data-table" id="transactionTable">
                <thead>
                    <tr><th>Date</th><th>Ref #</th><th>Particulars</th><th>In</th><th>Out</th><th>Balance</th><th>Unit Cost</th><th>Amount</th></tr>
                </thead>
                <tbody id="transactionTableBody"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- Movement Modal -->
<div id="movementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="movModalTitle">Add Stock Movement</h2>
            <span class="close" onclick="closeModal('movementModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="stock_monitoring.php">
                <div class="form-group">
                    <label>Item</label>
                    <select name="product_id" id="mov_product_id" required>
                        <option value="">-- Select item --</option>
                        <?php foreach ($inventory_list as $inv): ?>
                            <option value="<?= (int)$inv['id'] ?>"><?= htmlspecialchars($inv['item_name']) ?> (<?= htmlspecialchars($inv['item_code'] ?? 'N/A') ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select name="movement_type" required>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" id="mov_quantity" required min="1">
                </div>
                <div class="form-group">
                    <label>Reference Number</label>
                    <input type="text" name="reference_number" placeholder="PO-0001 / INV-0001">
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <textarea name="remarks" rows="3" placeholder="Optional remarks"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeModal('movementModal')">Cancel</button>
                    <button type="submit" class="btn-primary" id="movSubmitBtn" disabled>Submit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function closeModal(id){ document.getElementById(id).style.display = 'none'; }
function openModal(id){
    // Use flex display so CSS centering (align-items/justify-content) applies
    var el = document.getElementById(id);
    if (!el) return;
    el.style.display = 'flex';
}

function viewTransactions(itemId){
    fetch('inventory.php?action=fetch_transactions&item_id='+encodeURIComponent(itemId))
    .then(res => res.json())
    .then(data => {
        if(!data.success){ alert('No transactions or item not found'); return; }
        document.getElementById('txnModalTitle').innerText = data.item_code + ' — ' + data.item_name;
        var body = document.getElementById('transactionTableBody');
        body.innerHTML = '';
        data.transactions.forEach(t => {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td>'+t.date+'</td><td>'+t.ref_num+'</td><td>'+t.particulars+'</td><td class="text-align-right">'+(t.in||'')+'</td><td class="text-align-right">'+(t.out||'')+'</td><td class="text-align-right">'+t.balance+'</td><td class="text-align-right">'+t.unit_cost+'</td><td class="text-align-right">'+t.amount+'</td>';
            body.appendChild(tr);
        });
        openModal('transactionsModal');
    }).catch(e=>{ console.error(e); alert('Failed to fetch transactions.') });
}

function showAddMovement(productId, itemName){
    var productSelect = document.getElementById('mov_product_id');
    if (productSelect) {
        productSelect.value = productId ? String(productId) : '';
    }
    document.getElementById('movModalTitle').innerText = 'Add Stock Movement' + (itemName ? ' — '+itemName : '');
    updateMovementSubmitState();
    openModal('movementModal');
}

function refreshInventory(){ location.reload(); }

// Close modals when clicking outside modal content
window.onclick = function(e){
    var tx = document.getElementById('transactionsModal');
    var mv = document.getElementById('movementModal');
    if(e.target == tx) tx.style.display='none';
    if(e.target == mv) mv.style.display='none';
}

function updateMovementSubmitState(){
    var productId = document.getElementById('mov_product_id')?.value || '';
    var qty = parseFloat(document.getElementById('mov_quantity')?.value || '0');
    var btn = document.getElementById('movSubmitBtn');
    if (!btn) return;
    btn.disabled = !(productId && qty > 0);
}

document.getElementById('mov_product_id')?.addEventListener('change', updateMovementSubmitState);
document.getElementById('mov_quantity')?.addEventListener('input', updateMovementSubmitState);
</script>

</body>
</html>