<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$db = Database::getInstance();
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $session = trim((string)($_GET['session_id'] ?? 'default'));
    $stmt = $db->prepare('SELECT role, content, created_at FROM chat_messages WHERE session_id = ? ORDER BY id ASC LIMIT 50');
    $stmt->bind_param('s', $session);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
    $stmt->close();
    jsonResponse(0, 'ok', $rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonBody();
    $session = trim((string)($data['session_id'] ?? 'default'));
    $content = trim((string)($data['content'] ?? ''));
    $stmt = $db->prepare('INSERT INTO chat_messages (session_id, role, content) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $session, $role, $content);
    $role = 'user';
    $stmt->execute();
    $stmt->close();
    $reply = '已收到消息：' . $content;
    $stmt = $db->prepare('INSERT INTO chat_messages (session_id, role, content) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $session, $role, $reply);
    $role = 'assistant';
    $stmt->execute();
    $stmt->close();
    jsonResponse(0, 'ok');
}
