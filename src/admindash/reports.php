<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Get date filters
$from_date = isset($_POST['from_date']) ? $_POST['from_date'] : date('Y-m-d', strtotime('-30 days'));
$to_date = isset($_POST['to_date']) ? $_POST['to_date'] : date('Y-m-d');

// Fetch sales reports within date range
$query = "
    SELECT 
        s.sale_id,
        s.sale_number,
        s.sale_date,
        s.total_amount,
        s.payment_method,
        s.status,
        u.username as staff_name,
        COUNT(si.sale_item_id) as item_count
    FROM sales s
    LEFT JOIN users u ON s.created_by = u.user_id
    LEFT JOIN sale_items si ON s.sale_id = si.sale_id
    WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
    GROUP BY s.sale_id
    ORDER BY s.sale_date DESC
";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $from_date, $to_date);
$stmt->execute();
$sales_reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate summary statistics
$total_sales = 0;
$total_discount = 0;
$payment_breakdown = ['CASH' => 0, 'GCASH' => 0, 'CARD' => 0, 'OTHER' => 0];
$discount_breakdown = ['DISCOUNTED' => 0, 'NO_DISCOUNT' => 0];

foreach ($sales_reports as $sale) {
    $total_sales += $sale['total_amount'];
    
    $payment = $sale['payment_method'] ?? 'OTHER';
    if (isset($payment_breakdown[$payment])) {
        $payment_breakdown[$payment] += $sale['total_amount'];
    } else {
        $payment_breakdown['OTHER'] += $sale['total_amount'];
    }
    
    // Simple discount tracking based on payment method or other criteria
    $discount_breakdown['NO_DISCOUNT']++;
}

$total_transactions = count($sales_reports);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, must-revalidate">
    <title>Sales Reports - Shukran Café</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

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

    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
    <link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Sales Reports</h1>
    </div>

    <!-- Date Filter -->
    <div class="report-filter">
        <div class="form-group">
            <label>From Date</label>
            <form method="POST" id="filterForm" class="display-none"></form>
            <input type="date" id="fromDate" value="<?= $from_date ?>" form="filterForm" name="from_date">
        </div>
        <div class="form-group">
            <label>To Date</label>
            <input type="date" id="toDate" value="<?= $to_date ?>" form="filterForm" name="to_date">
        </div>
        <button onclick="applyFilter()" form="filterForm" type="submit">Apply Filter</button>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-grid">
        <div class="summary-card">
            <h4>Total Transactions</h4>
            <div class="value"><?= $total_transactions ?></div>
        </div>
        <div class="summary-card">
            <h4>Total Sales</h4>
            <div class="value">₱<?= number_format($total_sales, 2) ?></div>
        </div>
        <div class="summary-card">
            <h4>Average Sale</h4>
            <div class="value">₱<?= number_format($total_transactions > 0 ? $total_sales / $total_transactions : 0, 2) ?></div>
        </div>
        <div class="summary-card">
            <h4>Date Range</h4>
            <div class="value"><?= date('M d', strtotime($from_date)) ?> - <?= date('M d, Y', strtotime($to_date)) ?></div>
        </div>
    </div>

    <!-- Payment & Discount Breakdown -->
    <div class="payment-breakdown">
        <div class="breakdown-card">
            <h4>💳 Payment Method Breakdown</h4>
            <?php foreach ($payment_breakdown as $method => $amount): ?>
            <div class="breakdown-item">
                <span class="breakdown-label"><?= $method ?></span>
                <span class="breakdown-value">₱<?= number_format($amount, 2) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="breakdown-card">
            <h4>🎟️ Discount Type Breakdown</h4>
            <?php foreach ($discount_breakdown as $type => $count): ?>
            <div class="breakdown-item">
                <span class="breakdown-label"><?= str_replace('_', ' ', $type) ?></span>
                <span class="breakdown-value"><?= $count ?> transaction(s)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Sales Details Table -->
    <div class="content-card">
        <div class="card-header">
            <h2>📋 Detailed Sales Report (<?= $total_transactions ?> Records)</h2>
            <a href="#" onclick="printReport()" class="link-gray">
                🖨️ Print Report →
            </a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Order No.</th>
                        <th>Items</th>
                        <th>Total Amount</th>
                        <th>Payment Method</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($sales_reports)): ?>
                    <tr><td colspan="6" class="empty-message">No sales records found for the selected date range.</td></tr>
                    <?php else: ?>
                        <?php foreach ($sales_reports as $sale): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                            <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                            <td class="text-center"><?= $sale['item_count'] ?> item(s)</td>
                            <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                            <td>
                                <span class="badge badge-primary"><?= htmlspecialchars($sale['payment_method'] ?? 'N/A') ?></span>
                            </td>
                            <td><?= htmlspecialchars($sale['staff_name'] ?? 'System') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function applyFilter() {
    document.getElementById('filterForm').submit();
}

function printReport() {
    window.print();
}
</script>

</body>
</html>
