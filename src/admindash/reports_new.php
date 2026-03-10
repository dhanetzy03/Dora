<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Get report type and date filters
$report_type = $_GET['type'] ?? 'sales';
$from_date = $_POST['from_date'] ?? $_GET['from_date'] ?? date('Y-m-d', strtotime('-30 days'));
$to_date = $_POST['to_date'] ?? $_GET['to_date'] ?? date('Y-m-d');

// Initialize report data
$report_data = [];
$report_title = '';
$summary_stats = [];

switch ($report_type) {
    case 'sales':
        $report_title = 'Sales Report';
        $query = "SELECT s.*, u.username as staff_name FROM sales s 
                  LEFT JOIN users u ON s.created_by = u.user_id 
                  WHERE DATE(s.sale_date) BETWEEN ? AND ? 
                  ORDER BY s.sale_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $total = 0;
        foreach ($report_data as $row) $total += $row['total_amount'];
        $summary_stats = [
            'Total Sales' => '₱' . number_format($total, 2),
            'Transactions' => count($report_data),
            'Average Sale' => count($report_data) > 0 ? '₱' . number_format($total / count($report_data), 2) : '₱0.00'
        ];
        break;

    case 'inventory_movement':
        $report_title = 'Inventory Movement Report';
        $query = "SELECT sm.*, i.item_name, i.unit, u.username as staff_name 
                  FROM stock_movements sm 
                  LEFT JOIN inventory i ON sm.product_id = i.id
                  LEFT JOIN users u ON sm.created_by = u.user_id 
                  WHERE DATE(sm.created_at) BETWEEN ? AND ? 
                  ORDER BY sm.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $total_in = 0;
        $total_out = 0;
        foreach ($report_data as $row) {
            if ($row['movement_type'] == 'in') $total_in += $row['quantity'];
            else $total_out += $row['quantity'];
        }
        $summary_stats = [
            'Total Movements' => count($report_data),
            'Total IN' => number_format($total_in, 2),
            'Total OUT' => number_format($total_out, 2)
        ];
        break;

    case 'spoilage':
        $report_title = 'Spoilage Report';
        $query = "SELECT sr.*, u.username as staff_name 
                  FROM spoilage_records sr 
                  LEFT JOIN users u ON sr.recorded_by = u.user_id 
                  WHERE DATE(sr.date_spoiled) BETWEEN ? AND ? 
                  ORDER BY sr.date_spoiled DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $from_date, $to_date);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $total_loss = 0;
        $total_qty = 0;
        foreach ($report_data as $row) {
            $total_loss += $row['total_loss'];
            $total_qty += $row['quantity_spoiled'];
        }
        $summary_stats = [
            'Total Spoilage Records' => count($report_data),
            'Total Quantity Spoiled' => number_format($total_qty, 2),
            'Total Value Loss' => '₱' . number_format($total_loss, 2)
        ];
        break;

    case 'expiry_alert':
        $report_title = 'Expiry Alert Report';
        $days_ahead = 7; // Show items expiring within 7 days or already expired
        $query = "SELECT * FROM inventory 
                  WHERE expiry_date IS NOT NULL 
                  AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  ORDER BY expiry_date ASC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $days_ahead);
        $stmt->execute();
        $report_data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $expired = 0;
        $expiring_soon = 0;
        foreach ($report_data as $row) {
            if (strtotime($row['expiry_date']) < time()) $expired++;
            else $expiring_soon++;
        }
        $summary_stats = [
            'Expired Items' => $expired,
            'Expiring Soon (7 days)' => $expiring_soon,
            'Total Items at Risk' => count($report_data)
        ];
        break;

    case 'stock_level':
        $report_title = 'Stock Level Report';
        $report_data = $conn->query("SELECT * FROM inventory ORDER BY stock_qty ASC")->fetch_all(MYSQLI_ASSOC);
        
        $low_stock = 0;
        $out_of_stock = 0;
        $sufficient = 0;
        foreach ($report_data as $row) {
            if ($row['stock_qty'] <= 0) $out_of_stock++;
            elseif ($row['stock_qty'] <= $row['reorder_level']) $low_stock++;
            else $sufficient++;
        }
        $summary_stats = [
            'Out of Stock' => $out_of_stock,
            'Low Stock' => $low_stock,
            'Sufficient Stock' => $sufficient,
            'Total Items' => count($report_data)
        ];
        break;

    case 'beginning_ending':
        $report_title = 'Beginning & Ending Inventory Report';
        $selected_date = $_GET['snapshot_date'] ?? date('Y-m-d');
        
        $stmt_b = $conn->prepare("SELECT * FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'beginning' ORDER BY item_name");
        $stmt_b->bind_param('s', $selected_date);
        $stmt_b->execute();
        $beginning = $stmt_b->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_b->close();
        
        $stmt_e = $conn->prepare("SELECT * FROM inventory_snapshots WHERE snapshot_date = ? AND snapshot_type = 'ending' ORDER BY item_name");
        $stmt_e->bind_param('s', $selected_date);
        $stmt_e->execute();
        $ending = $stmt_e->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_e->close();
        
        $report_data = ['beginning' => $beginning, 'ending' => $ending];
        
        $total_beginning = array_sum(array_column($beginning, 'total_value'));
        $total_ending = array_sum(array_column($ending, 'total_value'));
        $change = $total_ending - $total_beginning;
        
        $summary_stats = [
            'Beginning Inventory' => '₱' . number_format($total_beginning, 2),
            'Ending Inventory' => '₱' . number_format($total_ending, 2),
            'Change' => '₱' . number_format($change, 2) . ' (' . number_format(($change / max($total_beginning, 1)) * 100, 1) . '%)'
        ];
        break;

    default:
        $report_title = 'Sales Report';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $report_title ?> - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
<link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <!-- Modern Gradient Reports Header -->
    <div class="dashboard-header no-print">
        <div class="dashboard-header-content">
            <h1><i class='bx bx-line-chart'></i> Reports & Analytics</h1>
            <p class="dashboard-subtitle">Comprehensive business insights • <?= date('F j, Y') ?></p>
        </div>
    </div>

    <div class="report-menu no-print">
        <a href="?type=sales" class="<?= $report_type == 'sales' ? 'active' : '' ?>">💰 Sales Report</a>
        <a href="?type=inventory_movement" class="<?= $report_type == 'inventory_movement' ? 'active' : '' ?>">📦 Inventory Movement</a>
        <a href="?type=spoilage" class="<?= $report_type == 'spoilage' ? 'active' : '' ?>">🗑️ Spoilage Report</a>
        <a href="?type=expiry_alert" class="<?= $report_type == 'expiry_alert' ? 'active' : '' ?>">⚠️ Expiry Alerts</a>
        <a href="?type=stock_level" class="<?= $report_type == 'stock_level' ? 'active' : '' ?>">📊 Stock Levels</a>
        <a href="?type=beginning_ending" class="<?= $report_type == 'beginning_ending' ? 'active' : '' ?>">📈 Beginning/Ending</a>
    </div>

    <?php if ($report_type != 'beginning_ending' && $report_type != 'stock_level' && $report_type != 'expiry_alert'): ?>
    <form method="POST" class="filter-form no-print">
        <div>
            <label>From Date</label>
            <input type="date" name="from_date" value="<?= $from_date ?>" required>
        </div>
        <div>
            <label>To Date</label>
            <input type="date" name="to_date" value="<?= $to_date ?>" required>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
        <a href="javascript:window.print()" class="print-btn">🖨️ Print Report</a>
    </form>
    <?php endif; ?>

    <div class="summary-grid">
        <?php foreach ($summary_stats as $label => $value): ?>
            <div class="summary-card">
                <h3><?= $label ?></h3>
                <div class="value"><?= $value ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="content-card">
        <h2><?= $report_title ?> (<?= date('M d, Y', strtotime($from_date)) ?> - <?= date('M d, Y', strtotime($to_date)) ?>)</h2>
        
        <?php if ($report_type == 'sales'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Sale #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($row['sale_date'])) ?></td>
                            <td><?= htmlspecialchars($row['sale_number']) ?></td>
                            <td><?= htmlspecialchars($row['customer_name']) ?></td>
                            <td>₱<?= number_format($row['total_amount'], 2) ?></td>
                            <td><?= htmlspecialchars($row['payment_method']) ?></td>
                            <td><span class="badge badge-success"><?= ucfirst($row['status']) ?></span></td>
                            <td><?= htmlspecialchars($row['staff_name'] ?? 'N/A') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type == 'inventory_movement'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Prev Qty</th>
                        <th>New Qty</th>
                        <th>Reference</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['item_name'] ?? 'Unknown') ?></td>
                            <td><span class="badge badge-<?= $row['movement_type'] == 'in' ? 'success' : 'warning' ?>"><?= strtoupper($row['movement_type']) ?></span></td>
                            <td><?= number_format($row['quantity'], 2) ?></td>
                            <td><?= number_format($row['previous_quantity'], 2) ?></td>
                            <td><?= number_format($row['new_quantity'], 2) ?></td>
                            <td><?= htmlspecialchars($row['reference_number'] ?? $row['reference_type']) ?></td>
                            <td><?= htmlspecialchars($row['staff_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type == 'spoilage'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty Spoiled</th>
                        <th>Unit Cost</th>
                        <th>Total Loss</th>
                        <th>Reason</th>
                        <th>Details</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($row['date_spoiled'])) ?></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= ucfirst($row['item_type']) ?></td>
                            <td><?= number_format($row['quantity_spoiled'], 2) ?> <?= $row['unit'] ?></td>
                            <td>₱<?= number_format($row['cost_per_unit'], 2) ?></td>
                            <td class="loss-amount">₱<?= number_format($row['total_loss'], 2) ?></td>
                            <td><span class="badge badge-warning"><?= ucfirst($row['spoilage_reason']) ?></span></td>
                            <td><?= htmlspecialchars($row['reason_details'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($row['staff_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type == 'expiry_alert'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Stock Qty</th>
                        <th>Expiry Date</th>
                        <th>Days Remaining</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): 
                        $days_remaining = floor((strtotime($row['expiry_date']) - time()) / (60 * 60 * 24));
                        $status_class = $days_remaining < 0 ? 'badge-danger' : ($days_remaining <= 3 ? 'badge-warning' : 'badge-info');
                        $status_text = $days_remaining < 0 ? 'EXPIRED' : ($days_remaining <= 3 ? 'Critical' : 'Soon');
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['item_code'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= number_format($row['stock_qty'], 2) ?></td>
                            <td><?= date('M d, Y', strtotime($row['expiry_date'])) ?></td>
                            <td><?= $days_remaining ?> days</td>
                            <td><span class="badge <?= $status_class ?>"><?= $status_text ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type == 'stock_level'): ?>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item Code</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Stock Qty</th>
                        <th>Reorder Level</th>
                        <th>Cost Per Unit</th>
                        <th>Total Value</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($report_data as $row): 
                        $status_class = $row['stock_qty'] <= 0 ? 'badge-danger' : ($row['stock_qty'] <= $row['reorder_level'] ? 'badge-warning' : 'badge-success');
                        $total_value = $row['stock_qty'] * ($row['cost_per_unit'] ?? 0);
                    ?>
                        <tr>
                            <td><?= htmlspecialchars($row['item_code'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= htmlspecialchars($row['category']) ?></td>
                            <td><?= number_format($row['stock_qty'], 2) ?></td>
                            <td><?= number_format($row['reorder_level']) ?></td>
                            <td>₱<?= number_format($row['cost_per_unit'] ?? 0, 2) ?></td>
                            <td>₱<?= number_format($total_value, 2) ?></td>
                            <td><span class="badge <?= $status_class ?>"><?= $row['status'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        
        <?php elseif ($report_type == 'beginning_ending'): ?>
            <div class="display-grid grid-2-cols gap-20">
                <div>
                    <h3>Beginning Inventory</h3>
                    <table class="data-table table-small-text">
                        <thead>
                            <tr><th>Item</th><th>Qty</th><th>Cost</th><th>Value</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['beginning'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td><?= number_format($row['quantity'], 2) ?></td>
                                    <td>₱<?= number_format($row['cost_per_unit'], 2) ?></td>
                                    <td>₱<?= number_format($row['total_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3>Ending Inventory</h3>
                    <table class="data-table table-small-text">
                        <thead>
                            <tr><th>Item</th><th>Qty</th><th>Cost</th><th>Value</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['ending'] as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['item_name']) ?></td>
                                    <td><?= number_format($row['quantity'], 2) ?></td>
                                    <td>₱<?= number_format($row['cost_per_unit'], 2) ?></td>
                                    <td>₱<?= number_format($row['total_value'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
