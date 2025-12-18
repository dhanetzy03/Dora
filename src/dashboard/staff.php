<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "staff") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";
$is_admin = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin');
// Sidebar is included after POST handling to avoid accidentally sending HTML before JSON responses

// Handle Sale Recording (supports itemized `items` JSON)
// Supports normal form POST (`record_sale`) and AJAX POST (`ajax_record_sale`)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['record_sale']) || isset($_POST['ajax_record_sale']))) {
    $sale_number = 'SALE-' . date('Ymd') . '-' . rand(1000, 9999);
    $sale_date = $_POST['sale_date'];
    $customer_name = trim($_POST['customer_name']);
    $posted_total = (float)($_POST['total_amount'] ?? 0);
    $payment_method = $_POST['payment_method'];
    $staff_id = $_SESSION['user_id'];

    // Insert sale (initial total can be 0, we'll update after items)
    $stmt = $conn->prepare("INSERT INTO sales (sale_number, sale_date, customer_name, total_amount, payment_method, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $initial_total = $posted_total ?: 0.00;
    $stmt->bind_param("sssdsi", $sale_number, $sale_date, $customer_name, $initial_total, $payment_method, $staff_id);

    if ($stmt->execute()) {
        $sale_id = $conn->insert_id;
        $computed_total = 0.00;

        // If itemized data provided, process sale_items and update stock
        if (!empty($_POST['items'])) {
            $items = json_decode($_POST['items'], true);
            if (is_array($items) && count($items) > 0) {
                $stmt_item = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt_get_stock = $conn->prepare("SELECT quantity FROM stock WHERE product_id = ? LIMIT 1");
                $stmt_update_stock = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE product_id = ?");
                // Enhanced movement insert: include sale_ref_id, unit_cost_at_movement, reference_number
                $stmt_insert_movement = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, reference_id, sale_ref_id, unit_cost_at_movement, reference_number, remarks, created_by, created_at) VALUES (?, 'out', ?, ?, ?, 'sale', ?, ?, ?, ?, ?, ?, NOW())");
                // Helpers to resolve cost: get product_code then inventory.cost_per_unit via item_code or item_name
                $stmt_get_product_code = $conn->prepare("SELECT product_code, product_name FROM products WHERE product_id = ? LIMIT 1");
                // Prepare inventory cost lookup only if the column exists. Support lookup by item_code or item_name.
                $stmt_get_inventory_cost = null;
                $inventory_lookup_by = null; // 'code' or 'name'
                if (column_exists('inventory', 'cost_per_unit')) {
                    if (column_exists('inventory', 'item_code')) {
                        $stmt_get_inventory_cost = $conn->prepare("SELECT cost_per_unit FROM inventory WHERE item_code = ? LIMIT 1");
                        $inventory_lookup_by = 'code';
                    } elseif (column_exists('inventory', 'item_name')) {
                        $stmt_get_inventory_cost = $conn->prepare("SELECT cost_per_unit FROM inventory WHERE item_name = ? LIMIT 1");
                        $inventory_lookup_by = 'name';
                    }
                }

                foreach ($items as $it) {
                    $product_id = (int)($it['product_id'] ?? 0);
                    $qty = (float)($it['quantity'] ?? 0);
                    $unit_price = (float)($it['unit_price'] ?? 0.00);
                    $subtotal = $qty * $unit_price;
                    $computed_total += $subtotal;

                    if ($product_id <= 0 || $qty <= 0) continue;

                    // Insert sale item
                    $stmt_item->bind_param("iiddd", $sale_id, $product_id, $qty, $unit_price, $subtotal);
                    $stmt_item->execute();

                    // Adjust stock (use `stock` table if present)
                    $prev_qty = 0.00;
                    $new_qty = 0.00;
                    if ($stmt_get_stock) {
                        $stmt_get_stock->bind_param("i", $product_id);
                        $stmt_get_stock->execute();
                        $res = $stmt_get_stock->get_result();
                        if ($row = $res->fetch_assoc()) {
                            $prev_qty = (float)$row['quantity'];
                            $new_qty = max(0, $prev_qty - $qty);
                            $stmt_update_stock->bind_param("di", $new_qty, $product_id);
                            $stmt_update_stock->execute();
                        }
                    }

                    // Determine unit cost at movement (try to fetch from inventory via product_code->item_code)
                    $unit_cost_at_movement = null;
                    $stmt_get_product_code->bind_param("i", $product_id);
                    $stmt_get_product_code->execute();
                    $res_code = $stmt_get_product_code->get_result();
                    if ($row_code = $res_code->fetch_assoc()) {
                        $pcode = $row_code['product_code'];
                        $pname = $row_code['product_name'];
                        if ($stmt_get_inventory_cost && $inventory_lookup_by) {
                            $lookup_val = ($inventory_lookup_by === 'code') ? $pcode : $pname;
                            if ($lookup_val) {
                                $stmt_get_inventory_cost->bind_param("s", $lookup_val);
                                $stmt_get_inventory_cost->execute();
                                $res_cost = $stmt_get_inventory_cost->get_result();
                                if ($r_cost = $res_cost->fetch_assoc()) {
                                    $unit_cost_at_movement = (float)$r_cost['cost_per_unit'];
                                }
                            }
                        }
                    }

                    // Log stock movement (include sale_ref_id and reference_number)
                    if ($stmt_insert_movement) {
                        $remarks = "Sale: {$sale_number}";
                        $reference_number = $sale_number;
                        // Bind types: i d d d i i d s s i -> "idddiidssi"
                        $stmt_insert_movement->bind_param("idddiidssi", $product_id, $qty, $prev_qty, $new_qty, $sale_id, $sale_id, $unit_cost_at_movement, $reference_number, $remarks, $staff_id);
                        $stmt_insert_movement->execute();
                    }
                }

                if ($stmt_item) $stmt_item->close();
                if ($stmt_get_stock) $stmt_get_stock->close();
                if ($stmt_update_stock) $stmt_update_stock->close();
                if ($stmt_insert_movement) $stmt_insert_movement->close();
            }
        }

        // If items were provided, update sales.total_amount with computed total
        if (!empty($computed_total)) {
            $update_sale = $conn->prepare("UPDATE sales SET total_amount = ?, updated_at = NOW() WHERE sale_id = ?");
            $update_sale->bind_param("di", $computed_total, $sale_id);
            $update_sale->execute();
            $update_sale->close();
        }
        $success = "Sale recorded successfully! Waiting for admin validation.";

        // If AJAX request, return JSON and exit so frontend can reset the form without reload
        if (isset($_POST['ajax_record_sale'])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'sale_id' => $sale_id,
                'sale_number' => $sale_number,
                'message' => $success
            ]);
            if ($stmt) $stmt->close();
            exit();
        }

        // Diagnostic: if no sale_items were recorded, write a small log entry for debugging
        if (empty($_POST['items']) || (isset($items) && empty($items))) {
            $logdir = __DIR__ . '/../../database/logs';
            if (!is_dir($logdir)) @mkdir($logdir, 0755, true);
            $logfile = $logdir . '/sales_missing_items.log';
            $msg = date('Y-m-d H:i:s') . " | sale_id={$sale_id} sale_number={$sale_number} posted_total={$posted_total} computed_total={$computed_total} created_by={$staff_id}\n";
            @file_put_contents($logfile, $msg, FILE_APPEND | LOCK_EX);
        }

    } else {
        $error = "Error recording sale.";
    }
    if ($stmt) $stmt->close();

        // If this was an AJAX request but we reached here (error path), return JSON failure
        if (isset($_POST['ajax_record_sale'])) {
            header('Content-Type: application/json');
            $errMsg = $error ?? 'Error recording sale.';
            echo json_encode(['success' => false, 'message' => $errMsg]);
            exit();
        }
}

// Fetch staff's sales
$staff_id = $_SESSION['user_id'];
$my_sales = $conn->query("
    SELECT * FROM sales 
    WHERE created_by = $staff_id 
    ORDER BY created_at DESC 
    LIMIT 20
")->fetch_all(MYSQLI_ASSOC);

$total_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id")->fetch_assoc()['c'];
$pending_validation = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id AND status='pending'")->fetch_assoc()['c'];
$validated_sales = $conn->query("SELECT COUNT(*) as c FROM sales WHERE created_by = $staff_id AND status='completed'")->fetch_assoc()['c'];
// Fetch products for itemized sale UI
$price_col = null;
$possible_price_cols = ['price','unit_price','selling_price','retail_price'];
foreach ($possible_price_cols as $c) {
    if (column_exists('products', $c)) { $price_col = $c; break; }
}
if ($price_col) {
    $products = $conn->query("SELECT product_id, product_code, product_name, unit, {$price_col} AS price FROM products WHERE status='active' ORDER BY product_name")->fetch_all(MYSQLI_ASSOC);
} else {
    $products = $conn->query("SELECT product_id, product_code, product_name, unit FROM products WHERE status='active' ORDER BY product_name")->fetch_all(MYSQLI_ASSOC);
}
?>
<?php include_once 'sidebar_staff.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/staff-style.css">
<body class="shukran-staff">
<div class="main-content">
    <!-- Top bar header: Greeting, quick actions -->
    <div class="top-bar">
        <div>
            <h1>Staff Dashboard</h1>
            <p class="muted">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Staff') ?> • <span id="timeNow"></span></p>
        </div>
        <div class="top-bar-actions">
            <!-- <a href="../admindash/sales_validation.php" class="btn btn-primary">Sales Validation</a> -->
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>


    <script>
  // Pre-render sidebar state before body loads
  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.documentElement.classList.add('sidebar-will-collapse');
  }
</script>

<script>
// Update live clock in header
function startClock() {
    const el = document.getElementById('timeNow');
    if (!el) return;
    function tick() {
        const now = new Date();
        el.textContent = now.toLocaleTimeString();
    }
    tick();
    setInterval(tick, 1000);
}
document.addEventListener('DOMContentLoaded', startClock);
</script>

<script>
// Item builder for staff sales (run after DOM ready)
document.addEventListener('DOMContentLoaded', function () {
(() => {
    const items = [];
    const productSelect = document.getElementById('productSelect');
    const qtyInput = document.getElementById('itemQty');
    const priceInput = document.getElementById('itemPrice');
    const addBtn = document.getElementById('addItemBtn');
    const itemsTable = document.getElementById('itemsTable').querySelector('tbody');
    const itemsInput = document.getElementById('itemsInput');
    const totalInput = document.getElementById('totalAmountInput');

    function render() {
        itemsTable.innerHTML = '';
        if (items.length === 0) {
            itemsTable.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#999">No items added</td></tr>';
            updateTotals();
            return;
        }
            items.forEach((it, idx) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${it.name}</td>
                <td>${parseFloat(it.qty)}</td>
                <td>${it.unit || ''}</td>
                    <td>₱${parseFloat(it.unit_price || it.price || 0).toFixed(2)}</td>
                    <td>₱${(parseFloat(it.qty) * parseFloat(it.unit_price || it.price || 0)).toFixed(2)}</td>
                <td><button type="button" data-idx="${idx}" class="btn btn-secondary btn-sm remove-item">Remove</button></td>
            `;
            itemsTable.appendChild(tr);
        });
        updateTotals();
    }

    function updateTotals() {
        const total = items.reduce((s, it) => s + (parseFloat(it.qty) * parseFloat(it.unit_price || it.price || 0)), 0);
        if (totalInput) totalInput.value = total.toFixed(2);
        if (itemsInput) itemsInput.value = JSON.stringify(items);
    }

    // Auto-fill unit price when a product is selected (uses data-price from option if available)
    if (productSelect && priceInput) {
        productSelect.addEventListener('change', () => {
            const opt = productSelect.options[productSelect.selectedIndex];
            const p = opt ? opt.dataset.price : null;
            if (p && p !== '""') {
                priceInput.value = parseFloat(p).toFixed(2);
                return;
            }
            // if no data-price, fetch from server
            const pid = opt ? opt.value : 0;
            if (!pid) { priceInput.value = '0.00'; return; }
            fetch(`get_product.php?product_id=${pid}`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.success && data.product) {
                        const serverPrice = data.product.price;
                        priceInput.value = serverPrice ? parseFloat(serverPrice).toFixed(2) : '0.00';
                    } else {
                        priceInput.value = '0.00';
                    }
                }).catch(() => priceInput.value = '0.00');
        });
    }

    addBtn.addEventListener('click', () => {
        const pid = productSelect.value;
        if (!pid) return alert('Choose a product.');
        const pname = productSelect.options[productSelect.selectedIndex].text;
        const punit = productSelect.options[productSelect.selectedIndex].dataset.unit || '';
        const qty = parseFloat(qtyInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        if (qty <= 0) return alert('Quantity must be positive.');
        if (price < 0) return alert('Price cannot be negative.');
        // push object with server-side expected key `unit_price`
        items.push({ product_id: parseInt(pid), name: pname, unit: punit, qty: qty, unit_price: price });
        render();
    });

    itemsTable.addEventListener('click', (e) => {
        if (e.target.classList.contains('remove-item')) {
            const idx = parseInt(e.target.dataset.idx);
            items.splice(idx, 1);
            render();
        }
    });

    // Ensure items JSON and total are populated before submit
    const saleForm = document.getElementById('saleForm');
    if (saleForm) {
        saleForm.addEventListener('submit', function (ev) {
            // if there are items, ensure itemsInput contains them and total is set
            if (items.length > 0) {
                itemsInput.value = JSON.stringify(items);
                const totalForSubmit = items.reduce((s, it) => s + (parseFloat(it.qty) * parseFloat(it.unit_price || it.price || 0)), 0);
                totalInput.value = totalForSubmit.toFixed(2);
            } else {
                // if no items, ensure hidden input empty
                itemsInput.value = '';
            }
        });
    }

    // AJAX: Record & New (submit sale and reset form without reloading)
    const recordAndNewBtn = document.getElementById('recordAndNewBtn');
    if (recordAndNewBtn) {
        recordAndNewBtn.addEventListener('click', function () {
            if (items.length === 0) return alert('Add at least one item before recording.');
            // build FormData from form
            const fd = new FormData(saleForm);
            fd.append('ajax_record_sale', '1');
            // ensure items and total are up to date
            itemsInput.value = JSON.stringify(items);
            const totalForSubmit = items.reduce((s, it) => s + (parseFloat(it.qty) * parseFloat(it.unit_price || it.price || 0)), 0);
            totalInput.value = totalForSubmit.toFixed(2);
            fd.set('total_amount', totalInput.value);

            // disable button while sending
            recordAndNewBtn.disabled = true;
            const originalText = recordAndNewBtn.textContent;
            recordAndNewBtn.textContent = 'Recording...';

            fetch('', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data && data.success) {
                        // reset items and form fields for next entry
                        items.length = 0;
                        render();
                        // quick feedback
                        alert('Recorded: ' + (data.sale_number || data.sale_id));
                        // clear non-persistent fields
                        saleForm.reset();
                    } else {
                        alert('Failed to record sale.');
                    }
                }).catch(err => {
                    console.error(err);
                    alert('Error recording sale.');
                }).finally(() => {
                    recordAndNewBtn.disabled = false;
                    recordAndNewBtn.textContent = originalText;
                });
        });
    }
})();
});
</script>



    <div class="stats-grid">
        <div class="stat-card sales">
            <div class="stat-icon"><i class='bx bx-receipt'></i></div>
            <div class="stat-content">
                <h3>Total Sales</h3>
                <p class="stat-value"><?= $total_sales ?></p>
            </div>
        </div>
        <div class="stat-card pending">
            <div class="stat-icon"><i class='bx bx-time'></i></div>
            <div class="stat-content">
                <h3>Pending Validation</h3>
                <p class="stat-value"><?= $pending_validation ?></p>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class='bx bx-check-circle'></i></div>
            <div class="stat-content">
                <h3>Validated Sales</h3>
                <p class="stat-value"><?= $validated_sales ?></p>
            </div>
        </div>
    </div>

    Record Sale Form
    <div class="form-section">
        <h2>Record New Sale</h2>
        <form method="POST" id="saleForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>Sale Date & Time</label>
                    <input id="sale_date_input" type="datetime-local" name="sale_date" value="" required>
                </div>
                <div class="form-group">
                    <label>Customer Name</label>
                    <input type="text" name="customer_name" placeholder="Walk-in customer">
                </div>
                <div class="form-group">
                    <label>Total Amount</label>
                    <input type="number" step="0.01" name="total_amount" id="totalAmountInput" placeholder="0.00" readonly>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="">-- Select Payment Method --</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="card">Card</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Items / Orders</label>
                    <div style="display:flex;gap:10px;align-items:center;margin-bottom:12px;flex-wrap:wrap;">
                        <select id="productSelect" style="min-width:260px;padding:10px;border-radius:6px;border:1px solid #cbd5e0;" >
                            <option value="">-- Choose product --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= (int)$p['product_id'] ?>" data-unit="<?= htmlspecialchars($p['unit'] ?? '') ?>" <?= isset($p['price']) ? 'data-price="' . htmlspecialchars($p['price']) . '"' : '' ?>><?= htmlspecialchars($p['product_name']) ?><?= isset($p['product_code']) ? ' (' . htmlspecialchars($p['product_code']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input id="itemQty" type="number" step="0.01" min="0" value="1" placeholder="Qty" style="width:100px;padding:10px;border-radius:6px;border:1px solid #cbd5e0;" />
                        <input id="itemPrice" type="number" step="0.01" min="0" value="0.00" placeholder="Unit Price" style="width:140px;padding:10px;border-radius:6px;border:1px solid #cbd5e0;" />
                        <button type="button" id="addItemBtn" class="btn btn-primary">Add Item</button>
                    </div>

                    <div class="table-responsive">
                        <table id="itemsTable" class="data-table styled-table">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Unit</th>
                                    <th>Unit Price</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td colspan="6" style="text-align:center;color:#999">No items added</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <input type="hidden" name="items" id="itemsInput" />
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="record_sale" class="btn btn-primary">
                    <i class='bx bx-save'></i> Record Sale
                </button>
                <button type="button" id="recordAndNewBtn" class="btn btn-secondary">
                    <i class='bx bx-plus-circle'></i> Record & New
                </button>
            </div>
        </form>
    </div>

    <!-- My Sales History -->
    <div class="form-section">
        <h2>My Sales History</h2>
        <div class="table-responsive">
            <table class="data-table styled-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Orders</th>
                        <th>Total Qty</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Recorded At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_sales)): ?>
                    <tr><td colspan="9" style="text-align: center; padding: 30px; color: #999;">No sales recorded yet</td></tr>
                    <?php else: ?>
                    <?php foreach ($my_sales as $sale): ?>
                    <?php
                        $sale_id = (int)$sale['sale_id'];
                        $item_count = 0;
                        $total_qty = 0;
                        $item_q = $conn->query("SELECT COUNT(*) as c, COALESCE(SUM(quantity),0) as q FROM sale_items WHERE sale_id = $sale_id");
                        if ($item_q && $row = $item_q->fetch_assoc()) {
                            $item_count = (int)$row['c'];
                            $total_qty = (float)$row['q'];
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td><?= $item_count ?></td>
                        <td><?= $total_qty ?></td>
                        <td><?= htmlspecialchars(ucfirst($sale['payment_method'])) ?></td>
                        <td>
                            <?php if ($sale['status'] === 'completed'): ?>
                                <span style="color:green;font-weight:bold">✓ COMPLETED</span>
                            <?php elseif ($sale['status'] === 'pending'): ?>
                                <span style="color:orange;font-weight:bold">⏳ PENDING</span>
                            <?php else: ?>
                                <span style="color:#888"><?= htmlspecialchars(strtoupper($sale['status'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                        <td><button type="button" class="btn btn-link btn-review-sale" data-sale-id="<?= (int)$sale['sale_id'] ?>">🔍 Review</button></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeDetailsModal()">&times;</span>
            <h2 id="modalTitle">Transaction Details</h2>
            <input type="hidden" id="currentSaleId">

            <div class="table-responsive table-margin-top">
                <table class="data-table" id="transactionTable">
                    <thead>
                        <tr>
                            <th>DATE</th>
                            <th>ORDER NO.</th>
                            <th>QUANTITY</th>
                            <th>UNIT COST</th>
                            <th>MARK UP (%)</th>
                            <th>SELLING PRICE</th>
                            <th>TOTAL SALES</th>
                        </tr>
                    </thead>
                    <tbody id="transactionBody">
                        <tr><td colspan="7" class="empty-message">Loading transaction items...</td></tr>
                    </tbody>
                </table>
            </div>
            
                <div class="text-right table-margin-top" style="margin-top:0;">
                    <button class="btn-secondary" onclick="closeDetailsModal()">Close Review</button>
                    <?php if ($is_admin): ?>
                    <form id="validationForm" method="POST" action="../admindash/sales_validation.php" class="inline-form">
                        <input type="hidden" name="sale_id" id="validationSaleId">
                        <button type="submit" name="validate_sale" class="btn-primary">
                            <i class='bx bx-check-circle'></i> Mark as Validated
                        </button>
                    </form>
                    <?php else: ?>
                        <span style="margin-left:12px;color:#666">Only admins can mark sales as validated. Contact an admin to review this sale.</span>
                    <?php endif; ?>
                </div>
        </div>
    </div>

    <script>
    function closeDetailsModal() {
        const m = document.getElementById('detailsModal');
        if (m) m.style.display = 'none';
    }

    function showDetailsModal(saleId, saleNumber, saleDate) {
        const modal = document.getElementById('detailsModal');
        const title = document.getElementById('modalTitle');
        const tableBody = document.getElementById('transactionBody');
        document.getElementById('currentSaleId').value = saleId;
        const valElem = document.getElementById('validationSaleId');
        if (valElem) valElem.value = saleId;

        title.innerText = `Transaction Details for Sale #${saleNumber}`;
        tableBody.innerHTML = '<tr><td colspan="7" class="empty-message">Loading transaction items...</td></tr>';
        if (modal) modal.style.display = 'block';

        fetch('../admindash/sales_validation.php?action=fetch_sale_items&sale_id=' + encodeURIComponent(saleId))
            .then(response => {
                const ct = response.headers.get('content-type') || '';
                if (!ct.includes('application/json')) {
                    return response.text().then(text => { throw new Error('Non-JSON response: ' + text); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.items && data.items.length > 0) {
                    tableBody.innerHTML = '';
                    data.items.forEach(item => {
                        const saleDateFormatted = saleDate;
                        const saleNumberFormatted = saleNumber;
                        const totalSales = parseFloat(item.subtotal).toFixed(2);
                        const row = `
                            <tr data-sale-item-id="${item.sale_item_id}">
                                <td>${saleDateFormatted}</td>
                                <td>${saleNumberFormatted}</td>
                                <td>${parseFloat(item.quantity).toFixed(0)}</td>
                                <td>₱${parseFloat(item.unit_cost_at_sale).toFixed(2)}</td>
                                <td class="markup-cell">
                                    <input type="number" 
                                        class="markup-input" 
                                        value="${(item.markup_rate * 100).toFixed(0)}" 
                                        min="0"
                                        max="1000"
                                        data-sale-item-id="${item.sale_item_id}"
                                        onchange="updateMarkup(this)">%
                                </td>
                                <td class="selling-price-cell">₱${parseFloat(item.unit_price).toFixed(2)}</td>
                                <td class="total-sales-cell">₱${totalSales}</td>
                            </tr>
                        `;
                        tableBody.innerHTML += row;
                    });
                } else {
                    const saleInfo = data.sale || null;
                    const movs = data.movements || [];
                    if (movs && movs.length > 0) {
                        tableBody.innerHTML = '';
                        movs.forEach(m => {
                            const qty = parseFloat(m.quantity)||0;
                            const unitCost = parseFloat(m.unit_cost_at_movement)||0;
                            const subtotal = (qty * unitCost).toFixed(2);
                            const row = `
                                <tr>
                                    <td>${m.created_at || ''}</td>
                                    <td>${m.reference_number || ''}</td>
                                    <td>${qty}</td>
                                    <td>₱${parseFloat(unitCost).toFixed(2)}</td>
                                    <td>-</td>
                                    <td>₱${parseFloat(subtotal).toFixed(2)}</td>
                                    <td></td>
                                </tr>`;
                            tableBody.innerHTML += row;
                        });
                        tableBody.innerHTML += `<tr><td colspan="7" class="empty-message">Note: These rows were reconstructed from stock movements linked to the sale. Confirm with records before relying on them.</td></tr>`;
                        tableBody.innerHTML += `<tr><td colspan="7" style="padding-top:12px">
                                                <strong>Admin:</strong> Add missing item to this sale —
                                                <select id="adminAddProduct"></select>
                                                <input id="adminAddQty" type="number" step="0.01" min="0.01" value="1" style="width:80px;margin-left:8px">
                                                <input id="adminAddPrice" type="number" step="0.01" min="0" value="0.00" style="width:120px;margin-left:8px">
                                                <button id="adminAddBtn" class="btn btn-primary btn-sm" data-sale-id="${saleInfo ? saleInfo.sale_id : ''}">Add</button>
                                                <div id="adminAddMsg" style="display:inline-block;margin-left:12px;color:#333"></div>
                                            </td></tr>`;
                        setTimeout(() => populateAdminProductSelect(), 100);
                    } else if (saleInfo) {
                        tableBody.innerHTML = `<tr><td colspan="7" class="empty-message">No item details found for this sale. Sale #: <strong>${saleInfo.sale_number}</strong> — Total: ₱${parseFloat(saleInfo.total_amount).toFixed(2)}</td></tr>`;
                    } else {
                        tableBody.innerHTML = '<tr><td colspan="7" class="empty-message">No item details found for this sale.</td></tr>';
                    }
                }
            })
            .catch(error => {
                console.error('Error fetching transactions:', error);
                const msg = (error && error.message) ? error.message : 'An error occurred while fetching data.';
                tableBody.innerHTML = `<tr><td colspan="7" class="empty-message">An error occurred while fetching data. Details: ${msg}</td></tr>`;
            });
    }

    function updateMarkup(inputElement) {
        const saleItemId = inputElement.getAttribute('data-sale-item-id');
        const newMarkupPercent = parseFloat(inputElement.value);
        const newMarkupRate = newMarkupPercent / 100;
        if (isNaN(newMarkupRate) || newMarkupRate < 0) { alert("Please enter a valid markup percentage."); return; }
        const formData = new FormData();
        formData.append('update_markup', true);
        formData.append('sale_item_id', saleItemId);
        formData.append('markup_rate', newMarkupRate);

        fetch('../admindash/sales_validation.php', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const row = inputElement.closest('tr');
                const sellingPriceCell = row.querySelector('.selling-price-cell');
                const totalSalesCell = row.querySelector('.total-sales-cell');
                sellingPriceCell.innerHTML = `₱${data.new_selling_price}`;
                totalSalesCell.innerHTML = `₱${data.new_total_sales}`;
                inputElement.style.backgroundColor = '#e8f5e9';
                setTimeout(() => inputElement.style.backgroundColor = 'transparent', 1500);
            } else {
                alert('Failed to update markup: ' + data.message);
            }
        }).catch(error => { console.error('Error updating markup:', error); alert('A network error occurred while updating the markup.'); });
    }

    function populateAdminProductSelect() {
        const sel = document.getElementById('adminAddProduct');
        if (!sel) return;
        fetch('../admindash/sales_validation.php?action=list_products')
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    sel.innerHTML = '<option value="">-- Select product --</option>';
                    data.products.forEach(p => { const o = document.createElement('option'); o.value = p.product_id; o.text = p.product_name; sel.appendChild(o); });
                }
            });
        const btn = document.getElementById('adminAddBtn');
        if (btn) btn.addEventListener('click', function() {
            const saleId = this.getAttribute('data-sale-id');
            const productId = document.getElementById('adminAddProduct').value;
            const qty = document.getElementById('adminAddQty').value;
            const price = document.getElementById('adminAddPrice').value;
            const msg = document.getElementById('adminAddMsg');
            msg.innerText = 'Adding...';
            const fd = new FormData(); fd.append('action','add_sale_item_ajax'); fd.append('sale_id', saleId); fd.append('product_id', productId); fd.append('quantity', qty); fd.append('unit_price', price);
            fetch('../admindash/sales_validation.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(d => { if (d.success) { msg.innerText = 'Added — refreshing...'; setTimeout(() => { showDetailsModal(saleId, saleInfoGlobalNumber(saleId)||saleId, ''); }, 800); } else { msg.innerText = 'Error: '+(d.message||'failed'); } })
                .catch(e => { msg.innerText = 'Network error'; console.error(e); });
        });
    }

    function saleInfoGlobalNumber(saleId){ try{ return document.getElementById('modalTitle').innerText.replace('Transaction Details for Sale #','').trim(); }catch(e){return null;} }
    </script>
