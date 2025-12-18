<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "staff") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";

// Handle Sale Recording (supports itemized `items` JSON)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_sale'])) {
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
                // Helpers to resolve cost: get product_code then inventory.cost_per_unit via item_code
                $stmt_get_product_code = $conn->prepare("SELECT product_code FROM products WHERE product_id = ? LIMIT 1");
                // Prepare inventory cost lookup only if the column exists
                $stmt_get_inventory_cost = null;
                if (column_exists('inventory', 'cost_per_unit')) {
                    $stmt_get_inventory_cost = $conn->prepare("SELECT cost_per_unit FROM inventory WHERE item_code = ? LIMIT 1");
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
                        if ($pcode) {
                            $stmt_get_inventory_cost->bind_param("s", $pcode);
                            $stmt_get_inventory_cost->execute();
                            $res_cost = $stmt_get_inventory_cost->get_result();
                            if ($r_cost = $res_cost->fetch_assoc()) {
                                $unit_cost_at_movement = (float)$r_cost['cost_per_unit'];
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
            $success = "Sale recorded successfully! Waiting for admin validation.";
        } else {
            $success = "Sale recorded successfully! Waiting for admin validation.";
        }

    } else {
        $error = "Error recording sale.";
    }
    if ($stmt) $stmt->close();
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/staff-style.css">
<body class="shukran-staff">
<script>
  // Pre-render sidebar state before body loads
  if (localStorage.getItem('sidebarCollapsed') === 'true') {
    document.documentElement.classList.add('sidebar-will-collapse');
  }
</script>

<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-text">
                <h2>☕ Shukran</h2>
                <p>Staff Panel</p>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle Sidebar">
                <i class='bx bx-chevron-left'></i>
            </button>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="staff.php" class="nav-item active">
            <i class='bx bx-home'></i>
            <span>Dashboard</span>
        </a>
        <a href="#" class="nav-item">
            <i class='bx bx-receipt'></i>
            <span>My Sales</span>
        </a>
        <a href="../auth/logout.php" class="nav-item">
            <i class='bx bx-exit'></i>
            <span>Logout</span>
        </a>
    </nav>
</div>

<div class="main-content">
    <div class="top-bar">
        <h1>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?>!</h1>
        <div class="top-bar-actions">
            <a href="../auth/logout.php" class="btn btn-danger btn-small">
                <i class='bx bx-exit'></i> Logout
            </a>
        </div>
    </div>

    <?php if (isset($success)): ?>
    <div style="margin: 20px auto; max-width: 1200px; padding: 15px; background: rgba(76, 175, 80, 0.1); color: #2e7d32; border-radius: 8px; border-left: 4px solid #4caf50;">
        <strong>✅ Success:</strong> <?= $success ?>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div style="margin: 20px auto; max-width: 1200px; padding: 15px; background: rgba(244, 67, 54, 0.1); color: #b71c1c; border-radius: 8px; border-left: 4px solid #f44336;">
        <strong>❌ Error:</strong> <?= $error ?>
    </div>
    <?php endif; ?>

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

    <!-- Record Sale Form -->
    <div class="form-section">
        <h2>Record New Sale</h2>
        <form method="POST">
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
                    <input type="number" step="0.01" name="total_amount" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" required>
                        <option value="">-- Select Payment Method --</option>
                        <option value="cash">Cash</option>
                        <option value="card">Card</option>
                        <option value="gcash">GCash</option>
                        <option value="other">Other</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="record_sale" class="btn btn-primary">
                    <i class='bx bx-save'></i> Record Sale
                </button>
            </div>
        </form>
    </div>

    <!-- My Sales History -->
    <div class="form-section">
        <h2>My Sales History</h2>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($my_sales)): ?>
                    <tr><td colspan="7" style="text-align: center; padding: 30px; color: #999;">No sales recorded yet</td></tr>
                    <?php else: ?>
                    <?php foreach ($my_sales as $sale): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td><?= ucfirst($sale['payment_method']) ?></td>
                        <td>
                            <?php
                                $status = $sale['status'];
                                $badge = $status == 'completed' ? 'badge-success' : ($status == 'pending' ? 'badge-warning' : 'badge-danger');
                            ?>
                            <span class="badge <?= $badge ?>"><?= ucfirst($status) ?></span>
                        </td>
                        <td><?= date('M d, Y H:i', strtotime($sale['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>

<script>
// Sidebar toggle functionality
const sidebarToggle = document.getElementById('sidebarToggle');
const sidebar = document.querySelector('.sidebar');
const html = document.documentElement;

sidebarToggle?.addEventListener('click', function() {
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    
    if (isCollapsed) {
        // Expand sidebar
        localStorage.setItem('sidebarCollapsed', 'false');
        html.classList.remove('sidebar-will-collapse');
    } else {
        // Collapse sidebar
        localStorage.setItem('sidebarCollapsed', 'true');
        html.classList.add('sidebar-will-collapse');
    }
});

// Set active nav item based on current page
document.querySelectorAll('.nav-item').forEach(item => {
    if (item.href === window.location.href || 
        (item.href.includes('staff.php') && window.location.pathname.includes('staff.php'))) {
        item.classList.add('active');
    } else {
        item.classList.remove('active');
    }
});

// Fill the datetime-local input with the user's local current date/time
document.addEventListener('DOMContentLoaded', function(){
    var el = document.getElementById('sale_date_input');
    if (!el) return;
    // If the field already has a value (e.g., editing), don't overwrite
    if (el.value && el.value.trim() !== '') return;
    var now = new Date();
    function pad(n){ return n.toString().padStart(2,'0'); }
    var local = now.getFullYear() + '-' + pad(now.getMonth()+1) + '-' + pad(now.getDate()) + 'T' + pad(now.getHours()) + ':' + pad(now.getMinutes());
    el.value = local;
});
</script>
