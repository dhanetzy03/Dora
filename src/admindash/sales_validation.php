<?php
session_start();
// Allow both admin and staff to view this page and use read-only AJAX endpoints.
// Admin-only actions (validate, update_markup) are checked later where executed.
if (!isset($_SESSION["username"]) || !in_array($_SESSION["role"], ['admin','staff'])) {
    $is_ajax_request = isset($_GET['action']) || isset($_POST['update_markup']) || isset($_POST['validate_sale']) || isset($_POST['action']);
    if ($is_ajax_request) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'unauthenticated']);
        exit();
    }
    header("Location: ../auth/login.php");
    exit();
}

$is_admin = ($_SESSION['role'] === 'admin');
$is_staff = ($_SESSION['role'] === 'staff');
require_once "../../config/db_connect.php";

// --- START: AJAX ENDPOINT 1 - FETCH SALE ITEMS ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_sale_items' && isset($_GET['sale_id'])) {
    $sale_id = (int)$_GET['sale_id'];

    // Join sale_items (s_i) with products (p) to get item names.
    // Note: older schemas may not have `unit_cost_at_sale` or `markup_rate` columns.
    // Select the available columns and set safe defaults for missing fields below.
    $sql = "SELECT s_i.sale_item_id, s_i.quantity, s_i.unit_price, s_i.subtotal, p.product_name
            FROM sale_items s_i
            JOIN products p ON s_i.product_id = p.product_id
            WHERE s_i.sale_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    // Fetch sale-level info to include in the response (useful when no items)
    $sale_stmt = $conn->prepare("SELECT sale_number, sale_date, total_amount, payment_method, customer_name FROM sales WHERE sale_id = ? LIMIT 1");
    $sale_stmt->bind_param("i", $sale_id);
    $sale_stmt->execute();
    $sale_info = $sale_stmt->get_result()->fetch_assoc();
    $sale_stmt->close();
    // Attempt to fetch related stock_movements for this sale (useful to reconstruct items)
    $mov_sql = "SELECT sm.movement_id, sm.product_id, sm.quantity, sm.unit_cost_at_movement, sm.reference_number, sm.remarks, sm.created_at, p.product_name
                FROM stock_movements sm
                LEFT JOIN products p ON sm.product_id = p.product_id
                WHERE (sm.sale_ref_id = ? OR sm.reference_number = ?) ORDER BY sm.created_at ASC";
    $mov_stmt = $conn->prepare($mov_sql);
    // bind sale_id and sale_number (may be null)
    $sale_num = $sale_info['sale_number'] ?? null;
    $mov_stmt->bind_param('is', $sale_id, $sale_num);
    $mov_stmt->execute();
    $movements = $mov_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $mov_stmt->close();
    // Ensure each item contains the keys expected by the frontend (backwards-compatible)
    foreach ($items as &$it) {
        if (!isset($it['unit_cost_at_sale'])) $it['unit_cost_at_sale'] = 0.00;
        if (!isset($it['markup_rate'])) $it['markup_rate'] = 0.00;
        if (!isset($it['unit_price'])) $it['unit_price'] = (float)($it['unit_price'] ?? 0.00);
        if (!isset($it['subtotal'])) $it['subtotal'] = (float)($it['subtotal'] ?? 0.00);
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'items' => $items, 'sale' => $sale_info, 'movements' => $movements]);
    exit();
}
// --- END: AJAX ENDPOINT 1 ---


// --- START: AJAX ENDPOINT 2 - UPDATE MARKUP (RECALCULATES SELLING PRICE and TOTAL SALES) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_markup'])) {
    $sale_item_id = (int)$_POST['sale_item_id'];
    $new_markup_rate = (float)$_POST['markup_rate']; // Passed as a decimal (e.g., 0.20)
    
    // 1. Fetch item cost and quantity
    // Try to get unit_cost_at_sale first (if it exists), fallback to unit_price
    $stmt_fetch = $conn->prepare("SELECT quantity, unit_price FROM sale_items WHERE sale_item_id = ?");
    $stmt_fetch->bind_param("i", $sale_item_id);
    $stmt_fetch->execute();
    $item_data = $stmt_fetch->get_result()->fetch_assoc();
    $stmt_fetch->close();
    
    if ($item_data) {
        $unit_cost = (float)$item_data['unit_price'];  // Using unit_price as the cost
        $quantity = (float)$item_data['quantity'];
        
        // Calculation: SELLING PRICE = UNIT COST * (1 + MARKUP RATE)
        $new_unit_price = $unit_cost * (1 + $new_markup_rate);
        
        // Calculation: TOTAL SALES = SELLING PRICE * QUANTITY
        $new_subtotal = $new_unit_price * $quantity;
        
        // 2. Update the sale_items row (only update available columns)
        $stmt_update = $conn->prepare("
            UPDATE sale_items 
            SET unit_price = ?, subtotal = ? 
            WHERE sale_item_id = ?
        ");
        $stmt_update->bind_param("ddi", $new_unit_price, $new_subtotal, $sale_item_id);
        if (!$stmt_update->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt_update->error]);
            $stmt_update->close();
            exit();
        }
        $stmt_update->close();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'new_selling_price' => number_format($new_unit_price, 2), 
            'new_total_sales' => number_format($new_subtotal, 2)
        ]);
        exit();
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit();
}
// --- END: MARKUP UPDATE ENDPOINT ---

// --- START: AJAX ENDPOINT 3 - LIST RECENT SALES WITH NO ITEMS (DIAGNOSTIC) ---
if (isset($_GET['action']) && $_GET['action'] === 'missing_sales') {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $limit = $limit > 0 && $limit <= 1000 ? $limit : 100;

    $sql = "SELECT s.sale_id, s.sale_number, s.sale_date, s.total_amount, s.status, u.username AS staff_name
            FROM sales s
            LEFT JOIN sale_items si ON s.sale_id = si.sale_id
            LEFT JOIN users u ON s.created_by = u.user_id
            WHERE si.sale_id IS NULL
            ORDER BY s.created_at DESC
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'count' => count($rows), 'sales' => $rows]);
    exit();
}
// Simple products list endpoint for admin modal (used by backfill UI)
if (isset($_GET['action']) && $_GET['action'] === 'list_products') {
    $prods = $conn->query("SELECT product_id, product_name FROM products WHERE status='active' ORDER BY product_name")->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'products' => $prods]);
    exit();
}
// --- END: AJAX ENDPOINT 3 ---


// Handle validation action (ADMIN only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_sale'])) {
    if (!$is_admin) {
        http_response_code(403);
        echo "Forbidden";
        exit();
    }
    $sale_id = (int)$_POST['sale_id'];
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE sales SET status='completed', validated_by=?, validated_at=NOW() WHERE sale_id=?");
    $stmt->bind_param("ii", $admin_id, $sale_id);
    $stmt->execute();
    header("Location: sales_validation.php?msg=validated");
    exit();
}

// --- START: AJAX ENDPOINT 4 - ADMIN: Add sale_item (backfill) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_sale_item_ajax') {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'unauthenticated']);
        exit();
    }
    $sale_id = (int)($_POST['sale_id'] ?? 0);
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (float)($_POST['quantity'] ?? 0);
    $unit_price = (float)($_POST['unit_price'] ?? 0);
    if ($sale_id <= 0 || $product_id <= 0 || $quantity <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']);
        exit();
    }

    $subtotal = $quantity * $unit_price;
    $stmt_ins = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
    $stmt_ins->bind_param('iiddd', $sale_id, $product_id, $quantity, $unit_price, $subtotal);
    $ok = $stmt_ins->execute();
    if ($ok) {
        $insert_id = $conn->insert_id;
        $stmt_ins->close();
        // Update sales total_amount
        $stmt_up = $conn->prepare("UPDATE sales SET total_amount = total_amount + ?, updated_at = NOW() WHERE sale_id = ?");
        $stmt_up->bind_param('di', $subtotal, $sale_id);
        $stmt_up->execute();
        $stmt_up->close();

        // Decrease stock (if stock record exists) and log movement
        $prev_qty = null; $new_qty = null;
        $stmt_stock = $conn->prepare("SELECT quantity FROM stock WHERE product_id = ? LIMIT 1");
        $stmt_stock->bind_param('i', $product_id);
        $stmt_stock->execute();
        $res_stock = $stmt_stock->get_result();
        if ($row = $res_stock->fetch_assoc()) {
            $prev_qty = (float)$row['quantity'];
            $new_qty = max(0, $prev_qty - $quantity);
            $stmt_upd = $conn->prepare("UPDATE stock SET quantity = ? WHERE product_id = ?");
            $stmt_upd->bind_param('di', $new_qty, $product_id);
            $stmt_upd->execute();
            $stmt_upd->close();
        }
        $stmt_stock->close();

        // Insert stock movement linking to sale
        $sale_num = $conn->query("SELECT sale_number FROM sales WHERE sale_id = $sale_id")->fetch_assoc()['sale_number'] ?? null;
        $unit_cost_for_movement = $unit_price; // best-effort
        $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, sale_ref_id, unit_cost_at_movement, reference_number, remarks, created_by, created_at) VALUES (?, 'out', ?, ?, ?, 'sale', ?, ?, ?, ?, ?, NOW())");
        $remarks = "Admin backfill";
        $stmt_mov->bind_param('idddiisssi', $product_id, $quantity, $prev_qty, $new_qty, $sale_id, $unit_cost_for_movement, $sale_num, $remarks, $_SESSION['user_id']);
        @$stmt_mov->execute();
        @$stmt_mov->close();

        echo json_encode(['success' => true, 'sale_item_id' => $insert_id]);
        exit();
    }
    $stmt_ins->close();
    echo json_encode(['success' => false, 'message' => 'Insert failed']);
    exit();
}
// --- END: AJAX ENDPOINT 4 ---
// Fetch pending sales
$staff_filter = '';
if ($is_staff) {
    $staff_id = (int)$_SESSION['user_id'];
    $staff_filter = " AND s.created_by = {$staff_id} ";
}

// Fetch pending sales (clean multiline SQL without escaping backslashes)
$pending_sql = "SELECT s.*, u.username as staff_name
    FROM sales s
    LEFT JOIN users u ON s.created_by = u.user_id
    WHERE s.status = 'pending' {$staff_filter}
    ORDER BY s.created_at DESC";
$pending_sales = $conn->query($pending_sql)->fetch_all(MYSQLI_ASSOC);

// Fetch validated sales (recent 10)
// Fetch recent validated sales
$validated_sql = "SELECT s.*, u.username as staff_name, v.username as validator_name
    FROM sales s
    LEFT JOIN users u ON s.created_by = u.user_id
    LEFT JOIN users v ON s.validated_by = v.user_id
    WHERE s.status = 'completed' {$staff_filter}
    ORDER BY s.validated_at DESC
    LIMIT 10";
$validated_sales = $conn->query($validated_sql)->fetch_all(MYSQLI_ASSOC);

$total_pending = count($pending_sales);
$total_validated = $conn->query("SELECT COUNT(*) as c FROM sales s WHERE s.status='completed' {$staff_filter}")->fetch_assoc()['c'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-store, must-revalidate">
<title>Sales Validation - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<!-- Inline full admin CSS to prevent FOUC on first load -->
<style>
body.shukran-admin * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}
body.shukran-admin {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f5f7fa;
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styles */
.sidebar {
    width: 260px;
    background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
    color: white;
    padding: 0;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    z-index: 1002;
    transition: transform 0.25s ease, width 0.25s ease;
}

/* Sidebar header / toggle */
.sidebar-header {
    padding: 18px 20px;
    border-bottom: 1px solid rgba(255,255,255,0.06);
}

...css truncated for brevity...
</style>
<!-- External CSS still loaded for browser cache and dev tools (with cache-busting) -->
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
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
<!-- Inline full admin CSS to prevent FOUC on first load -->
<style>
/* Additional Sales Validation Styles */
.markup-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.markup-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.markup-cell {
    white-space: nowrap;
}

.text-center {
    text-align: center;
}

.alert-success {
    margin: 15px 30px;
    padding: 15px;
    background: #d4edda;
    color: #155724;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}

/* Modal positioning (ensure centered and responsive) */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.45);
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.modal .modal-content {
    background: #fff;
    width: 100%;
    max-width: 900px;
    border-radius: 10px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    animation: slideDown 0.2s ease-out;
    overflow: auto;
    max-height: 90vh;
}
.modal .close {
    cursor: pointer;
    font-size: 20px;
}
</style>
<!-- External CSS still loaded for browser cache and dev tools (with cache-busting) -->
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>💸 Sales Validation & Monitoring</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'validated'): ?>
    <div class="alert-success">
        ✅ Sale validated successfully!
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon bg-warning-light"><i class='bx bx-time icon-warning'></i></div>
            <div class="stat-info">
                <h3><?= $total_pending ?></h3>
                <p>Pending Validation</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-success-light"><i class='bx bx-check-circle icon-success'></i></div>
            <div class="stat-info">
                <h3><?= $total_validated ?></h3>
                <p>Total Validated</p>
            </div>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Pending Sales (Require Validation)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>DATE</th>
                        <th>ORDER NO.</th>
                        <th>QUANTITY</th>
                        <th>UNIT COST</th>
                        <th>MARK UP</th>
                        <th>SELLING PRICE</th>
                        <th>TOTAL SALES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_sales)): ?>
                    <tr><td colspan="7" class="empty-message">No pending sales to validate</td></tr>
                    <?php else: ?>
                    <?php foreach ($pending_sales as $sale): ?>
                    <tr>
                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td>-</td>
                        <td>-</td>
                        <td><span class="badge badge-warning">Pending</span></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td class="text-center">
                            <button class="btn-primary btn-sm" 
                                onclick="showDetailsModal(<?= $sale['sale_id'] ?>, '<?= htmlspecialchars($sale['sale_number']) ?>', '<?= date('M d, Y', strtotime($sale['sale_date'])) ?>')" 
                                title="Review and Validate">
                                <i class='bx bx-search-alt'></i> Review
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="content-card">
        <div class="card-header">
            <h2>Recently Validated Sales</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                            <th>Sale #</th>
                            <th>Date</th>
                            <th>Staff</th>
                            <th>Amount</th>
                            <th>Validated By</th>
                            <th>Validated At</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                </thead>
                <tbody>
                    <?php foreach ($validated_sales as $sale): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td><?= date('M d, Y', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['staff_name']) ?></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td><?= htmlspecialchars($sale['validator_name']) ?></td>
                        <td><?= date('M d, Y H:i', strtotime($sale['validated_at'])) ?></td>
                        <td><span class="badge badge-success">Validated</span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
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
                <form id="validationForm" method="POST" action="" class="inline-form">
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
    document.getElementById('detailsModal').style.display = 'none';
}

function showDetailsModal(saleId, saleNumber, saleDate) {
    const modal = document.getElementById('detailsModal');
    const title = document.getElementById('modalTitle');
    const tableBody = document.getElementById('transactionBody');
    
    // Set IDs for form submission
    document.getElementById('currentSaleId').value = saleId;
    document.getElementById('validationSaleId').value = saleId;

    title.innerText = `Transaction Details for Sale #${saleNumber}`;
    tableBody.innerHTML = '<tr><td colspan="7" class="empty-message">Loading transaction items...</td></tr>';
    // Use flex display so the modal's align-items / justify-content centering works
    modal.style.display = 'flex';

    fetch(`sales_validation.php?action=fetch_sale_items&sale_id=${saleId}`)
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
                    const saleDateFormatted = saleDate; // Using the sale date passed in
                    const saleNumberFormatted = saleNumber; // Using the sale number passed in
                    
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
                        </tr>`;
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
                        const movRow = `
                            <tr>
                              <td>${m.created_at || ''}</td>
                              <td>${m.reference_number || ''}</td>
                              <td>${qty}</td>
                              <td>₱${parseFloat(unitCost).toFixed(2)}</td>
                              <td>-</td>
                              <td>₱${parseFloat(subtotal).toFixed(2)}</td>
                              <td></td>
                            </tr>`;
                        tableBody.innerHTML += movRow;
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
            // Show more actionable message including server text when available
            const msg = (error && error.message) ? error.message : 'An error occurred while fetching data.';
            tableBody.innerHTML = `<tr><td colspan="7" class="empty-message">An error occurred while fetching data. Details: ${msg}</td></tr>`;
        });
}

function updateMarkup(inputElement) {
    const saleItemId = inputElement.getAttribute('data-sale-item-id');
    const newMarkupPercent = parseFloat(inputElement.value);
    
    // Convert percentage back to a decimal rate (e.g., 20% -> 0.20)
    const newMarkupRate = newMarkupPercent / 100; 

    if (isNaN(newMarkupRate) || newMarkupRate < 0) {
        alert("Please enter a valid markup percentage.");
        return;
    }

    // Use AJAX to send the update to the server
    const formData = new FormData();
    formData.append('update_markup', true);
    formData.append('sale_item_id', saleItemId);
    formData.append('markup_rate', newMarkupRate); // Send the decimal rate

    fetch('sales_validation.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const row = inputElement.closest('tr');
            
            // Update the display cells with the new calculated values
            const sellingPriceCell = row.querySelector('.selling-price-cell');
            const totalSalesCell = row.querySelector('.total-sales-cell');
            
            sellingPriceCell.innerHTML = `₱${data.new_selling_price}`;
            totalSalesCell.innerHTML = `₱${data.new_total_sales}`;

            // Provide visual feedback
            inputElement.style.backgroundColor = '#e8f5e9';
            setTimeout(() => inputElement.style.backgroundColor = 'transparent', 1500);

        } else {
            alert('Failed to update markup: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error updating markup:', error);
        alert('A network error occurred while updating the markup.');
    });
}

// Admin helper: populate product list for backfill and handle add action
function populateAdminProductSelect() {
    const sel = document.getElementById('adminAddProduct');
    if (!sel) return;
    // Fetch products list (reuse existing products in staff page)
    fetch('sales_validation.php?action=list_products')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                sel.innerHTML = '<option value="">-- Select product --</option>';
                data.products.forEach(p => {
                    const o = document.createElement('option'); o.value = p.product_id; o.text = p.product_name; sel.appendChild(o);
                });
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
        fetch('sales_validation.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => { if (d.success) { msg.innerText = 'Added — refreshing...'; setTimeout(() => { showDetailsModal(saleId, saleInfoGlobalNumber(saleId)||saleId, ''); }, 800); } else { msg.innerText = 'Error: '+(d.message||'failed'); } })
            .catch(e => { msg.innerText = 'Network error'; console.error(e); });
    });
}

function saleInfoGlobalNumber(saleId){ try{ return document.getElementById('modalTitle').innerText.replace('Transaction Details for Sale #','').trim(); }catch(e){return null;} }
</script>

</body>
</html>