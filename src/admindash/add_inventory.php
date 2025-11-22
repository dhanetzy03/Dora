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

?>
