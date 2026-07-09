<?php
require_once __DIR__ . '/../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = requireLogin();
    jsonResponse(0, 'ok', $user);
}
