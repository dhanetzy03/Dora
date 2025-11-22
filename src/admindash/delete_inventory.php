<?php
session_start();
if (!isset($_SESSION["username"]) || $_SESSION["role"] !== "admin") {
    header("Location: ../auth/login.php");
    exit();
}
require_once "../../config/db_connect.php";

function back($key, $msg) {
    $_SESSION[$key] = $msg;
    header('Location: inventory.php');
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    back('error', 'Invalid item id.');
}

$stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
$stmt->bind_param('i', $id);
if ($stmt->execute()) {
    back('success', 'Item deleted successfully.');
} else {
    back('error', 'Failed to delete item.');
}

?>
