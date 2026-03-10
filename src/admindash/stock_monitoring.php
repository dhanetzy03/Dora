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
            <form method="POST" action="inventory.php">
                <input type="hidden" name="product_id" id="mov_product_id">
                <div class="form-group">
                    <label>Type</label>
                    <select name="movement_type" required>
                        <option value="in">In</option>
                        <option value="out">Out</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" name="quantity" required min="1">
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
                    <button type="submit" class="btn-primary">Submit</button>
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
    if(productId){ document.getElementById('mov_product_id').value = productId; }
    document.getElementById('movModalTitle').innerText = 'Add Stock Movement' + (itemName ? ' — '+itemName : '');
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
</script>

</body>
</html>