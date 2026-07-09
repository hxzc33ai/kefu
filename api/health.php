<?php
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
jsonResponse(0, 'ok', ['service' => 'kefu-php', 'time' => date('Y-m-d H:i:s')]);
