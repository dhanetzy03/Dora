<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

$error = '';

// Delete handler
if (isset($_GET['delete_id'])) {
    $del = (int)$_GET['delete_id'];
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

// Add / Edit handler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $material_id = (int)($_POST['material_id'] ?? 0);
    $material_name = trim($_POST['material_name'] ?? '');
    $unit = trim($_POST['unit'] ?? 'pcs');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reorder_level = (int)($_POST['reorder_level'] ?? 0);
    $supplier = trim($_POST['supplier'] ?? '');

    if (empty($material_name) || empty($unit)) {
        $error = 'Please fill required fields.';
    } else {
        if ($material_id > 0) {
            $stmt = $conn->prepare("UPDATE raw_materials SET material_name = ?, unit = ?, quantity = ?, reorder_level = ?, supplier = ?, last_updated = NOW() WHERE material_id = ?");
            $stmt->bind_param("ssii si", $material_name, $unit, $quantity, $reorder_level, $supplier, $material_id);
            $stmt->execute();
            $stmt->close();
        } else {
            $stmt = $conn->prepare("INSERT INTO raw_materials (material_name, unit, quantity, reorder_level, supplier, last_updated) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssiis", $material_name, $unit, $quantity, $reorder_level, $supplier);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: raw_materials.php");
        exit();
    }
}

// Fetch materials (if table missing, show empty list)
$materials = [];
$res = $conn->query("SHOW TABLES LIKE 'raw_materials'");
if ($res && $res->num_rows > 0) {
    $r = $conn->query("SELECT * FROM raw_materials ORDER BY material_id DESC");
    if ($r) $materials = $r->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, must-revalidate">
    <title>Raw Materials - Admin</title>
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
    <h1>Raw Materials / Ingredients</h1>

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

    <form method="POST" action="raw_materials.php">
        <div class="form-grid">
            <input type="hidden" name="material_id" id="material_id">
            <input class="full" type="text" name="material_name" id="material_name" placeholder="Material Name" required>
            <input type="text" name="unit" id="unit" placeholder="Unit (e.g., kg, pcs)" required>
            <input type="number" name="quantity" id="quantity" placeholder="Quantity" value="0">
            <input type="number" name="reorder_level" id="reorder_level" placeholder="Reorder Level" value="0">
            <input class="full" type="text" name="supplier" id="supplier" placeholder="Supplier (optional)">
            <div class="form-actions">
                <button class="btn btn-primary" type="submit">Save</button>
                <button class="btn btn-secondary" type="button" onclick="resetForm()">Reset</button>
            </div>
        </div>
    </form>

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
        <?php if (empty($materials)): ?>
            <tr><td colspan="7" class="empty-message">No raw materials found. If this is a fresh DB, create the `raw_materials` table first.</td></tr>
        <?php else: foreach ($materials as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['material_name']) ?></td>
                <td><?= htmlspecialchars($m['unit']) ?></td>
                <td><?= htmlspecialchars($m['quantity']) ?></td>
                <td><?= htmlspecialchars($m['reorder_level']) ?></td>
                <td><?= htmlspecialchars($m['supplier'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['last_updated'] ?? '—') ?></td>
                <td>
                    <a class="action-link" href="#" onclick='edit(<?= json_encode($m) ?>);return false;'>Edit</a>
                    <a class="action-link delete" href="raw_materials.php?delete_id=<?= $m['material_id'] ?>" onclick="return confirm('Delete this material?')">Delete</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    </div>

</div>

<script>
function edit(m) {
    document.getElementById('material_id').value = m.material_id;
    document.getElementById('material_name').value = m.material_name;
    document.getElementById('unit').value = m.unit;
    document.getElementById('quantity').value = m.quantity;
    document.getElementById('reorder_level').value = m.reorder_level;
    document.getElementById('supplier').value = m.supplier || '';
    window.scrollTo(0,0);
}
function resetForm(){
    document.getElementById('material_id').value='';
    document.getElementById('material_name').value='';
    document.getElementById('unit').value='';
    document.getElementById('quantity').value=0;
    document.getElementById('reorder_level').value=0;
    document.getElementById('supplier').value='';
}
</script>

</body>
</html>
