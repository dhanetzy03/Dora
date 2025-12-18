<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db_connect.php';

$id = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;
if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'invalid_id']);
    exit;
}

$stmt = $conn->prepare("SELECT product_id, product_code, product_name, unit, 
    CASE 
        WHEN COALESCE(price, '') = '' THEN NULL
        ELSE price
    END AS price 
    FROM products WHERE product_id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    echo json_encode(['success' => true, 'product' => $row]);
} else {
    echo json_encode(['success' => false, 'error' => 'not_found']);
}

$stmt->close();
exit;
