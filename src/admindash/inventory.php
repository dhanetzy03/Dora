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
<style>
/* Remove underline under icon links */
.btn-icon { text-decoration: none; color: inherit; display: inline-block; }
.btn-icon:hover { text-decoration: none; }
.btn-icon i { vertical-align: middle; }

/* Flash notification styling */
.flash {
    padding: 12px 18px;
    border-radius: 8px;
    font-weight: 600;
    box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    max-width: 760px;
    width: 100%;
    text-align: center;
}
.flash-success { background: #e6ffed; color: #116530; }
.flash-error { background: #ffecec; color: #a10000; }

/* simple fade out */
.fade-out { animation: fadeOut 0.6s ease forwards; }
@keyframes fadeOut { to { opacity: 0; transform: translateY(-6px); }}
</style>
</head>
<body>

<?php include 'sidebar.php'; ?>
<?php
// Flash messages from add/update actions
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);
?>

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

    <!-- Flash notification area (appears under the stats cards) -->
    <div id="flashContainer" style="margin-top:16px;display:flex;justify-content:center;">
        <?php if ($error): ?>
            <div id="flash" class="flash flash-error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div id="flash" class="flash flash-success"><?= htmlspecialchars($success) ?></div>
        <?php else: ?>
            <div id="flash" class="flash" style="display:none;"></div>
        <?php endif; ?>
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
                                $status_db = $item['status'];
                                // Map DB status to display label and class
                                if (strtolower($status_db) === 'sufficient') {
                                    $display_status = 'Good';
                                    $class = 'badge-success';
                                } elseif (strtolower($status_db) === 'low stock') {
                                    $display_status = 'Low Stock';
                                    $class = 'badge-warning';
                                } else {
                                    $display_status = 'Out of Stock';
                                    $class = 'badge-danger';
                                }
                            ?>
                            <span class="badge <?= $class ?>"><?= htmlspecialchars($display_status) ?></span>
                        </td>
                        <td><?= date('M d, Y', strtotime($item['last_updated'])) ?></td>
                        <td>
                                     <a class="btn-icon" title="Edit" href="#" onclick="showEditModal(this); return false;" 
                                         data-id="<?= htmlspecialchars($item['id'], ENT_QUOTES) ?>"
                                         data-item_name="<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>"
                                         data-category="<?= htmlspecialchars($item['category'], ENT_QUOTES) ?>"
                                         data-unit="<?= htmlspecialchars($item['unit'] ?? 'pcs', ENT_QUOTES) ?>"
                                         data-stock_qty="<?= htmlspecialchars($item['stock_qty'], ENT_QUOTES) ?>"
                                         data-reorder_level="<?= htmlspecialchars($item['reorder_level'], ENT_QUOTES) ?>">
                                         <i class='bx bx-edit'></i>
                                     </a>
                            <a class="btn-icon" title="Delete" href="delete_inventory.php?id=<?= urlencode($item['id']) ?>" onclick="return confirm('Are you sure you want to delete this item?');"><i class='bx bx-trash'></i></a>
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

<!-- Edit Item Modal -->
<div id="editModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Edit Item</h2>
            <span class="close" onclick="closeEditModal()">&times;</span>
        </div>
        <form id="editForm" method="POST" action="edit_inventory.php">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label>Item Name *</label>
                <input type="text" name="item_name" id="edit_item_name" required>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" name="category" id="edit_category" required>
                </div>
                <div class="form-group">
                    <label>Unit *</label>
                    <input type="text" name="unit" id="edit_unit" required>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" name="stock_qty" id="edit_stock_qty" required>
                </div>
                <div class="form-group">
                    <label>Reorder Level </label>
                    <input type="number" name="reorder_level" id="edit_reorder_level" placeholder="Leave empty to keep current">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Changes</button>
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
// Edit modal handlers
function showEditModal(el) {
    var id = el.getAttribute('data-id');
    var name = el.getAttribute('data-item_name');
    var category = el.getAttribute('data-category');
    var unit = el.getAttribute('data-unit');
    var qty = el.getAttribute('data-stock_qty');
    var reorder = el.getAttribute('data-reorder_level');

    document.getElementById('edit_id').value = id;
    document.getElementById('edit_item_name').value = name;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_unit').value = unit;
    document.getElementById('edit_stock_qty').value = qty;
    document.getElementById('edit_reorder_level').value = (reorder === null || reorder === 'NULL') ? '' : reorder;

    document.getElementById('editModal').style.display = 'block';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
// Auto-hide flash message after 5 seconds with a fade
window.addEventListener('DOMContentLoaded', function(){
    var flash = document.getElementById('flash');
    if (!flash) return;
    if (flash.style.display === 'none') return;
    setTimeout(function(){
        flash.classList.add('fade-out');
        setTimeout(function(){ if (flash.parentNode) flash.parentNode.style.display='none'; }, 700);
    }, 5000);
});
</script>

</body>
</html>
