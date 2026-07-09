<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = Database::getInstance();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->query('SELECT * FROM orders ORDER BY id DESC LIMIT 100');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    jsonResponse(0, 'ok', $rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonBody();
    $orderNo = trim((string)($data['order_no'] ?? '')) ?: 'ORDER-' . time();
    $userName = (string)($data['user_name'] ?? '');
    $amount = (float)($data['amount'] ?? 0);
    $status = (string)($data['status'] ?? 'pending');
    $stmt = $db->prepare('INSERT INTO orders (order_no, user_name, amount, status) VALUES (?, ?, ?, ?)');
    $stmt->bind_param('ssds', $orderNo, $userName, $amount, $status);
    $stmt->execute();
    $stmt->close();
    jsonResponse(0, '创建成功', ['order_no' => $orderNo]);
}
