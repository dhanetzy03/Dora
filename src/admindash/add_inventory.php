<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

// Simple helper to redirect back to inventory with a flash message
function back($key, $msg) {
    $_SESSION[$key] = $msg;
    header('Location: inventory.php');
    exit();
}

$item_name = trim($_POST['item_name'] ?? '');
$category = trim($_POST['category'] ?? '');
$unit = trim($_POST['unit'] ?? 'pcs');
$stock_qty = isset($_POST['stock_qty']) ? (int)$_POST['stock_qty'] : 0;
$reorder_level_input = isset($_POST['reorder_level']) ? trim($_POST['reorder_level']) : '';
$reorder_level = ($reorder_level_input === '') ? null : (int)$reorder_level_input;

if ($item_name === '' || $category === '') {
    back('error', 'Item name and category are required.');
}

// Check if item already exists (case-insensitive)
$check = $conn->prepare("SELECT id, stock_qty, reorder_level, stock_in FROM inventory WHERE LOWER(item_name) = LOWER(?) LIMIT 1");
$check->bind_param("s", $item_name);
$check->execute();
$result = $check->get_result();

if ($result && $result->num_rows > 0) {
    // Existing item: increment quantities
    $item = $result->fetch_assoc();
    $new_stock = $item['stock_qty'] + $stock_qty;
    $new_stock_in = $item['stock_in'] + $stock_qty;
    $reorder_final = $reorder_level ?? $item['reorder_level'];

    // Determine status
    if ($new_stock <= 0) {
        $status = 'Out of Stock';
    } elseif ($reorder_final !== null && $new_stock <= $reorder_final) {
        $status = 'Low Stock';
    } else {
        $status = 'Sufficient';
    }

    if ($reorder_level !== null) {
        // types: stock_qty (i), reorder_level (i), stock_in (i), status (s), unit (s), id (i)
        $update = $conn->prepare("UPDATE inventory SET stock_qty=?, reorder_level=?, stock_in=?, status=?, unit=?, last_updated=NOW() WHERE id=?");
        $update->bind_param("iiissi", $new_stock, $reorder_final, $new_stock_in, $status, $unit, $item['id']);
    } else {
        // types: stock_qty (i), stock_in (i), status (s), unit (s), id (i)
        $update = $conn->prepare("UPDATE inventory SET stock_qty=?, stock_in=?, status=?, unit=?, last_updated=NOW() WHERE id=?");
        $update->bind_param("iissi", $new_stock, $new_stock_in, $status, $unit, $item['id']);
    }

    if ($update->execute()) {
        back('success', 'Existing item updated with additional quantity.');
    } else {
        back('error', 'Failed to update existing item.');
    }

    // Sync updated inventory to products.stock if a matching product exists
    $syncName = $item_name;
    if (!empty($syncName)) {
        $prodStmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? LIMIT 1");
        $prodStmt->bind_param("s", $syncName);
        $prodStmt->execute();
        $prodRes = $prodStmt->get_result();
        if ($prodRes && $prodRes->num_rows > 0) {
            $prodId = $prodRes->fetch_assoc()['product_id'];
            $prodStmt->close();
            // set stock equal to new_stock
            $s2 = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
            $s2->bind_param("i", $prodId);
            $s2->execute();
            $sr2 = $s2->get_result();
            if ($sr2 && $sr2->num_rows > 0) {
                $sid2 = $sr2->fetch_assoc()['stock_id'];
                $s2->close();
                $u2 = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
                $u2->bind_param("ii", $new_stock, $sid2);
                $u2->execute();
                $u2->close();
            } else {
                $s2->close();
                $ins2 = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
                $ins2->bind_param("ii", $prodId, $new_stock);
                $ins2->execute();
                $ins2->close();
            }
        } else {
            $prodStmt->close();
        }
    }

} else {
    // New item: reorder level is required
    if ($reorder_level === null) {
        back('error', 'Reorder level is required for new items.');
    }

    $status = ($stock_qty <= 0) ? 'Out of Stock' : (($stock_qty <= $reorder_level) ? 'Low Stock' : 'Sufficient');
    $stock_in = $stock_qty;
    $stock_out = 0;

    $stmt = $conn->prepare("INSERT INTO inventory (item_name, category, stock_qty, reorder_level, status, unit, stock_in, stock_out, last_updated) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("ssiissii", $item_name, $category, $stock_qty, $reorder_level, $status, $unit, $stock_in, $stock_out);

    if ($stmt->execute()) {
        back('success', 'New item added to inventory.');
    } else {
        back('error', 'Failed to add new item.');
    }
}

// After adding new inventory item, sync to products.stock when names match
$syncName = $item_name;
$syncQty = $stock_qty;
if (!empty($syncName)) {
    $prodStmt = $conn->prepare("SELECT product_id FROM products WHERE product_name = ? LIMIT 1");
    $prodStmt->bind_param("s", $syncName);
    $prodStmt->execute();
    $prodRes = $prodStmt->get_result();
    if ($prodRes && $prodRes->num_rows > 0) {
        $prodId = $prodRes->fetch_assoc()['product_id'];
        $prodStmt->close();
        $s3 = $conn->prepare("SELECT stock_id FROM stock WHERE product_id = ? LIMIT 1");
        $s3->bind_param("i", $prodId);
        $s3->execute();
        $sr3 = $s3->get_result();
        if ($sr3 && $sr3->num_rows > 0) {
            $sid3 = $sr3->fetch_assoc()['stock_id'];
            $s3->close();
            $u3 = $conn->prepare("UPDATE stock SET quantity = ?, last_updated = NOW() WHERE stock_id = ?");
            $u3->bind_param("ii", $syncQty, $sid3);
            $u3->execute();
            $u3->close();
        } else {
            $s3->close();
            $ins3 = $conn->prepare("INSERT INTO stock (product_id, quantity, last_updated) VALUES (?, ?, NOW())");
            $ins3->bind_param("ii", $prodId, $syncQty);
            $ins3->execute();
            $ins3->close();
        }
    } else {
        $prodStmt->close();
    }
}

?>
