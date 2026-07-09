<?php
require_once __DIR__ . '/db.php';

function apiResponse($code = 0, $msg = 'success', $data = null): array {
    return ['code' => $code, 'msg' => $msg, 'data' => $data];
}

function jsonResponse($code = 0, $msg = 'success', $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(apiResponse($code, $msg, $data), JSON_UNESCAPED_UNICODE);
}

function getJsonBody(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function requireLogin(): array {
    session_start();
    if (empty($_SESSION['admin_id'])) {
        http_response_code(401);
        jsonResponse(401, '未登录');
        exit;
    }
    return [
        'id' => (int) $_SESSION['admin_id'],
        'username' => $_SESSION['admin_username'] ?? '',
    ];
}

function loginAdmin(string $username, string $password): ?array {
    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT id, username, password_hash FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return null;
    }
    if (($user['password_hash'] ?? '') !== md5($password)) {
        return null;
    }
    session_start();
    $_SESSION['admin_id'] = (int) $user['id'];
    $_SESSION['admin_username'] = $user['username'];
    return [
        'id' => (int) $user['id'],
        'username' => $user['username'],
    ];
}

function hashPassword(string $password): string {
    return md5($password);
}
