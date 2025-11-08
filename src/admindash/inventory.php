<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Fetch all inventory items
$result = $conn->query("SELECT * FROM inventory ORDER BY id DESC");
$inventory = $result->fetch_all(MYSQLI_ASSOC);

// Dashboard Stats
$totalItems = count($inventory);
$criticalItems = $conn->query("SELECT COUNT(*) AS c FROM inventory WHERE status = 'Low Stock' OR status = 'Out of Stock'")->fetch_assoc()['c'];
$totalValue = 0; // Can calculate based on price if added later
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Inventory Management - Shukran Café</title>
<link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
<link rel="stylesheet" href="../styles/admin-style.css">
</head>
<body>

<?php include 'sidebar.php'; ?>

<div class="main-content">
    <div class="top-bar">
        <h1>📦 Inventory Management</h1>
        <div class="user-info">
            <span>Welcome, <?= htmlspecialchars($_SESSION["username"]) ?></span>
            <a href="../auth/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background: #e3f2fd;"><i class='bx bx-package' style="color: #2196f3;"></i></div>
            <div class="stat-info">
                <h3><?= $totalItems ?></h3>
                <p>Total Items</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #ffebee;"><i class='bx bx-error' style="color: #f44336;"></i></div>
            <div class="stat-info">
                <h3><?= $criticalItems ?></h3>
                <p>Critical Stock</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background: #e8f5e9;"><i class='bx bx-trending-up' style="color: #4caf50;"></i></div>
            <div class="stat-info">
                <h3><?= $totalItems - $criticalItems ?></h3>
                <p>Sufficient Stock</p>
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
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Stock Qty</th>
                        <th>Unit</th>
                        <th>Reorder Level</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['item_name']) ?></strong></td>
                        <td><?= htmlspecialchars($item['category']) ?></td>
                        <td><?= htmlspecialchars($item['stock_qty']) ?></td>
                        <td><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></td>
                        <td><?= htmlspecialchars($item['reorder_level']) ?></td>
                        <td>
                            <?php
                                $status = $item['status'];
                                $class = $status === 'Sufficient' ? 'badge-success' : ($status === 'Low Stock' ? 'badge-warning' : 'badge-danger');
                            ?>
                            <span class="badge <?= $class ?>"><?= htmlspecialchars($status) ?></span>
                        </td>
                        <td><?= date('M d, Y', strtotime($item['last_updated'])) ?></td>
                        <td>
                            <button class="btn-icon" title="Edit"><i class='bx bx-edit'></i></button>
                            <button class="btn-icon" title="Delete"><i class='bx bx-trash'></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div id="addModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Add New Item</h2>
            <span class="close" onclick="closeAddModal()">&times;</span>
        </div>
        <form action="add_inventory.php" method="POST">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category *</label>
                    <select name="category" required>
                        <option value="">Select Category</option>
                        <option value="Beverages">Beverages</option>
                        <option value="Food Items">Food Items</option>
                        <option value="Ingredients">Ingredients</option>
                        <option value="Supplies">Supplies</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <input type="text" name="unit" value="pcs" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock_qty" required>
                </div>
                <div class="form-group">
                    <label>Reorder Level *</label>
                    <input type="number" name="reorder_level" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-primary">Add Item</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddModal() {
    document.getElementById('addModal').style.display = 'block';
}
function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}
</script>

</body>
</html>
