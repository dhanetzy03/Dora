<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";

// --- START: STOCK TRANSACTIONS FETCHING (NEW AJAX ENDPOINT) ---
// This is the missing endpoint that fetches the data when you click the item code.
if (isset($_GET['action']) && $_GET['action'] === 'fetch_transactions' && isset($_GET['item_id'])) {
    
    $item_id = (int)$_GET['item_id'];
    
    // 1. Fetch item details (for item code, name, and current cost)
    // Select cost_per_unit only if the column exists to avoid SQL errors on older DBs
    $cols = 'id, item_code, item_name';
    if (column_exists('inventory', 'cost_per_unit')) {
        $cols .= ', cost_per_unit';
    }
    $stmt_item = $conn->prepare("SELECT {$cols} FROM inventory WHERE id = ?");
    $stmt_item->bind_param("i", $item_id);
    $stmt_item->execute();
    $item_data = $stmt_item->get_result()->fetch_assoc();
    $stmt_item->close();

    if ($item_data) {
        
        // 2. Fetch all stock movements for the item, ordered by date
        $stmt_log = $conn->prepare("SELECT * FROM stock_movements WHERE product_id = ? ORDER BY created_at ASC");
        $stmt_log->bind_param("i", $item_id);
        $stmt_log->execute();
        $transactions = $stmt_log->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_log->close();

        // 3. Prepare response data
        $response = [
            'success' => true,
            'item_code' => $item_data['item_code'],
            'item_name' => $item_data['item_name'],
            'current_unit_cost' => (float)$item_data['cost_per_unit'], 
            'transactions' => []
        ];

        foreach ($transactions as $txn) {
            $quantity = (int)$txn['quantity'];
            // Use logged cost if available, otherwise use the current item cost
            // Use logged cost if available, otherwise fall back to item's current cost (or 0 if not present)
            $item_cost = isset($item_data['cost_per_unit']) ? (float)$item_data['cost_per_unit'] : 0.0;
            $unit_cost = (float)($txn['unit_cost_at_movement'] ?? $item_cost);
            $amount = $quantity * $unit_cost;
            
            $response['transactions'][] = [
                'date' => date('M d, Y H:i', strtotime($txn['created_at'])),
                'ref_num' => htmlspecialchars($txn['reference_number'] ?? 'N/A'),
                'particulars' => htmlspecialchars($txn['remarks'] ?? 'N/A'),
                'quantity' => $quantity,
                'unit_cost' => number_format($unit_cost, 2),
                'amount' => number_format($amount, 2),
                'in' => $txn['movement_type'] === 'in' ? number_format($quantity) : '',
                'out' => $txn['movement_type'] === 'out' ? number_format($quantity) : '',
                'balance' => number_format((int)$txn['new_quantity'])
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit();

    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Item not found.']);
        exit();
    }
}
// --- END: STOCK TRANSACTIONS FETCHING ---


// --- START: INVENTORY DELETION HANDLER (NEW) ---
if ($_SERVER["REQUEST_METHOD"] === "GET" && isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    if ($delete_id > 0) {
        // Prepare SQL statement for deletion
        $stmt_delete = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $delete_id);
            $stmt_delete->execute();
            $stmt_delete->close();
            // Optional: You would set a success session message here
        }
    }
    
    // Redirect back to inventory page
    header("Location: inventory.php");
    exit();
}
// --- END: INVENTORY DELETION HANDLER ---


// --- START: STOCK MOVEMENT HANDLER (New Feature) ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['movement_type'])) {
    
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    $movement_type = trim($_POST['movement_type'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    // NEW: Get Reference Number from the modal form
    $reference_number = trim($_POST['reference_number'] ?? 'N/A'); 
    
    $reference_type = "Manual Adjustment";
    $created_by = $_SESSION['user_id'] ?? 1; // Assuming user_id is stored in session

    if ($product_id <= 0 || $quantity <= 0 || !in_array($movement_type, ['in', 'out'])) {
        // ERROR LOGIC REMOVED
    } else {
        // 1. Get current stock quantity AND COST
        // NOTE: cost_per_unit is critical for logging the cost at the time of movement
        // Select cost_per_unit only if present
        $cols_cur = 'stock_qty, item_name, reorder_level';
        if (column_exists('inventory', 'cost_per_unit')) {
            $cols_cur .= ', cost_per_unit';
        }
        $stmt_current = $conn->prepare("SELECT {$cols_cur} FROM inventory WHERE id = ?");
        $stmt_current->bind_param("i", $product_id);
        $stmt_current->execute();
        $result_current = $stmt_current->get_result();
        $item_data = $result_current->fetch_assoc();
        $stmt_current->close();

        if ($item_data) {
            $previous_quantity = $item_data['stock_qty'];
            // NEW: Get current unit cost (if available)
            $current_unit_cost = isset($item_data['cost_per_unit']) ? (float)$item_data['cost_per_unit'] : 0.0; 
            $new_quantity = $previous_quantity;
            $item_name = $item_data['item_name'];
            $reorder_level = $item_data['reorder_level'];
            $stock_change_successful = false;

            if ($movement_type === 'in') {
                $new_quantity += $quantity;
                $stock_change_successful = true;
            } elseif ($movement_type === 'out') {
                if ($previous_quantity >= $quantity) {
                    $new_quantity -= $quantity;
                    $stock_change_successful = true;
                } else {
                    // ERROR LOGIC REMOVED
                }
            }

            if ($stock_change_successful) {
                // Determine new status based on new quantity
                $new_status = 'Sufficient';
                if ($new_quantity <= 0) {
                    $new_status = 'Out of Stock';
                } elseif ($new_quantity <= $reorder_level) {
                    $new_status = 'Low Stock';
                }

                // 2. Update inventory stock and status
                $stmt_update = $conn->prepare("UPDATE inventory SET stock_qty = ?, status = ?, last_updated = NOW() WHERE id = ?");
                $stmt_update->bind_param("isi", $new_quantity, $new_status, $product_id);
                $stmt_update->execute();
                $stmt_update->close();

                // 3. Log the stock movement
                // UPDATED: Added unit_cost_at_movement and reference_number to the INSERT and bind_param
                $stmt_log = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                $stmt_log->bind_param("isiiissids", $product_id, $movement_type, $quantity, $previous_quantity, $new_quantity, $reference_type, $remarks, $created_by, $current_unit_cost, $reference_number);
                $stmt_log->execute();
                $stmt_log->close();
                
                // SUCCESS LOGIC REMOVED
            }
        } else {
             // ERROR LOGIC REMOVED
        }
    }
    
    header("Location: inventory.php");
    exit();
}
// --- END: STOCK MOVEMENT HANDLER ---

// --- START: INTEGRATED ADD/EDIT INVENTORY LOGIC ---
if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['movement_type'])) {
    
    // 1. Collect and Sanitize Input Data
    $item_id = (int)($_POST['item_id'] ?? 0); // Used for editing existing item
    $item_code = trim($_POST['item_code'] ?? '');
    $item_name = trim($_POST['item_name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $unit = trim($_POST['unit'] ?? ''); 
    // Stock Qty is only used for ADD, not EDIT
    $stock_qty = (int)($_POST['stock_qty'] ?? 0); 
    $cost_per_unit = (float)($_POST['cost_per_unit'] ?? 0.00); 
    $reorder_level = (int)($_POST['reorder_level'] ?? 1);
    $shelf_life_days = !empty($_POST['shelf_life_days']) ? (int)$_POST['shelf_life_days'] : null;
    $date_received = !empty($_POST['date_received']) ? $_POST['date_received'] : date('Y-m-d');
    $expiry_date = null;
    if ($shelf_life_days !== null && $date_received) {
        $expiry_date = date('Y-m-d', strtotime($date_received . ' + ' . $shelf_life_days . ' days'));
    }

    // Status based on quantity
    // NOTE: For editing, we must fetch the existing stock_qty since it's not in the edit form
    if ($item_id > 0) {
         $stmt_current_qty = $conn->prepare("SELECT stock_qty FROM inventory WHERE id = ?");
         $stmt_current_qty->bind_param("i", $item_id);
         $stmt_current_qty->execute();
         $current_item_data = $stmt_current_qty->get_result()->fetch_assoc();
         $stock_qty = $current_item_data['stock_qty'] ?? 0;
         $stmt_current_qty->close();
    }
    
    $status = 'Sufficient';
    if ($stock_qty <= 0) {
        $status = 'Out of Stock';
    } elseif ($stock_qty <= $reorder_level) {
        $status = 'Low Stock';
    }

    if (empty($item_code) || empty($item_name) || empty($category) || empty($unit)) {
        // ERROR LOGIC REMOVED
    } else {
        $hasCost = column_exists('inventory', 'cost_per_unit');
        if ($item_id > 0) {
            // EDIT/UPDATE LOGIC
            // NOTE: stock_qty is not included in the UPDATE since it should be managed by the Movement Modal for logging integrity.
            if ($hasCost) {
                $sql = "UPDATE inventory SET item_name = ?, category = ?, unit = ?, cost_per_unit = ?, reorder_level = ?, status = ?, shelf_life_days = ?, date_received = ?, expiry_date = ?, last_updated = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssidiissi", $item_name, $category, $unit, $cost_per_unit, $reorder_level, $status, $shelf_life_days, $date_received, $expiry_date, $item_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $sql = "UPDATE inventory SET item_name = ?, category = ?, unit = ?, reorder_level = ?, status = ?, shelf_life_days = ?, date_received = ?, expiry_date = ?, last_updated = NOW() WHERE id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssiiissi", $item_name, $category, $unit, $reorder_level, $status, $shelf_life_days, $date_received, $expiry_date, $item_id);
                    $stmt->execute();
                    $stmt->close();
                }
            }

        } else {
            // ADD LOGIC (Original)
            if ($hasCost) {
                $sql = "INSERT INTO inventory (item_name, category, unit, stock_qty, cost_per_unit, reorder_level, status, shelf_life_days, date_received, expiry_date, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssidiisss", $item_name, $category, $unit, $stock_qty, $cost_per_unit, $reorder_level, $status, $shelf_life_days, $date_received, $expiry_date);
                    if ($stmt->execute()) {
                        // SUCCESS LOGIC REMOVED
                    } else {
                        if ($conn->errno == 1062) {
                            // ERROR LOGIC REMOVED
                        }
                    }
                    $stmt->close();
                }
            } else {
                // Fallback when database does not have cost_per_unit
                $sql = "INSERT INTO inventory (item_name, category, unit, stock_qty, reorder_level, status, shelf_life_days, date_received, expiry_date, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssiissss", $item_name, $category, $unit, $stock_qty, $reorder_level, $status, $shelf_life_days, $date_received, $expiry_date);
                    if ($stmt->execute()) {
                        // SUCCESS LOGIC REMOVED
                    } else {
                        if ($conn->errno == 1062) {
                            // ERROR LOGIC REMOVED
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
    
    header("Location: inventory.php");
    exit();
}
// --- END: INTEGRATED ADD/EDIT INVENTORY LOGIC ---


// --- START: PAGE DATA FETCHING ---
// Fetch all inventory items
$result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");
$inventory = $result->fetch_all(MYSQLI_ASSOC);

// Calculate Total Inventory Cost and update inventory stats
$totalInventoryCost = 0;
foreach ($inventory as $item) {
    $cost = $item['cost_per_unit'] ?? 0;
    $qty = $item['stock_qty'] ?? 0;
    $totalInventoryCost += $qty * $cost;
}

// Dashboard Stats
$totalItems = count($inventory);
$criticalItems = $conn->query("SELECT COUNT(*) AS c FROM inventory WHERE stock_qty <= reorder_level")->fetch_assoc()['c']; 

// Fast Moving (80% of current stock quantity was moved OUT in the last 7 days.)
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

// Slow Moving (No OUT movements in the last 60 days AND the current stock is NOT low/critical.)
$slow_moving_count = $conn->query("SELECT COUNT(i.id) as c 
    FROM inventory i
    LEFT JOIN (
        SELECT DISTINCT product_id 
        FROM stock_movements 
        WHERE movement_type='out' AND created_at >= DATE_SUB(NOW(), INTERVAL 60 DAY) 
    ) sm ON i.id = sm.product_id
    WHERE sm.product_id IS NULL AND i.stock_qty > 0 AND i.stock_qty > i.reorder_level
")->fetch_assoc()['c'];

// Flash Message Handling (Display and clear messages) - **REMOVED**
// $success_message = $_SESSION['success'] ?? null;
// $error_message = $_SESSION['error'] ?? null;
// unset($_SESSION['success'], $_SESSION['error']); 
// --- END: PAGE DATA FETCHING ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="Cache-Control" content="no-store, must-revalidate">
<title>Inventory Management - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
<script>
// Apply sidebar state BEFORE body renders to prevent layout shift
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
    <!-- Modern Gradient Inventory Header -->
    <div class="dashboard-header">
        <div class="dashboard-header-content">
            <h1><i class='bx bx-package'></i> Inventory Management</h1>
            <p class="dashboard-subtitle">Manage your stock levels • <?= date('F j, Y') ?></p>
        </div>
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
            <div class="stat-icon bg-info-light"><i class='bx bx-package icon-info'></i></div>
            <div class="stat-info">
                <h3><?= $totalItems ?></h3>
                <p>Total Items</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-danger-light"><i class='bx bx-error icon-danger'></i></div>
            <div class="stat-info">
                <h3><?= $criticalItems ?></h3>
                <p>Critical Stock</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon bg-success-light"><i class='bx bx-dollar-circle icon-success'></i></div>
            <div class="stat-info">
                <h3>₱<?= number_format($totalInventoryCost, 2) ?></h3>
                <p>Total Inventory Cost</p>
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
            <h2>Inventory Items</h2>
            <button class="btn-primary" onclick="showAddModal()"><i class='bx bx-plus'></i> Add Item</button>
        </div>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Code</th> 
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Stock Qty</th>
                        <th>Cost Per Unit</th> 
                        <th>Amount</th> 
                        <th>Reorder Level</th>
                        <th>Shelf Life</th>
                        <th>Expiry Date</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): 
                        // Set defaults for potentially missing columns from old data
                        $stock_qty = $item['stock_qty'] ?? 0;
                        $cost_per_unit = $item['cost_per_unit'] ?? 0;
                        $reorder_level = $item['reorder_level'] ?? 0;
                        $item_id = $item['id']; // Get the item ID for the movement function
                        $shelf_life_days = $item['shelf_life_days'] ?? null;
                        $expiry_date = $item['expiry_date'] ?? null;
                        $date_received = $item['date_received'] ?? null;

                        // Calculation for Amount
                        $amount = $stock_qty * $cost_per_unit;
                        
                        // Status determination based on Reorder Level (recalculating dynamically)
                        $status = 'Sufficient';
                        $class = 'badge-success';
                        
                        // Check expiry status first
                        $expiry_status = '';
                        if ($expiry_date) {
                            $days_to_expiry = (strtotime($expiry_date) - time()) / (60 * 60 * 24);
                            if ($days_to_expiry < 0) {
                                $status = 'Expired';
                                $class = 'badge-danger';
                                $expiry_status = '⚠️ EXPIRED';
                            } elseif ($days_to_expiry <= 3) {
                                $expiry_status = '⚠️ Expires Soon';
                            }
                        }
                        
                        // Then check stock levels (if not expired)
                        if ($status !== 'Expired') {
                            if ($stock_qty <= 0) {
                                $status = 'Out of Stock';
                                $class = 'badge-danger';
                            } elseif ($stock_qty <= $reorder_level) {
                                $status = 'Low Stock';
                                $class = 'badge-warning';
                            }
                        }

                        // Re-order Level status (if stock is near/at reorder level)
                        $reorder_status = $stock_qty <= $reorder_level ? 'Critical' : 'Safe';
                    ?>
                    <tr>
                        <td class="link-inventory" onclick="showLogModal(<?= $item_id ?>)">
                            <strong><?= htmlspecialchars($item['item_code'] ?? 'N/A') ?></strong>
                        </td>
                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                        <td><?= htmlspecialchars($item['category']) ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? 'N/A') ?></td>
                        <td><?= number_format($stock_qty) ?></td>
                        <td>₱<?= number_format($cost_per_unit, 2) ?></td>
                        <td>₱<?= number_format($amount, 2) ?></td>
                        <td><?= number_format($reorder_level) ?> <span class="badge badge-info"><?= $reorder_status ?></span></td>
                        <td><?= $shelf_life_days ? $shelf_life_days . ' days' : 'N/A' ?></td>
                        <td>
                            <?php if ($expiry_date): ?>
                                <?= date('M d, Y', strtotime($expiry_date)) ?>
                                <?php if ($expiry_status): ?>
                                    <br><small class="color-red font-weight-bold"><?= $expiry_status ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $class ?>"><?= htmlspecialchars($status) ?></span>
                        </td>
                        <td><?= date('M d, Y', strtotime($item['last_updated'] ?? 'now')) ?></td>
                        <td>
                            <button class="btn-icon" title="Edit Item Details"
                                onclick="showEditModal(
                                    <?= $item_id ?>, 
                                    '<?= htmlspecialchars($item['item_code'] ?? '') ?>', 
                                    '<?= htmlspecialchars($item['item_name']) ?>', 
                                    '<?= htmlspecialchars($item['category']) ?>', 
                                    '<?= htmlspecialchars($item['unit']) ?>', 
                                    <?= $cost_per_unit ?>, 
                                    <?= $reorder_level ?>,
                                    <?= $shelf_life_days ?? 'null' ?>,
                                    '<?= htmlspecialchars($date_received ?? '') ?>'
                                )">
                                <i class='bx bx-edit'></i>
                            </button>
                            <button class="btn-icon" title="Stock In/Out" 
                                onclick="showMovementModal(<?= $item_id ?>, '<?= htmlspecialchars($item['item_name']) ?>', <?= $stock_qty ?>)">
                                <i class='bx bx-log-in-circle'></i>
                            </button>
                                     <a href="?delete_id=<?= $item_id ?>" 
                                         class="btn-icon delete-icon"
                                         title="Remove Item"
                                         onclick="return confirm('Are you sure you want to permanently remove <?= htmlspecialchars($item['item_name']) ?>? This action cannot be undone.');">
                                          <i class='bx bx-trash'></i>
                                     </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Item</h2>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form action="" method="POST">
             <div class="form-group">
                <label>Item Code *</label> 
                <input type="text" name="item_code" required>
            </div>
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" required>
            </div>
            <div class="form-row form-gap">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="Perishable">Perishable</option>
                        <option value="Pre-pack Frozen Food">Pre-pack Frozen Food</option>
                        <option value="Condiments">Condiments</option>
                        <option value="Beverages">Beverages</option>
                        <option value="Raw">Raw</option>
                        </select>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <select name="unit" required>
                        <option value="">Select Unit</option>
                        <option value="g">g</option>
                        <option value="kg">kg</option>
                        <option value="mL">mL</option>
                        <option value="L">L</option>
                    </select>
                </div>
            </div>
            <div class="form-row form-gap">
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock_qty" required min="0">
                </div>
                <div class="form-group">
                    <label>Cost Per Unit *</label> 
                    <input type="number" name="cost_per_unit" step="0.01" required min="0">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Reorder Level *</label>
                    <input type="number" name="reorder_level" required min="1">
                </div>
            </div>
            <div class="form-row form-gap">
                <div class="form-group">
                    <label>Shelf Life (Days)</label>
                    <input type="number" name="shelf_life_days" min="1" placeholder="e.g., 7, 30, 90">
                    <small class="color-666">Optional: Enter days until item expires</small>
                </div>
                <div class="form-group">
                    <label>Date Received</label>
                    <input type="date" name="date_received" value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<div id="movementModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="movementTitle">Adjust Stock for [Item Name]</h2>
            <span class="close" onclick="closeMovementModal()">&times;</span>
        </div>
        <form action="" method="POST">
            <input type="hidden" name="product_id" id="movementProductId">
            <p>Current Stock: <strong id="currentStockDisplay">0</strong></p>

            <div class="form-group">
                <label>Movement Type</label>
                <div class="form-row form-gap">
                    <label class="label-normal"><input type="radio" name="movement_type" value="in" required> <span>Stock In (Add)</span></label>
                    <label class="label-normal"><input type="radio" name="movement_type" value="out" required checked> <span>Stock Out (Remove)</span></label>
                </div>
            </div>
            
             <div class="form-group">
                <label>Quantity to Move *</label> 
                <input type="number" name="quantity" required min="1">
            </div>
            <div class="form-group">
                <label>Reference Number (Receipt/Adj. Note)</label>
                <input type="text" name="reference_number" placeholder="Enter receipt or reference number (Optional)">
            </div>
            <div class="form-group">
                <label>Remarks / Reason (e.g., Inventory, Damage, Sale)</label>
                <input type="text" name="remarks" placeholder="Enter reason for adjustment">
            </div>
           
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeMovementModal()">Cancel</button>
                <button type="submit" class="btn-primary">Apply Adjustment</button>
            </div>
        </form>
    </div>
</div>

<div id="editModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Item: <span id="editItemNameDisplay"></span></h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form action="" method="POST" id="editItemForm">
             <input type="hidden" name="item_id" id="editItemId"> 

             <div class="form-group">
                <label>Item Code *</label> 
                <input type="text" name="item_code" id="editItemCode" required>
            </div>
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="editItemName" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" id="editCategory" required>
                        <option value="">Select Category</option>
                        <option value="Perishable">Perishable</option>
                        <option value="Pre-pack Frozen Food">Pre-pack Frozen Food</option>
                        <option value="Condiments">Condiments</option>
                        <option value="Beverages">Beverages</option>
                        <option value="Raw">Raw</option>
                        </select>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <select name="unit" id="editUnit" required>
                        <option value="">Select Unit</option>
                        <option value="g">g</option>
                        <option value="kg">kg</option>
                        <option value="mL">mL</option>
                        <option value="L">L</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Cost Per Unit *</label> 
                    <input type="number" name="cost_per_unit" id="editCostPerUnit" step="0.01" required min="0">
                </div>
                <div class="form-group">
                    <label>Reorder Level *</label>
                    <input type="number" name="reorder_level" id="editReorderLevel" required min="1">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Shelf Life (Days)</label>
                    <input type="number" name="shelf_life_days" id="editShelfLifeDays" min="1" placeholder="e.g., 7, 30, 90">
                </div>
                <div class="form-group">
                    <label>Date Received</label>
                    <input type="date" name="date_received" id="editDateReceived">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<div id="logModal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2>Stock Transactions for: <span id="logItemNameDisplay"></span></h2>
            <span class="close" onclick="closeLogModal()">&times;</span>
        </div>
        
        <p>Item Code: <strong id="logItemCodeDisplay"></strong> | Current Unit Cost: ₱<strong id="logCurrentCostDisplay"></strong></p>

        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>REF #</th>
                        <th>Particulars</th>
                        <th>Qty Moved</th>
                        <th>Unit Cost</th>
                        <th>Amount</th>
                        <th>IN</th>
                        <th>OUT</th>
                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr><td colspan="9" class="empty-message">Loading transactions...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeLogModal()">Close</button>
        </div>
    </div>
</div>
<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'flex';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function showMovementModal(itemId, itemName, currentStock) {
    document.getElementById('movementProductId').value = itemId;
    document.getElementById('movementTitle').innerText = 'Adjust Stock for ' + itemName;
    document.getElementById('currentStockDisplay').innerText = currentStock;
    // Reset Quantity field to avoid accidental submissions
    document.querySelector('#movementModal input[name="quantity"]').value = '';
    // FIX: Clear reference and remarks fields on open
    document.querySelector('#movementModal input[name="reference_number"]').value = '';
    document.querySelector('#movementModal input[name="remarks"]').value = '';
    document.getElementById('movementModal').style.display = 'flex';
}
function closeMovementModal() {
    document.getElementById('movementModal').style.display = 'none';
}

// NEW FUNCTIONS FOR EDIT MODAL
function showEditModal(id, code, name, category, unit, cost, reorder, shelfLife, dateReceived) {
    document.getElementById('editItemId').value = id;
    document.getElementById('editItemNameDisplay').innerText = name;
    document.getElementById('editItemCode').value = code;
    document.getElementById('editItemName').value = name;
    document.getElementById('editCategory').value = category;
    document.getElementById('editUnit').value = unit;
    document.getElementById('editCostPerUnit').value = cost.toFixed(2); // Format cost
    document.getElementById('editReorderLevel').value = reorder;
    document.getElementById('editShelfLifeDays').value = shelfLife || '';
    document.getElementById('editDateReceived').value = dateReceived || '';
    
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// START: NEW FUNCTIONS FOR STOCK LOG MODAL (MISSING COMPONENT)
function closeLogModal() {
    document.getElementById('logModal').style.display = 'none';
}

function showLogModal(itemId) {
    const logModal = document.getElementById('logModal');
    const tableBody = document.getElementById('logTableBody');
    
    // Show modal immediately with loading message
    tableBody.innerHTML = '<tr><td colspan="9" class="empty-message">Loading transactions...</td></tr>';
    logModal.style.display = 'flex';

    // Fetch data via AJAX
    fetch(`inventory.php?action=fetch_transactions&item_id=${itemId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('logItemNameDisplay').innerText = data.item_name;
                document.getElementById('logItemCodeDisplay').innerText = data.item_code;
                document.getElementById('logCurrentCostDisplay').innerText = data.current_unit_cost.toFixed(2);

                tableBody.innerHTML = ''; // Clear loading message

                if (data.transactions.length === 0) {
                    tableBody.innerHTML = '<tr><td colspan="9" class="empty-message">No stock movements logged for this item.</td></tr>';
                    return;
                }

                data.transactions.forEach(txn => {
                    const row = `
                        <tr>
                            <td>${txn.date}</td>
                            <td>${txn.ref_num}</td>
                            <td>${txn.particulars}</td>
                            <td>${txn.quantity}</td>
                            <td>₱${txn.unit_cost}</td>
                            <td>₱${txn.amount}</td>
                            <td>${txn.in}</td>
                            <td>${txn.out}</td>
                            <td>${txn.balance}</td>
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
            } else {
                 tableBody.innerHTML = `<tr><td colspan="9" class="empty-message">Error: ${data.message}</td></tr>`;
            }
        })
        .catch(error => {
            console.error('Error fetching transactions:', error);
            tableBody.innerHTML = '<tr><td colspan="9" class="empty-message">An error occurred while fetching data.</td></tr>';
        });
}
// END: NEW FUNCTIONS FOR STOCK LOG MODAL
</script>

</body>
</html>