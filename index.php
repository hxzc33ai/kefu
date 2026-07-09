<?php
require_once __DIR__ . '/includes/auth.php';
$user = requireLogin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>后台首页</title></head>
<body>
<h1>后台首页</h1>
<p>欢迎，<?php echo htmlspecialchars($user['username']); ?></p>
<p><a href="api/health.php">检查接口</a></p>
</body>
</html>
