<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// --- START: AJAX ENDPOINT 1 - FETCH SALE ITEMS ---
if (isset($_GET['action']) && $_GET['action'] === 'fetch_sale_items' && isset($_GET['sale_id'])) {
    $sale_id = (int)$_GET['sale_id'];

    // Join sale_items (s_i) with products (p) to get item names.
    $stmt = $conn->prepare("
        SELECT 
            s_i.sale_item_id,
            s_i.quantity, 
            s_i.unit_cost_at_sale,
            s_i.markup_rate,
            s_i.unit_price, -- SELLING PRICE
            s_i.subtotal,   -- TOTAL SALES
            p.product_name
        FROM sale_items s_i
        JOIN products p ON s_i.product_id = p.product_id
        WHERE s_i.sale_id = ?
    ");
    $stmt->bind_param("i", $sale_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'items' => $items]);
    exit();
}
// --- END: AJAX ENDPOINT 1 ---


// --- START: AJAX ENDPOINT 2 - UPDATE MARKUP (RECALCULATES SELLING PRICE and TOTAL SALES) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_markup'])) {
    $sale_item_id = (int)$_POST['sale_item_id'];
    $new_markup_rate = (float)$_POST['markup_rate']; // Passed as a decimal (e.g., 0.20)
    
    // 1. Fetch item cost and quantity
    $stmt_fetch = $conn->prepare("SELECT quantity, unit_cost_at_sale FROM sale_items WHERE sale_item_id = ?");
    $stmt_fetch->bind_param("i", $sale_item_id);
    $stmt_fetch->execute();
    $item_data = $stmt_fetch->get_result()->fetch_assoc();
    $stmt_fetch->close();
    
    if ($item_data) {
        $unit_cost = (float)$item_data['unit_cost_at_sale'];
        $quantity = (float)$item_data['quantity'];
        
        // Calculation: SELLING PRICE = UNIT COST * (1 + MARKUP RATE)
        $new_unit_price = $unit_cost * (1 + $new_markup_rate);
        
        // Calculation: TOTAL SALES = SELLING PRICE * QUANTITY
        $new_subtotal = $new_unit_price * $quantity;
        
        // 2. Update the sale_items row
        $stmt_update = $conn->prepare("
            UPDATE sale_items 
            SET markup_rate = ?, unit_price = ?, subtotal = ? 
            WHERE sale_item_id = ?
        ");
        $stmt_update->bind_param("dddi", $new_markup_rate, $new_unit_price, $new_subtotal, $sale_item_id);
        $stmt_update->execute();
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
    echo json_encode(['success' => false, 'message' => 'Item not found or calculation failed.']);
    exit();
}
// --- END: MARKUP UPDATE ENDPOINT ---


// Handle validation action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_sale'])) {
    $sale_id = (int)$_POST['sale_id'];
    $admin_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE sales SET status='completed', validated_by=?, validated_at=NOW() WHERE sale_id=?");
    $stmt->bind_param("ii", $admin_id, $sale_id);
    $stmt->execute();
    header("Location: sales_validation.php?msg=validated");
    exit();
}

// Fetch pending sales
$pending_sales = $conn->query("
    SELECT s.*, u.username as staff_name 
    FROM sales s 
    LEFT JOIN users u ON s.created_by = u.user_id 
    WHERE s.status = 'pending' 
    ORDER BY s.created_at DESC
")->fetch_all(MYSQLI_ASSOC);

// Fetch validated sales (recent 10)
$validated_sales = $conn->query("
    SELECT s.*, u.username as staff_name, v.username as validator_name
    FROM sales s 
    LEFT JOIN users u ON s.created_by = u.user_id 
    LEFT JOIN users v ON s.validated_by = v.user_id
    WHERE s.status = 'completed' 
    ORDER BY s.validated_at DESC 
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

$total_pending = count($pending_sales);
$total_validated = $conn->query("SELECT COUNT(*) as c FROM sales WHERE status='completed'")->fetch_assoc()['c'];
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
            <form id="validationForm" method="POST" action="" class="inline-form">
                <input type="hidden" name="sale_id" id="validationSaleId">
                <button type="submit" name="validate_sale" class="btn-primary">
                    <i class='bx bx-check-circle'></i> Mark as Validated
                </button>
            </form>
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
    modal.style.display = 'block';

    fetch(`sales_validation.php?action=fetch_sale_items&sale_id=${saleId}`)
        .then(response => response.json())
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
                        </tr>
                    `;
                    tableBody.innerHTML += row;
                });
              } else {
                  tableBody.innerHTML = '<tr><td colspan="7" class="empty-message">No item details found for this sale.</td></tr>';
              }
        })
        .catch(error => {
            console.error('Error fetching transactions:', error);
            tableBody.innerHTML = '<tr><td colspan="7" class="empty-message">An error occurred while fetching data.</td></tr>';
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
</script>

</body>
</html>