<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";
require_once "../../config/db_helper.php";

$activeTab = $_GET['tab'] ?? 'products';
if (!in_array($activeTab, ['products', 'materials'], true)) {
    $activeTab = 'products';
}

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
        db_begin_transaction();
        
        try {
            // First delete from stock table (foreign key constraint)
            $stmt = $conn->prepare("DELETE FROM stock WHERE product_id = ?");
            if (!$stmt) throw new Exception('Failed to prepare stock delete');
            $stmt->bind_param("i", $del);
            if (!$stmt->execute()) throw new Exception('Failed to delete stock');
            $stmt->close();
            
            // Then delete product
            $stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
            if (!$stmt) throw new Exception('Failed to prepare product delete');
            $stmt->bind_param("i", $del);
            if (!$stmt->execute()) throw new Exception('Failed to delete product');
            $stmt->close();
            
            db_commit();
            $_SESSION['success'] = 'Product and associated stock deleted successfully.';
        } catch (Exception $e) {
            db_rollback();
            $_SESSION['error'] = 'Failed to delete product: ' . $e->getMessage();
        }
    }
    header("Location: raw_materials.php");
    exit();
}
if (isset($_GET['delete_material_id'])) {
    $del = (int)$_GET['delete_material_id'];
    if ($del > 0) {
        db_begin_transaction();
        
        try {
            $materialCode = null;
            $lookup = $conn->prepare("SELECT material_code FROM raw_materials WHERE material_id = ? LIMIT 1");
            if (!$lookup) throw new Exception('Failed to prepare material lookup');
            $lookup->bind_param("i", $del);
            if (!$lookup->execute()) throw new Exception('Failed to lookup material before delete');
            $lookupRow = $lookup->get_result()->fetch_assoc();
            $lookup->close();
            $materialCode = $lookupRow['material_code'] ?? null;

            if (!empty($materialCode)) {
                $invDel = $conn->prepare("DELETE FROM inventory WHERE item_code = ?");
                if (!$invDel) throw new Exception('Failed to prepare inventory delete');
                $invDel->bind_param("s", $materialCode);
                if (!$invDel->execute()) throw new Exception('Failed to delete inventory mirror record');
                $invDel->close();
            }

            $stmt = $conn->prepare("DELETE FROM raw_materials WHERE material_id = ?");
            if (!$stmt) throw new Exception('Failed to prepare delete statement');
            $stmt->bind_param("i", $del);
            if (!$stmt->execute()) throw new Exception('Failed to delete material');
            $stmt->close();
            
            db_commit();
            $_SESSION['success'] = 'Material deleted successfully.';
        } catch (Exception $e) {
            db_rollback();
            $_SESSION['error'] = 'Failed to delete material: ' . $e->getMessage();
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
                // Update product (handle nullable category) with transaction protection
                db_begin_transaction();
                
                try {
                    if ($category_id === null) {
                        $stmt = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, description = ?, category_id = NULL, unit = ?, reorder_level = ?, price = ?, updated_at = NOW() WHERE product_id = ?");
                        if (!$stmt) throw new Exception('Failed to prepare update statement');
                        $stmt->bind_param("ssssidi", $product_code, $product_name, $description, $unit, $reorder_level, $price, $product_id);
                        if (!$stmt->execute()) throw new Exception('Failed to update product');
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("UPDATE products SET product_code = ?, product_name = ?, description = ?, category_id = ?, unit = ?, reorder_level = ?, price = ?, updated_at = NOW() WHERE product_id = ?");
                        if (!$stmt) throw new Exception('Failed to prepare update statement');
                        $stmt->bind_param("sssisidi", $product_code, $product_name, $description, $category_id, $unit, $reorder_level, $price, $product_id);
                        if (!$stmt->execute()) throw new Exception('Failed to update product');
                        $stmt->close();
                    }
                    
                    // Update stock if initial_stock provided (overwrite current quantity)
                    if ($initial_stock !== null) {
                        $s = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
                        if (!$s) throw new Exception('Failed to prepare stock select');
                        $s->bind_param("i", $product_id);
                        if (!$s->execute()) throw new Exception('Failed to fetch stock');
                        $sr = $s->get_result();
                        
                        if ($sr && $sr->num_rows > 0) {
                            $sid = $sr->fetch_assoc()['stock_id'];
                            $s->close();
                            $u = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                            if (!$u) throw new Exception('Failed to prepare stock update');
                            $u->bind_param("di", $initial_stock, $sid);
                            if (!$u->execute()) throw new Exception('Failed to update stock');
                            $u->close();
                        } else {
                            $s->close();
                            $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                            if (!$ins) throw new Exception('Failed to prepare stock insert');
                            $ins->bind_param("id", $product_id, $initial_stock);
                            if (!$ins->execute()) throw new Exception('Failed to insert stock');
                            $ins->close();
                        }
                    }
                    
                    db_commit();
                    $_SESSION['success'] = 'Product updated successfully.';
                } catch (Exception $e) {
                    db_rollback();
                    $_SESSION['error'] = 'Failed to update product: ' . $e->getMessage();
                }
            } else {
                // Insert new product
                db_begin_transaction();
                
                try {
                    if ($category_id === null) {
                        $stmt = $conn->prepare("INSERT INTO products (product_code, product_name, description, category_id, unit, reorder_level, price, created_at, updated_at) VALUES (?, ?, ?, NULL, ?, ?, ?, NOW(), NOW())");
                        if (!$stmt) throw new Exception('Failed to prepare product insert');
                        $stmt->bind_param("ssssid", $product_code, $product_name, $description, $unit, $reorder_level, $price);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to insert product');
                        }
                        $newId = $stmt->insert_id;
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO products (product_code, product_name, description, category_id, unit, reorder_level, price, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
                        if (!$stmt) throw new Exception('Failed to prepare product insert');
                        $stmt->bind_param("sssisid", $product_code, $product_name, $description, $category_id, $unit, $reorder_level, $price);
                        if (!$stmt->execute()) {
                            throw new Exception('Failed to insert product');
                        }
                        $newId = $stmt->insert_id;
                        $stmt->close();
                    }
                    
                    // Create initial stock row
                    $ins = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                    if (!$ins) throw new Exception('Failed to prepare stock insert');
                    $ins->bind_param("id", $newId, $initial_stock);
                    if (!$ins->execute()) {
                        throw new Exception('Failed to insert stock');
                    }
                    $ins->close();
                    
                    db_commit();
                    $_SESSION['success'] = 'Product added successfully.';
                } catch (Exception $e) {
                    db_rollback();
                    $_SESSION['error'] = 'Failed to add product: ' . $e->getMessage();
                }
            }
            // If a new product was created and admin selected an existing inventory item to add stock to,
            // update that inventory's stock and insert a stock_movements entry for audit.
            if (isset($newId) && !empty($_POST['add_to_inventory_id']) && isset($_POST['add_quantity']) && floatval($_POST['add_quantity']) > 0) {
                $addInvId = (int)$_POST['add_to_inventory_id'];
                $addQty = (float)$_POST['add_quantity'];
                
                if ($addInvId > 0 && $addQty > 0) {
                    db_begin_transaction();
                    
                    try {
                        // Get current inventory qty and cost
                        $q = $conn->prepare("SELECT stock_qty, COALESCE(cost_per_unit,0) as cost_per_unit FROM inventory WHERE id = ? LIMIT 1");
                        if (!$q) throw new Exception('Failed to prepare inventory select');
                        $q->bind_param('i', $addInvId);
                        if (!$q->execute()) throw new Exception('Failed to fetch inventory');
                        $invRow = $q->get_result()->fetch_assoc();
                        $q->close();
                        
                        if (!$invRow) throw new Exception('Inventory item not found');
                        
                        $prev = (float)($invRow['stock_qty'] ?? 0);
                        $newQty = $prev + $addQty;
                        
                        // Update inventory
                        $u = $conn->prepare("UPDATE inventory SET stock_qty = ?, last_updated = NOW() WHERE id = ?");
                        if (!$u) throw new Exception('Failed to prepare inventory update');
                        $u->bind_param('di', $newQty, $addInvId);
                        if (!$u->execute()) throw new Exception('Failed to update inventory');
                        $u->close();

                        // Insert stock_movements for this addition
                        $reference_number = 'ADDPROD-' . time();
                        $reference_type = 'product_add';
                        $remarks = 'Added from new product: ' . $product_name;
                        $created_by = $_SESSION['user_id'] ?? null;
                        $unit_cost = (float)($invRow['cost_per_unit'] ?? 0);
                        
                        $stmt_mov = $conn->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, previous_quantity, new_quantity, reference_type, remarks, created_by, created_at, unit_cost_at_movement, reference_number) VALUES (?, 'in', ?, ?, ?, ?, ?, ?, NOW(), ?, ?)");
                        if (!$stmt_mov) throw new Exception('Failed to prepare stock movement insert');
                        
                        // Correct binding: product_id(i), quantity(d), previous(d), new(d), ref_type(s), remarks(s), created_by(i), unit_cost(d), reference_number(s)
                        $stmt_mov->bind_param('idddisids', $addInvId, $addQty, $prev, $newQty, $reference_type, $remarks, $created_by, $unit_cost, $reference_number);
                        if (!$stmt_mov->execute()) throw new Exception('Failed to insert stock movement');
                        $stmt_mov->close();
                        
                        db_commit();
                    } catch (Exception $e) {
                        db_rollback();
                        $_SESSION['warning'] = 'Product created but stock movement failed: ' . $e->getMessage();
                    }
                }
            }

            header("Location: raw_materials.php");
            exit();
        }
    } elseif ($entity === 'material') {
        // Raw materials add/edit (preserve original table behavior)
        $rawMaterialDebug = [
            'event' => 'material_post_received',
            'post' => [
                'material_id' => $_POST['material_id'] ?? null,
                'material_name' => $_POST['material_name'] ?? null,
                'unit_m' => $_POST['unit_m'] ?? null,
                'quantity' => $_POST['quantity'] ?? null,
                'reorder_level_m' => $_POST['reorder_level_m'] ?? null,
                'supplier' => $_POST['supplier'] ?? null
            ]
        ];
        $material_id = (int)($_POST['material_id'] ?? 0);
        $material_name = trim($_POST['material_name'] ?? '');
        $unit = trim($_POST['unit_m'] ?? $_POST['unit'] ?? 'pcs');
        $quantity = (float)($_POST['quantity'] ?? 0.0);
        $cost_per_unit = isset($_POST['cost_per_unit']) ? (float)$_POST['cost_per_unit'] : 0.00;
        $reorder_level = (int)($_POST['reorder_level_m'] ?? $_POST['reorder_level'] ?? 0);
        $supplier = trim($_POST['supplier'] ?? '');

        if (empty($material_name) || empty($unit)) {
            $error = 'Please fill required material fields.';
        } else {
            db_begin_transaction();
            
            try {
                $materialCodeForSync = null;
                $oldMaterialName = null;

                $supplier_id = null;
                if ($supplier !== '') {
                    $sstmt = $conn->prepare("SELECT supplier_id FROM suppliers WHERE LOWER(TRIM(supplier_name)) = LOWER(TRIM(?)) LIMIT 1");
                    if (!$sstmt) throw new Exception('Failed to prepare supplier lookup');
                    $sstmt->bind_param("s", $supplier);
                    if (!$sstmt->execute()) throw new Exception('Failed to lookup supplier');
                    $srow = $sstmt->get_result()->fetch_assoc();
                    $sstmt->close();

                    if ($srow && isset($srow['supplier_id'])) {
                        $supplier_id = (int)$srow['supplier_id'];
                        $rawMaterialDebug['supplier_lookup'] = 'existing_supplier';
                    } else {
                        $sins = $conn->prepare("INSERT INTO suppliers (supplier_name, status, created_at, updated_at) VALUES (?, 'active', NOW(), NOW())");
                        if (!$sins) throw new Exception('Failed to prepare supplier insert');
                        $sins->bind_param("s", $supplier);
                        if (!$sins->execute()) throw new Exception('Failed to create supplier');
                        $supplier_id = (int)$sins->insert_id;
                        $sins->close();
                        $rawMaterialDebug['supplier_lookup'] = 'new_supplier_created';
                    }
                } else {
                    $rawMaterialDebug['supplier_lookup'] = 'empty_supplier_set_null';
                }

                if ($material_id > 0) {
                    $beforeStmt = $conn->prepare("SELECT material_code, material_name FROM raw_materials WHERE material_id = ? LIMIT 1");
                    if (!$beforeStmt) throw new Exception('Failed to prepare material prefetch');
                    $beforeStmt->bind_param("i", $material_id);
                    if (!$beforeStmt->execute()) throw new Exception('Failed to prefetch material');
                    $beforeRow = $beforeStmt->get_result()->fetch_assoc();
                    $beforeStmt->close();
                    if (!$beforeRow) throw new Exception('Material not found for update');
                    $materialCodeForSync = $beforeRow['material_code'] ?? null;
                    $oldMaterialName = $beforeRow['material_name'] ?? null;

                    if ($supplier_id === null) {
                        $stmt = $conn->prepare("UPDATE raw_materials SET material_name = ?, unit = ?, quantity = ?, cost_per_unit = ?, reorder_level = ?, supplier_id = NULL, last_updated = NOW() WHERE material_id = ?");
                        if (!$stmt) throw new Exception('Failed to prepare update statement');
                        $stmt->bind_param("ssddii", $material_name, $unit, $quantity, $cost_per_unit, $reorder_level, $material_id);
                    } else {
                        $stmt = $conn->prepare("UPDATE raw_materials SET material_name = ?, unit = ?, quantity = ?, cost_per_unit = ?, reorder_level = ?, supplier_id = ?, last_updated = NOW() WHERE material_id = ?");
                        if (!$stmt) throw new Exception('Failed to prepare update statement');
                        $stmt->bind_param("ssddiii", $material_name, $unit, $quantity, $cost_per_unit, $reorder_level, $supplier_id, $material_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to update raw material');
                    }
                    $stmt->close();
                    $_SESSION['success'] = 'Raw material updated successfully.';
                    $rawMaterialDebug['db_action'] = 'update_raw_material_success';
                } else {
                    $material_code = 'RM-' . strtoupper(substr(md5($material_name . microtime(true)), 0, 10));
                    $materialCodeForSync = $material_code;
                    if ($supplier_id === null) {
                        $stmt = $conn->prepare("INSERT INTO raw_materials (material_code, material_name, unit, quantity, cost_per_unit, reorder_level, supplier_id, last_updated) VALUES (?, ?, ?, ?, ?, ?, NULL, NOW())");
                        if (!$stmt) throw new Exception('Failed to prepare insert statement');
                        $stmt->bind_param("sssddi", $material_code, $material_name, $unit, $quantity, $cost_per_unit, $reorder_level);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO raw_materials (material_code, material_name, unit, quantity, cost_per_unit, reorder_level, supplier_id, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
                        if (!$stmt) throw new Exception('Failed to prepare insert statement');
                        $stmt->bind_param("sssddii", $material_code, $material_name, $unit, $quantity, $cost_per_unit, $reorder_level, $supplier_id);
                    }
                    if (!$stmt->execute()) {
                        throw new Exception('Failed to insert raw material');
                    }
                    $stmt->close();
                    $_SESSION['success'] = 'Raw material added successfully.';
                    $rawMaterialDebug['db_action'] = 'insert_raw_material_success';
                    $rawMaterialDebug['generated_material_code'] = $material_code;
                }

                if (empty($materialCodeForSync)) {
                    throw new Exception('Material code missing during inventory sync');
                }

                $status = 'Sufficient';
                if ($quantity <= 0) {
                    $status = 'Out of Stock';
                } elseif ($quantity <= $reorder_level) {
                    $status = 'Low Stock';
                }

                $invId = null;
                $invLookup = $conn->prepare("SELECT id FROM inventory WHERE item_code = ? LIMIT 1");
                if (!$invLookup) throw new Exception('Failed to prepare inventory lookup by code');
                $invLookup->bind_param("s", $materialCodeForSync);
                if (!$invLookup->execute()) throw new Exception('Failed to lookup inventory by code');
                $invRow = $invLookup->get_result()->fetch_assoc();
                $invLookup->close();

                if ($invRow && isset($invRow['id'])) {
                    $invId = (int)$invRow['id'];
                } elseif (!empty($oldMaterialName)) {
                    $invLookupByName = $conn->prepare("SELECT id FROM inventory WHERE LOWER(TRIM(item_name)) = LOWER(TRIM(?)) LIMIT 1");
                    if (!$invLookupByName) throw new Exception('Failed to prepare inventory lookup by old name');
                    $invLookupByName->bind_param("s", $oldMaterialName);
                    if (!$invLookupByName->execute()) throw new Exception('Failed to lookup inventory by old name');
                    $invRowByName = $invLookupByName->get_result()->fetch_assoc();
                    $invLookupByName->close();
                    if ($invRowByName && isset($invRowByName['id'])) {
                        $invId = (int)$invRowByName['id'];
                    }
                }

                if ($invId) {
                    $invUpdate = $conn->prepare("UPDATE inventory SET item_code = ?, item_name = ?, category = 'Raw', stock_qty = ?, reorder_level = ?, status = ?, unit = ?, cost_per_unit = ?, last_updated = NOW() WHERE id = ?");
                    if (!$invUpdate) throw new Exception('Failed to prepare inventory update sync');
                    $invUpdate->bind_param("ssdissdi", $materialCodeForSync, $material_name, $quantity, $reorder_level, $status, $unit, $cost_per_unit, $invId);
                    if (!$invUpdate->execute()) throw new Exception('Failed to update inventory sync');
                    $invUpdate->close();
                    $rawMaterialDebug['inventory_sync'] = 'updated_existing_inventory';
                } else {
                    $stockIn = (int)round(max(0, $quantity));
                    $invInsert = $conn->prepare("INSERT INTO inventory (item_code, item_name, category, stock_qty, reorder_level, status, unit, cost_per_unit, stock_in, stock_out, date_received, last_updated) VALUES (?, ?, 'Raw', ?, ?, ?, ?, ?, ?, 0, CURDATE(), NOW())");
                    if (!$invInsert) throw new Exception('Failed to prepare inventory insert sync');
                    $invInsert->bind_param("ssdisdii", $materialCodeForSync, $material_name, $quantity, $reorder_level, $status, $unit, $cost_per_unit, $stockIn);
                    if (!$invInsert->execute()) throw new Exception('Failed to insert inventory sync');
                    $invInsert->close();
                    $rawMaterialDebug['inventory_sync'] = 'inserted_new_inventory';
                }
                
                db_commit();
                $rawMaterialDebug['transaction'] = 'commit';
            } catch (Exception $e) {
                db_rollback();
                $_SESSION['error'] = 'Failed to save raw material: ' . $e->getMessage();
                $rawMaterialDebug['transaction'] = 'rollback';
                $rawMaterialDebug['error'] = $e->getMessage();
            }
            $_SESSION['raw_material_debug'] = $rawMaterialDebug;
            header("Location: raw_materials.php?tab=materials");
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

$rawMaterialCount = 0;
$supplierCount = 0;
$rmCountRes = $conn->query("SELECT COUNT(*) AS cnt FROM raw_materials");
if ($rmCountRes) {
    $row = $rmCountRes->fetch_assoc();
    $rawMaterialCount = (int)($row['cnt'] ?? 0);
}
$supCountRes = $conn->query("SELECT COUNT(*) AS cnt FROM suppliers");
if ($supCountRes) {
    $row = $supCountRes->fetch_assoc();
    $supplierCount = (int)($row['cnt'] ?? 0);
}

$rawMaterialDebugOutput = $_SESSION['raw_material_debug'] ?? null;
if (isset($_SESSION['raw_material_debug'])) {
    unset($_SESSION['raw_material_debug']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-store, must-revalidate">
    <title>Products - Admin</title>
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <link rel="stylesheet" href="../styles/admin-style.css?v=DEFENSE2025">
    <link rel="stylesheet" href="../styles/shukran-theme.css?v=DEFENSE2025">
<style>
:root {
    --rm-bg-soft: #f3f6fd;
    --rm-bg-card: #ffffff;
    --rm-ink: #1f2d4f;
    --rm-muted: #5a6b8d;
    --rm-line: #dbe4f8;
    --rm-primary: #2f4f93;
    --rm-primary-2: #3f68bc;
    --rm-danger: #cf4357;
    --rm-danger-2: #e16476;
}
.materials-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 12px;
}
.materials-chip {
    background: linear-gradient(180deg, #ffffff 0%, #f7f9ff 100%);
    color: #1d2a4a;
    border: 1px solid #d8e1fb;
    border-radius: 999px;
    padding: 8px 14px;
    font-size: 12px;
    font-weight: 700;
    box-shadow: 0 2px 10px rgba(36, 68, 128, 0.08);
}
.materials-chip strong {
    margin-left: 8px;
    font-size: 14px;
    color: #29427a;
}
.page-container {
    background: linear-gradient(180deg, #f5f7fc 0%, #eef2fb 100%);
    border: 1px solid #dde5f7;
    border-radius: 14px;
    padding: 14px;
}
.content-card {
    border: 1px solid var(--rm-line);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 24px rgba(31, 45, 79, 0.06);
}
.card-header {
    background: linear-gradient(180deg, #ffffff 0%, #f8faff 100%);
    border-bottom: 1px solid var(--rm-line);
}
.card-header h2 {
    color: var(--rm-ink);
    letter-spacing: 0.2px;
}
.table-responsive {
    width: 100%;
    overflow-x: auto;
}
.materials-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: auto;
    font-size: 13px;
}
.materials-table tr:nth-child(even) td {
    background: #fafbff;
}
.materials-table th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #eef3ff;
    color: #25365d;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.7px;
    padding: 10px 12px;
}
.materials-table td {
    border-bottom: 1px solid #e9eefb;
    padding: 8px 12px;
    line-height: 1.3;
    color: #2d3f68;
}
.tabs {
    display: inline-flex;
    background: #e9efff;
    border: 1px solid #d2dcf7;
    border-radius: 12px;
    padding: 4px;
    gap: 4px;
}
.tab-btn {
    border: 0;
    border-radius: 8px;
    padding: 8px 14px;
    font-weight: 700;
    color: #435a8e;
    background: transparent;
}
.tab-btn.active {
    background: #ffffff;
    color: #253d73;
    box-shadow: 0 2px 8px rgba(37, 61, 115, 0.18);
}
.helper-note {
    font-size: 12px;
    color: #5e6b85;
    margin-top: -4px;
    margin-bottom: 8px;
}
.action-buttons {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.action-btn {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    border: 1px solid #cfd8ef;
    background: linear-gradient(180deg, #f8faff 0%, #eef3ff 100%);
    color: var(--rm-primary);
    transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.action-btn i {
    font-size: 16px;
}
.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(47, 79, 147, 0.18);
    background: linear-gradient(180deg, #ffffff 0%, #e8efff 100%);
}
.action-btn.delete {
    color: var(--rm-danger);
    border-color: #f1c6cf;
    background: linear-gradient(180deg, #fff8f9 0%, #fff0f3 100%);
}
.action-btn.delete:hover {
    box-shadow: 0 6px 14px rgba(207, 67, 87, 0.2);
    background: linear-gradient(180deg, #ffffff 0%, #ffe9ee 100%);
}
</style>
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
    <div class="dashboard-header">
        <div class="dashboard-header-content">
            <h1><i class='bx bx-package'></i> Products Management</h1>
            <p class="dashboard-subtitle">Manage products and raw materials inventory</p>
            <div class="materials-meta">
                <!-- <span class="materials-chip">Raw Materials<strong><?= $rawMaterialCount ?></strong></span>
                <span class="materials-chip">Suppliers<strong><?= $supplierCount ?></strong></span>
                <span class="materials-chip">View<strong><?= htmlspecialchars(ucfirst($activeTab)) ?></strong></span> -->
            </div>
        </div>
    </div>

    <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert-success"><?= htmlspecialchars($_SESSION['success']) ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
        <div class="alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['warning'])): ?>
        <div class="alert-error"><?= htmlspecialchars($_SESSION['warning']) ?></div>
        <?php unset($_SESSION['warning']); ?>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="page-container">

    <div class="tabs">
        <button type="button" id="tabProducts" class="tab-btn <?= $activeTab === 'products' ? 'active' : '' ?>"><i class='bx bx-cube'></i> Products</button>
        <button type="button" id="tabMaterials" class="tab-btn <?= $activeTab === 'materials' ? 'active' : '' ?>"><i class='bx bx-leaf'></i> Raw Materials</button>
    </div>

    <div id="productsSection" style="<?= $activeTab === 'products' ? '' : 'display:none;' ?>">
    <div class="content-card" style="margin-top:18px;">
        <div class="card-header">
            <h2><i class='bx bx-cube'></i> Product Details</h2>
        </div>
        <div class="content-body">
    <form method="POST" action="raw_materials.php">
        <input type="hidden" name="entity" value="product">
        <div class="form-grid">
            <input type="hidden" name="product_id" id="product_id">
            <div class="form-group full">
                <label for="product_code">Product Code</label>
                <input type="text" name="product_code" id="product_code" placeholder="Product Code (optional)">
            </div>
            <div class="form-group full">
                <label for="product_name">Product Name</label>
                <input type="text" name="product_name" id="product_name" placeholder="Product Name" required>
            </div>
            <div class="form-group full">
                <label for="category_id">Category</label>
                <select name="category_id" id="category_id">
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="unit">Unit</label>
                <input type="text" name="unit" id="unit" placeholder="Unit (e.g., pcs, cup)" required>
            </div>
            <div class="form-group">
                <label for="price">Unit Price</label>
                <input type="number" step="0.01" name="price" id="price" placeholder="Price" value="0.00">
            </div>

            <div class="form-group">
                <label for="initial_stock">Initial Stock</label>
                <input type="number" step="0.01" name="initial_stock" id="initial_stock" placeholder="Initial Stock" value="0">
            </div>
            <div class="form-group full">
                <label for="add_to_inventory">Add To (existing inventory)</label>
                <select name="add_to_inventory_id" id="add_to_inventory">
                    <option value="">-- Select inventory item to add stock to --</option>
                    <?php foreach ($inventory_list as $inv): ?>
                        <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['item_name']) ?> (Current: <?= number_format($inv['stock_qty'] ?? 0,2) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div><!-- /add_to_inventory form-group -->
            <div class="form-group">
                <label for="add_quantity">Add Quantity (when using Add To)</label>
                <input type="number" step="0.01" name="add_quantity" id="add_quantity" placeholder="Quantity to add" value="0">
            </div>
            <div class="form-group">
                <label for="reorder_level">Reorder Level</label>
                <input type="number" name="reorder_level" id="reorder_level" placeholder="Reorder Level" value="0">
            </div>
            <div class="form-group full">
                <label for="description">Description</label>
                <textarea name="description" id="description" placeholder="Description (optional)" rows="2"></textarea>
            </div>
            <div class="form-actions">
                <button class="btn btn-primary" type="submit"><i class='bx bx-save'></i> Save Product</button>
                <button class="btn btn-secondary" type="button" onclick="resetForm()"><i class='bx bx-reset'></i> Reset</button>
            </div>
        </div>
    </form>
    </div><!-- content-body -->
    </div><!-- content-card -->
    </div><!-- productsSection -->

    <div id="materialsSection" style="<?= $activeTab === 'materials' ? '' : 'display:none;' ?>">
    <div class="content-card" style="margin-top:18px;">
        <div class="card-header">
            <h2><i class='bx bx-leaf'></i> Raw Material Details</h2>
        </div>
        <div class="content-body">
    <form method="POST" action="raw_materials.php">
        <input type="hidden" name="entity" value="material">
        <input type="hidden" name="material_id" id="material_id">
        <div class="form-grid">
        <div class="form-group full">
            <label for="material_name">Material Name</label>
            <input class="full" type="text" name="material_name" id="material_name" placeholder="Material Name" required>
        </div>
        <div class="form-group">
            <label for="unit_m">Unit</label>
            <input type="text" name="unit_m" id="unit_m" placeholder="Unit (e.g., kg, pcs)" required>
        </div>
        <div class="form-group">
            <label for="quantity">Quantity</label>
            <input type="number" name="quantity" id="quantity" placeholder="Quantity" value="0">
        </div>
        <div class="form-group">
            <label for="cost_per_unit">Cost Per Unit</label>
            <input type="number" step="0.01" name="cost_per_unit" id="cost_per_unit" placeholder="Cost per unit" value="0.00">
        </div>
        <div class="form-group">
            <label for="reorder_level_m">Reorder Level</label>
            <input type="number" name="reorder_level_m" id="reorder_level_m" placeholder="Reorder Level" value="0">
        </div>
        <div class="form-group full">
            <label for="supplier">Supplier</label>
            <input class="full" type="text" name="supplier" id="supplier" placeholder="Supplier (optional)">
        </div>
        <div class="form-group full">
            <div class="helper-note">Tip: You can leave supplier blank. System will store it as no supplier and still save the material.</div>
        </div>
        <div class="form-actions">
            <button class="btn btn-primary" type="submit"><i class='bx bx-save'></i> Save Material</button>
            <button class="btn btn-secondary" type="button" onclick="resetMaterialForm()"><i class='bx bx-reset'></i> Reset</button>
        </div>
        </div>
    </form>
    </div><!-- content-body -->
    </div><!-- content-card -->
    </div><!-- materialsSection -->

    <div id="productsListSection" class="content-card" style="margin-top:18px;<?= $activeTab === 'products' ? '' : 'display:none;' ?>">
        <div class="card-header">
            <h2><i class='bx bx-table'></i> Products List</h2>
        </div>
    <div class="table-responsive">
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
                    <div class="action-buttons">
                        <a class="action-btn" href="#" title="Edit Product" aria-label="Edit Product" onclick='edit(<?= json_encode($p) ?>);return false;'>
                            <i class='bx bx-edit-alt'></i>
                        </a>
                        <a class="action-btn delete" href="raw_materials.php?delete_product_id=<?= $p['product_id'] ?>" title="Delete Product" aria-label="Delete Product" onclick="return confirm('Delete this product?')">
                            <i class='bx bx-trash'></i>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    </div><!-- table-responsive -->
    </div><!-- content-card -->

    <div id="materialsListSection" class="content-card" style="margin-top:18px;<?= $activeTab === 'materials' ? '' : 'display:none;' ?>">
        <div class="card-header">
            <h2><i class='bx bx-leaf'></i> Raw Materials List</h2>
        </div>
    <div class="table-responsive">
    <table class="materials-table">
        <tr>
            <th>Name</th>
            <th>Unit</th>
            <th>Quantity</th>
            <th>Cost/Unit</th>
            <th>Reorder</th>
            <th>Supplier</th>
            <th>Last Updated</th>
            <th>Actions</th>
        </tr>
        <?php
        $materials = [];
        $r = $conn->query("SHOW TABLES LIKE 'raw_materials'");
        if ($r && $r->num_rows > 0) {
            $rm = $conn->query("SELECT rm.*, s.supplier_name AS supplier FROM raw_materials rm LEFT JOIN suppliers s ON rm.supplier_id = s.supplier_id ORDER BY rm.material_id DESC");
            if ($rm) $materials = $rm->fetch_all(MYSQLI_ASSOC);
        }
        if (empty($materials)): ?>
            <tr><td colspan="8" class="empty-message">No raw materials found.</td></tr>
        <?php else: foreach ($materials as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['material_name']) ?></td>
                <td><?= htmlspecialchars($m['unit']) ?></td>
                <td><?= htmlspecialchars($m['quantity']) ?></td>
                <td>₱<?= number_format((float)($m['cost_per_unit'] ?? 0), 2) ?></td>
                <td><?= htmlspecialchars($m['reorder_level']) ?></td>
                <td><?= htmlspecialchars($m['supplier'] ?? '') ?></td>
                <td><?= htmlspecialchars($m['last_updated'] ?? '—') ?></td>
                <td>
                    <div class="action-buttons">
                        <a class="action-btn" href="#" title="Edit Material" aria-label="Edit Material" onclick='editMaterial(<?= json_encode($m) ?>);return false;'>
                            <i class='bx bx-edit-alt'></i>
                        </a>
                        <a class="action-btn delete" href="raw_materials.php?delete_material_id=<?= $m['material_id'] ?>" title="Delete Material" aria-label="Delete Material" onclick="return confirm('Delete this material?')">
                            <i class='bx bx-trash'></i>
                        </a>
                    </div>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
    </div><!-- table-responsive -->
    </div><!-- content-card (raw materials) -->

    </div><!-- page-container -->
</div><!-- main -->

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
    document.getElementById('cost_per_unit').value = (m.cost_per_unit !== undefined) ? parseFloat(m.cost_per_unit) : 0;
    document.getElementById('reorder_level_m').value = m.reorder_level || 0;
    document.getElementById('supplier').value = m.supplier || '';
    window.scrollTo(0,0);
}
function resetMaterialForm(){
    document.getElementById('material_id').value='';
    document.getElementById('material_name').value='';
    document.getElementById('unit_m').value='';
    document.getElementById('quantity').value=0;
    document.getElementById('cost_per_unit').value='0.00';
    document.getElementById('reorder_level_m').value=0;
    document.getElementById('supplier').value='';
}

var rawMaterialDebug = <?= json_encode($rawMaterialDebugOutput, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
if (rawMaterialDebug) {
    console.group('Raw Materials Debug');
    console.log('Server Debug:', rawMaterialDebug);
    console.groupEnd();
}

var materialForm = document.querySelector("form input[name='entity'][value='material']")?.closest('form');
if (materialForm) {
    materialForm.addEventListener('submit', function() {
        console.group('Raw Materials Submit');
        console.log('entity:', 'material');
        console.log('material_id:', document.getElementById('material_id')?.value);
        console.log('material_name:', document.getElementById('material_name')?.value);
        console.log('unit_m:', document.getElementById('unit_m')?.value);
        console.log('quantity:', document.getElementById('quantity')?.value);
        console.log('cost_per_unit:', document.getElementById('cost_per_unit')?.value);
        console.log('reorder_level_m:', document.getElementById('reorder_level_m')?.value);
        console.log('supplier:', document.getElementById('supplier')?.value);
        console.groupEnd();
    });
}

// Tabs
document.getElementById('tabProducts').addEventListener('click', function(){
    document.getElementById('productsSection').style.display = '';
    document.getElementById('productsListSection').style.display = '';
    document.getElementById('materialsSection').style.display = 'none';
    document.getElementById('materialsListSection').style.display = 'none';
    this.classList.add('active');
    document.getElementById('tabMaterials').classList.remove('active');
    window.history.replaceState({}, '', 'raw_materials.php?tab=products');
});
document.getElementById('tabMaterials').addEventListener('click', function(){
    document.getElementById('productsSection').style.display = 'none';
    document.getElementById('productsListSection').style.display = 'none';
    document.getElementById('materialsSection').style.display = '';
    document.getElementById('materialsListSection').style.display = '';
    this.classList.add('active');
    document.getElementById('tabProducts').classList.remove('active');
    window.history.replaceState({}, '', 'raw_materials.php?tab=materials');
});
</script>

</body>
</html>
