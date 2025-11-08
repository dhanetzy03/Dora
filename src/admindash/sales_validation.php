<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

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
<title>Sales Validation - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>Sales Validation</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <?php if (isset($_GET['msg']) && $_GET['msg'] == 'validated'): ?>
    <div style="margin: 20px 30px; padding: 15px; background: #d4edda; color: #155724; border-radius: 8px;">
        ✅ Sale validated successfully!
    </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #fff3cd;"><i class='bx bx-time' style="color: #856404;"></i></div>
            <div class="stat-info">
                <h3><?= $total_pending ?></h3>
                <p>Pending Validation</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #d4edda;"><i class='bx bx-check-circle' style="color: #155724;"></i></div>
            <div class="stat-info">
                <h3><?= $total_validated ?></h3>
                <p>Total Validated</p>
            </div>
        </div>
    </div>

    <!-- Pending Sales -->
    <div class="content-card">
        <div class="card-header">
            <h2>Pending Sales (Require Validation)</h2>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sale #</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_sales)): ?>
                    <tr><td colspan="8" style="text-align: center; padding: 30px; color: #999;">No pending sales to validate</td></tr>
                    <?php else: ?>
                    <?php foreach ($pending_sales as $sale): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($sale['sale_number']) ?></strong></td>
                        <td><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?></td>
                        <td><?= htmlspecialchars($sale['staff_name']) ?></td>
                        <td><?= htmlspecialchars($sale['customer_name'] ?: 'Walk-in') ?></td>
                        <td><strong>₱<?= number_format($sale['total_amount'], 2) ?></strong></td>
                        <td><?= ucfirst($sale['payment_method']) ?></td>
                        <td><span class="badge badge-warning">Pending</span></td>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="sale_id" value="<?= $sale['sale_id'] ?>">
                                <button type="submit" name="validate_sale" class="btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                    <i class='bx bx-check'></i> Validate
                                </button>
                            </form>
                            <button class="btn-icon" onclick="viewDetails(<?= $sale['sale_id'] ?>)" title="View Details">
                                <i class='bx bx-show'></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Validated Sales -->
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

<script>
function viewDetails(saleId) {
    alert('View sale details functionality - Sale ID: ' + saleId);
    // Can implement modal to show sale items details
}
</script>

</body>
</html>
