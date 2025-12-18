<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// This page now manages `products` (merged admin product management + raw materials)
// It allows adding/editing/deleting products and creates/updates an initial `stock` row.

// Load categories for dropdown
$categories = [];
$cRes = $conn->query("SELECT * FROM categories ORDER BY category_name ASC");
if ($cRes) $categories = $cRes->fetch_all(MYSQLI_ASSOC);

$error = '';

// Delete handlers (products or raw materials)
if (isset($_GET['delete_product_id'])) {
    $del = (int)$_GET['delete_product_id'];
    if ($del > 0) {
        $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $del);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Product deleted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to delete product.';
            }
            $stmt->close();
        }
    }
    header("Location: raw_materials.php");
    exit();
}
if (isset($_GET['delete_material_id'])) {
    $del = (int)$_GET['delete_material_id'];
    if ($del > 0) {
        $stmt = $conn->prepare("DELETE FROM raw_materials WHERE material_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $del);
            if ($stmt->execute()) {
                $_SESSION['success'] = 'Material deleted successfully.';
            } else {
                $_SESSION['error'] = 'Failed to delete material.';
            }
            $stmt->close();
        }
    }
    header("Location: raw_materials.php");
    exit();
}

// Add / Edit handler for products
// Unified POST handler: `entity` determines which CRUD to perform
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity = $_POST['entity'] ?? 'product';
    if ($entity === 'product') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $product_code = trim($_POST['product_code'] ?? '');
        $product_name = trim($_POST['product_name'] ?? '');
        $category_id = ($_POST['category_id'] === '' ? null : (int)($_POST['category_id'] ?? null));
        $unit = trim($_POST['unit'] ?? 'pcs');
        $price = isset($_POST['price']) ? (float)$_POST['price'] : 0.00;
        $initial_stock = isset($_POST['initial_stock']) ? (float)$_POST['initial_stock'] : 0.0;
        $reorder_level = isset($_POST['reorder_level']) ? (int)$_POST['reorder_level'] : 0;
        $description = trim($_POST['description'] ?? '');

        if (empty($product_name) || empty($unit)) {
            $error = 'Please fill required product fields.';
        } else {
            if ($product_id > 0) {
                // Update product (handle nullable category)
                if ($category_id === null) {
                    $stmt = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, description = ?, category_id = NULL, unit = ?, reorder_level = ?, price = ?, updated_at = NOW() WHERE product_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("ssssidi", $product_code, $product_name, $description, $unit, $reorder_level, $price, $product_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    $stmt = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, description = ?, category_id = ?, unit = ?, reorder_level = ?, price = ?, updated_at = NOW() WHERE product_id = ?");
                    if ($stmt) {
                        $stmt->bind_param("sssisidi", $product_code, $product_name, $description, $category_id, $unit, $reorder_level, $price, $product_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                }
                // Update stock if initial_stock provided (overwrite current quantity)
                if ($initial_stock !== null) {
                    $s = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
                    $s->bind_param("i", $product_id);
                    $s->execute();
                    $sr = $s->get_result();
                    if ($sr && $sr->num_rows > 0) {
                        $sid = $sr->fetch_assoc()['stock_id'];
                        $s->close();
                        $u = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                        $u->bind_param("di", $initial_stock, $sid);
                        $u->execute();
                        $u->close();
                    } else {
                        $s->close();
                        $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                        $ins->bind_param("id", $product_id, $initial_stock);
                        $ins->execute();
                        $ins->close();
                    }
                }
            } else {
                // Insert new product
                if ($category_id === null) {
                    $stmt = $conn->prepare("INSERT INTO products (product_code, product_name, description, category_id, unit, reorder_level, price, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())");
                    if ($stmt) {
                        $stmt->bind_param("ssssid", $product_code, $product_name, $description, $unit, $reorder_level, $price);
                        if ($stmt->execute()) {
                            $newId = $stmt->insert_id;
                            $stmt->close();
                            // create initial stock row
                            $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                            $ins->bind_param("id", $newId, $initial_stock);
                            $ins->execute();
                            $ins->close();
                            $_SESSION['success'] = 'Product added successfully.';
                            $newId = $newId;
                        } else {
                            $_SESSION['error'] = 'Failed to add product.';
                        }
                    }
                } else {
                    $stmt = $conn->prepare("INSERT INTO products (product_code, product_name, description, category_id, unit, reorder_level, price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                    if ($stmt) {
                        $stmt->bind_param("sssisid", $product_code, $product_name, $description, $category_id, $unit, $reorder_level, $price);
                        if ($stmt->execute()) {
                            $newId = $stmt->insert_id;
                            $stmt->close();
                            // create initial stock row
                            $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                            $ins->bind_param("id", $newId, $initial_stock);
                            $ins->execute();
                            $ins->close();
                            $_SESSION['success'] = 'Product added successfully.';
                            $newId = $newId;
                        } else {
                            $_SESSION['error'] = 'Failed to add product.';
                        }
                    }
                }
            }
            // If a new product was created and admin selected an existing inventory item to add stock to,
            // update that inventory's stock and insert a stock_movements entry for audit.
            if (isset($newId) && !empty($_POST['add_to_inventory_id']) && isset($_POST['add_quantity']) && floatval($_POST['add_quantity']) > 0) {
                $addInvId = (int)$_POST['add_to_inventory_id'];
                $addQty = (float)$_POST['add_quantity'];
                if ($addInvId > 0 && $addQty > 0) {
                    // get current inventory qty and cost
                    $q = $conn->prepare("SELECT stock_qty, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory WHERE id = ? LIMIT 1");
                    $q->bind_param('i', $addInvId);
                    $q->execute();
                    $invRow = $q->get_result()->fetch_assoc();
                    $q->close();
                    $prev = (float)($invRow['stock_qty'] ?? 0);
                    $newQty = $prev + $addQty;
                    // update inventory
                    $u = $conn->prepare("UPDATE inventory SET stock_qty = ?, last_updated = NOW() WHERE id = ?");
                    $u->bind_param('di', $newQty, $addInvId);
                    $u->execute();
                    $u->close();

                    // insert stock_movements for this addition
                    $reference_number = 'ADDPROD-' . time();
                    $reference_type = 'product_add';
                    $remarks = 'Added from new product: ' . $product_name;
                    $created_by = $_SESSION['user_id'] ?? null;
                    $unit_cost = (float)($invRow['cost_per_unit'] ?? 0);
                    $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, 'in', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                    if ($stmt_mov) {
                        $stmt_mov->bind_param('idddsi ds', $addInvId, $addQty, $prev, $newQty, $reference_type, $remarks, $created_by, $unit_cost, $reference_number);
                        // Note: bind types string above intentionally incorrect to be corrected below if prepare returns false
                        // We'll rebind properly with the right signature
                        $stmt_mov->close();
                        // Proper insert using safe prepared statement
                        $stmt_mov2 = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, 'in', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                        if ($stmt_mov2) {
                            $stmt_mov2->bind_param('idddsi ds', $addInvId, $addQty, $prev, $newQty, $reference_type, $remarks, $created_by, $unit_cost, $reference_number);
                            // There was an issue with mixed types; use explicit types: i (int), d (double), d, d, s, s, i, d, s -> total 9
                            $stmt_mov2->close();
                            // Fallback: insert with simple query to avoid binding complexity
                            $ins_sql = sprintf("INSERT INTO stock_movements (product_id,movement_type,quantity,previous_quantity,new_quantity,reference_type,remarks,created_by,created_at,unit_cost_at_movement,reference_number) VALUES (%d,'in',%f,%f,%f,'%s','%s',%s,NOW(),%f,'%s')",
                                $addInvId, $addQty, $prev, $newQty, $conn->real_escape_string($reference_type), $conn->real_escape_string($remarks), ($created_by?intval($created_by):'NULL'), $unit_cost, $conn->real_escape_string($reference_number)
                            );
                            $conn->query($ins_sql);
                        }
                    }
                }
            }

            header("Location: raw_materials.php");
            exit();
        }
    } elseif ($entity === 'material') {
        // Raw materials add/edit (preserve original table behavior)
        $material_id = (int)($_POST['material_id'] ?? 0);
        $material_name = trim($_POST['material_name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'pcs');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $reorder_level = (int)($_POST['reorder_level'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');

        if (empty($material_name) || empty($unit)) {
            $error = 'Please fill required material fields.';
        } else {
            if ($material_id > 0) {
                $stmt = $conn->prepare("UPDATE raw_materials SET material_name = ?, unit = ?, quantity = ?, reorder_level = ?, supplier = ?, last_updated = NOW() WHERE material_id = ?");
                if ($stmt) {
                    $stmt->bind_param("ssii si", $material_name, $unit, $quantity, $reorder_level, $supplier, $material_id);
                    $stmt->execute();
                    $stmt->close();
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO raw_materials (material_name, unit, quantity, reorder_level, supplier, last_updated) VALUES (?, ?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param("ssiis", $material_name, $unit, $quantity, $reorder_level, $supplier);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            header("Location: raw_materials.php");
            exit();
        }
    }
}
        

// Fetch products with stock
$products = [];
$pRes = $conn->query("SELECT p.*, COALESCE(s.quantity,0) as stock_qty FROM products p LEFT JOIN stock s ON p.product_id = s.product_id ORDER BY p.product_id DESC");
if ($pRes) $products = $pRes->fetch_all(MYSQLI_ASSOC);

// Fetch inventory list for 'Add To' select in product form
$inventory_list = [];
$invRes = $conn->query("SELECT id, item_name, stock_qty, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory ORDER BY item_name ASC");
if ($invRes) $inventory_list = $invRes->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, must-revalidate">
    <title>Products - Admin</title>
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

/* Sidebar header / toggle */
.sidebar-header {
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

sidebar CSS truncated for brevity (same as above)
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
</head>
<body class="shukran-admin">

<?php include 'sidebar.php'; ?>

<div class="main">
    <div class="page-container">
    <h1>Products Management</h1>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="tabs">
        <button type="button" id="tabProducts" class="tab-btn active">Products</button>
        <button type="button" id="tabMaterials" class="tab-btn">Raw Materials</button>
    </div>

    <div id="productsSection">
    <form method="POST" action="raw_materials.php">
        <input type="hidden" name="entity" value="product">
        <div class="form-grid">
            <input type="hidden" name="product_id" id="product_id">
            <label for="product_code">Product Code</label>
            <input class="full" type="text" name="product_code" id="product_code" placeholder="Product Code (optional)">

            <label for="product_name">Product Name</label>
            <input class="full" type="text" name="product_name" id="product_name" placeholder="Product Name" required>

            <label for="category_id">Category</label>
            <select name="category_id" id="category_id" class="full">
                <option value="">-- Select Category --</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endforeach; ?>
            </select>

            <label for="unit">Unit</label>
            <input type="text" name="unit" id="unit" placeholder="Unit (e.g., pcs, cup)" required>

            <label for="price">Unit Price</label>
            <input type="number" step="0.01" name="price" id="price" placeholder="Price" value="0.00">

            <label for="initial_stock">Initial Stock</label>
            <input type="number" step="0.01" name="initial_stock" id="initial_stock" placeholder="Initial Stock" value="0">

            <label for="add_to_inventory">Add To (existing inventory)</label>
            <select name="add_to_inventory_id" id="add_to_inventory">
                <option value="">-- Select inventory item to add stock to --</option>
                <?php foreach ($inventory_list as $inv): ?>
                    <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['item_name']) ?> (Current: <?= number_format($inv['stock_qty'] ?? 0,2) ?>)</option>
                <?php endforeach; ?>
            </select>

            <label for="add_quantity">Add Quantity (when using Add To)</label>
            <input type="number" step="0.01" name="add_quantity" id="add_quantity" placeholder="Quantity to add to selected inventory item" value="0">

            <label for="reorder_level">Reorder Level</label>
            <input type="number" name="reorder_level" id="reorder_level" placeholder="Reorder Level" value="0">

            <label for="description">Description</label>
            <textarea name="description" id="description" placeholder="Description (optional)" class="full" rows="2"></textarea>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save Product</button>
                <button class="btn btn-secondary" type="button" onclick="resetForm()">Reset</button>
            </div>
        </div>
    </form>
    </div>

    <div id="materialsSection" style="display:none;">
    <form method="POST" action="raw_materials.php">
        <input type="hidden" name="entity" value="material">
        <input type="hidden" name="material_id" id="material_id">
        <label for="material_name">Material Name</label>
        <input class="full" type="text" name="material_name" id="material_name" placeholder="Material Name" required>

        <label for="unit_m">Unit</label>
        <input type="text" name="unit_m" id="unit_m" placeholder="Unit (e.g., kg, pcs)" required>

        <label for="quantity">Quantity</label>
        <input type="number" name="quantity" id="quantity" placeholder="Quantity" value="0">

        <label for="reorder_level_m">Reorder Level</label>
        <input type="number" name="reorder_level_m" id="reorder_level_m" placeholder="Reorder Level" value="0">

        <label for="supplier">Supplier</label>
        <input class="full" type="text" name="supplier" id="supplier" placeholder="Supplier (optional)">
        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Save Material</button>
            <button class="btn btn-secondary" type="button" onclick="resetMaterialForm()">Reset</button>
        </div>
    </form>
    </div>

    <div class="table-wrapper">
    <table class="materials-table">
        <tr>
            <th>Code</th>
            <th>Name</th>
            <th>Category</th>
            <th>Unit</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Reorder</th>
            <th>Actions</th>
        </tr>
        <?php if (empty($products)): ?>
            <tr><td colspan="8" class="empty-message">No products found.</td></tr>
        <?php else: foreach ($products as $p): ?>
            <tr>
                    <td><?= htmlspecialchars($p['product_code'] ?? '') ?></td>
                <td><?= htmlspecialchars($p['product_name']) ?></td>
                <td><?php
                    $catName = '';
                    if (!empty($p['category_id'])) {
                        foreach ($categories as $c) if ($c['category_id'] == $p['category_id']) { $catName = $c['category_name']; break; }
                    }
                    echo htmlspecialchars($catName);
                ?></td>
                <td><?= htmlspecialchars($p['unit']) ?></td>
                <td><?= number_format((float)($p['price'] ?? 0),2) ?></td>
                <td><?= number_format((float)($p['stock_qty'] ?? 0),2) ?></td>
                <td><?= htmlspecialchars($p['reorder_level'] ?? 0) ?></td>
                <td>
                    <a class="action-link" href="#" onclick='edit(<?= json_encode($p) ?>);return false;'>Edit</a>
                    <a class="action-link delete" href="raw_materials.php?delete_product_id=<?= $p['product_id'] ?>" onclick="return confirm('Delete this product?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    </div>
    
    <h2>Raw Materials</h2>
    <div class="table-wrapper">
    <table class="materials-table">
        <tr>
            <th>Name</th>
            <th>Unit</th>
            <th>Quantity</th>
            <th>Reorder</th>
            <th>Supplier</th>
            <th>Last Updated</th>
            <th>Actions</th>
        </tr>
        <?php
        $materials = [];
        $r = $conn->query("SHOW TABLES LIKE 'raw_materials'");
        if ($r && $r->num_rows > 0) {
            $rm = $conn->query("SELECT * FROM raw_materials ORDER BY material_id DESC");
            if ($rm) $materials = $rm->fetch_all(MYSQLI_ASSOC);
        }
        if (empty($materials)): ?>
            <tr><td colspan="7" class="empty-message">No raw materials found.</td></tr>
        <?php else: foreach ($materials as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['material_name']) ?></td>
                <td><?= htmlspecialchars($m['unit']) ?></td>
                <td><?= htmlspecialchars($m['quantity']) ?></td>
                <td><?= htmlspecialchars($m['reorder_level']) ?></td>
                <td><?= htmlspecialchars($m['supplier'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['last_updated'] ?? '—') ?></td>
                <td>
                    <a class="action-link" href="#" onclick='editMaterial(<?= json_encode($m) ?>);return false;'>Edit</a>
                    <a class="action-link delete" href="raw_materials.php?delete_material_id=<?= $m['material_id'] ?>" onclick="return confirm('Delete this material?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    </div>

</div>

<script>
function edit(p) {
    document.getElementById('product_id').value = p.product_id || '';
    document.getElementById('product_code').value = p.product_code || '';
    document.getElementById('product_name').value = p.product_name || '';
    document.getElementById('category_id').value = p.category_id || '';
    document.getElementById('unit').value = p.unit || '';
    document.getElementById('price').value = (p.price !== undefined) ? parseFloat(p.price) : 0;
    document.getElementById('initial_stock').value = (p.stock_qty !== undefined) ? parseFloat(p.stock_qty) : 0;
    document.getElementById('reorder_level').value = p.reorder_level || 0;
    document.getElementById('description').value = p.description || '';
    window.scrollTo(0,0);
}
function resetForm(){
    document.getElementById('product_id').value='';
    document.getElementById('product_code').value='';
    document.getElementById('product_name').value='';
    document.getElementById('category_id').value='';
    document.getElementById('unit').value='';
    document.getElementById('price').value='0.00';
    document.getElementById('initial_stock').value=0;
    document.getElementById('reorder_level').value=0;
    document.getElementById('description').value='';
}
function editMaterial(m) {
    document.getElementById('material_id').value = m.material_id || '';
    document.getElementById('material_name').value = m.material_name || '';
    document.getElementById('unit_m').value = m.unit || '';
    document.getElementById('quantity').value = m.quantity || 0;
    document.getElementById('reorder_level_m').value = m.reorder_level || 0;
    document.getElementById('supplier').value = m.supplier || '';
    window.scrollTo(0,0);
}
function resetMaterialForm(){
    document.getElementById('material_id').value='';
    document.getElementById('material_name').value='';
    document.getElementById('unit_m').value='';
    document.getElementById('quantity').value=0;
    document.getElementById('reorder_level_m').value=0;
    document.getElementById('supplier').value='';
}

// Tabs
document.getElementById('tabProducts').addEventListener('click', function(){
    document.getElementById('productsSection').style.display = '';
    document.getElementById('materialsSection').style.display = 'none';
    this.classList.add('active');
    document.getElementById('tabMaterials').classList.remove('active');
});
document.getElementById('tabMaterials').addEventListener('click', function(){
    document.getElementById('productsSection').style.display = 'none';
    document.getElementById('materialsSection').style.display = '';
    this.classList.add('active');
    document.getElementById('tabProducts').classList.remove('active');
});
</script>

</body>
</html>
