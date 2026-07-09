<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = Database::getInstance();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->query('SELECT config_key, config_value FROM system_configs ORDER BY id');
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    jsonResponse(0, 'ok', $rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonBody();
    $key = trim((string)($data['config_key'] ?? ''));
    $value = (string)($data['config_value'] ?? '');
    $stmt = $db->prepare('INSERT INTO system_configs (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
    $stmt->close();
    jsonResponse(0, '保存成功');
    exit;
}
