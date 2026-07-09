<?php
session_start();
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

function jsonApi(int $code, string $msg, $data = null): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

function getParam(string $key, $default = '') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        return isset($_POST[$key]) ? trim((string)$_POST[$key]) : $default;
    }
    return isset($_GET[$key]) ? trim((string)$_GET[$key]) : $default;
}

$action = getParam('action', '');
$ajax = getParam('ajax', '');
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $user = loginAdmin($username, $password);
    if ($user) {
        header('Location: kejinshou.php');
        exit;
    }
    $errorMessage = '账号或密码错误，请重试。';
}

if ($action === 'logout') {
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    header('Location: kejinshou.php');
    exit;
}

if ($ajax !== '') {
    $user = requireLogin();
    $db = Database::getInstance();

    if ($ajax === 'sessions' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = [];
        $stmt = $db->prepare('SELECT session_id, MAX(created_at) AS last_at, COUNT(*) AS message_count FROM chat_messages GROUP BY session_id ORDER BY last_at DESC LIMIT 100');
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        jsonApi(0, 'ok', $rows);
    }

    if ($ajax === 'orders') {
        $rows = [];
        $stmt = $db->prepare('SELECT id, order_no, user_name, amount, status, created_at FROM orders ORDER BY id DESC LIMIT 200');
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        jsonApi(0, 'ok', $rows);
    }

    if ($ajax === 'update_order' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $status = trim((string)($_POST['status'] ?? ''));
        if ($id <= 0 || $status === '') {
            jsonApi(400, '订单ID或状态无效');
        }
        $stmt = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        if (!$stmt) {
            jsonApi(500, '数据库准备失败');
        }
        $stmt->bind_param('si', $status, $id);
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();
        jsonApi(0, '状态更新成功', ['updated' => $updated]);
    }

    if ($ajax === 'config_list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $rows = [];
        $stmt = $db->prepare('SELECT config_key, config_value FROM system_configs ORDER BY id');
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        }
        jsonApi(0, 'ok', $rows);
    }

    if ($ajax === 'save_config' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $key = trim((string)($_POST['config_key'] ?? ''));
        $value = trim((string)($_POST['config_value'] ?? ''));
        if ($key === '') {
            jsonApi(400, '配置项不能为空');
        }
        $stmt = $db->prepare('INSERT INTO system_configs (config_key, config_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE config_value = VALUES(config_value)');
        if (!$stmt) {
            jsonApi(500, '数据库准备失败');
        }
        $stmt->bind_param('ss', $key, $value);
        $stmt->execute();
        $stmt->close();
        jsonApi(0, '保存成功');
    }

    jsonApi(404, '未找到对应接口');
}

$loggedIn = !empty($_SESSION['admin_id']);
if (!$loggedIn) {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" /><title>后台登录</title><style>body{margin:0;font-family:Arial,sans-serif;background:#f3f5f9;display:flex;justify-content:center;align-items:center;height:100vh;} .panel{width:min(420px,95vw);background:#fff;padding:24px;border-radius:12px;box-shadow:0 12px 40px rgba(0,0,0,.08);} .panel h1{margin-top:0;font-size:1.8rem;color:#111;} .panel input{width:100%;margin:12px 0;padding:12px;border:1px solid #d5d9e0;border-radius:8px;font-size:1rem;} .panel button{width:100%;padding:12px;border:none;border-radius:8px;background:#2563eb;color:#fff;font-size:1rem;cursor:pointer;} .hint{color:#d32f2f;margin-top:12px;font-size:.95rem;}</style></head><body><div class="panel"><h1>客服后台登录</h1><form method="post" action="kejinshou.php"><input type="hidden" name="action" value="login" /><input name="username" type="text" placeholder="管理员账号" value="hxzc33" required /><input name="password" type="password" placeholder="管理员密码" required /><button type="submit">登录</button>' . ($errorMessage ? '<div class="hint">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>' : '') . '</form></div></body></html>';
    exit;
}

$adminName = htmlspecialchars($_SESSION['admin_username'] ?? '管理员', ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>客服后台管理</title>
    <style>
        :root{font-size:16px;color-scheme:light;}
        *{box-sizing:border-box;}
        body{margin:0;font-family:PingFang SC,Helvetica,Arial,sans-serif;background:#f4f7fb;color:#17212b;}
        header{background:#1f2937;color:#fff;padding:16px 20px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;}
        header h1{margin:0;font-size:1.1rem;}
        header nav{display:flex;gap:12px;flex-wrap:wrap;}
        header a, header button{color:#fff;text-decoration:none;border:none;background:transparent;font-size:.95rem;cursor:pointer;}
        header button{padding:0;}
        .wrapper{display:grid;grid-template-columns:1fr;gap:16px;padding:16px;max-width:1200px;margin:0 auto;}
        .card{background:#fff;border-radius:16px;box-shadow:0 8px 30px rgba(15,23,42,.08);padding:18px;}
        .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;}
        .tabs button{flex:1 1 0;min-width:120px;padding:12px 14px;border:1px solid #e5e7eb;border-radius:12px;background:#f9fafb;color:#111;cursor:pointer;transition:all .2s;}
        .tabs button.active{background:#2563eb;color:#fff;border-color:#2563eb;}
        .section{display:none;}
        .section.active{display:block;}
        .grid-2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
        .form-row{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;}
        .form-row input,.form-row select{flex:1 1 200px;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px;}
        .form-row button{padding:10px 14px;border:none;border-radius:10px;background:#2563eb;color:#fff;cursor:pointer;}
        .table-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:.95rem;}
        th,td{padding:12px 10px;text-align:left;border-bottom:1px solid #e5e7eb;}
        th{background:#f8fafc;font-weight:600;}
        tr:hover{background:#f8fafc;}
        .tag{display:inline-flex;align-items:center;padding:4px 9px;border-radius:999px;font-size:.85rem;background:#e5e7eb;color:#111;}
        .textarea{width:100%;min-height:120px;padding:12px;border:1px solid #d1d5db;border-radius:12px;resize:vertical;}
        .button-ghost{padding:8px 12px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#111;cursor:pointer;}
        .info-box{display:grid;grid-template-columns:1fr;gap:8px;}
        @media (max-width:768px){.grid-2{grid-template-columns:1fr;}}
    </style>
</head>
<body>
<header>
    <h1>客服后台管理</h1>
    <nav>
        <span>管理员：<?php echo $adminName; ?></span>
        <a href="kejinshou.php?action=logout">退出登录</a>
    </nav>
</header>
<main class="wrapper">
    <div class="card">
        <div class="tabs">
            <button class="tab-button active" data-section="chat">客服对话</button>
            <button class="tab-button" data-section="orders">订单管理</button>
            <button class="tab-button" data-section="config">系统配置</button>
            <button class="tab-button" data-section="info">系统信息</button>
        </div>
        <div id="chat" class="section active">
            <div class="grid-2">
                <div>
                    <h2>对话会话列表</h2>
                    <div class="table-wrap"><table id="sessionTable"><thead><tr><th>会话ID</th><th>消息数</th><th>最后更新时间</th><th>操作</th></tr></thead><tbody></tbody></table></div>
                </div>
                <div>
                    <h2>消息内容</h2>
                    <div class="table-wrap"><table id="messageTable"><thead><tr><th>角色</th><th>内容</th><th>时间</th></tr></thead><tbody></tbody></table></div>
                </div>
            </div>
            <div style="margin-top:18px;">
                <div class="form-row">
                    <input id="chatSessionId" type="text" placeholder="会话ID" />
                    <button type="button" onclick="loadChatMessages()">加载会话</button>
                </div>
                <textarea id="chatContent" class="textarea" placeholder="输入回复内容"></textarea>
                <div style="display:flex;gap:12px;flex-wrap:wrap;"><button class="button-ghost" type="button" onclick="sendChat()">发送回复</button><button class="button-ghost" type="button" onclick="refreshSessions()">刷新会话列表</button></div>
                <p style="margin-top:10px;color:#6b7280;">请先选择或输入会话ID，然后发送回复。</p>
            </div>
        </div>
        <div id="orders" class="section">
            <h2>订单列表</h2>
            <div class="table-wrap"><table id="orderTable"><thead><tr><th>订单号</th><th>用户</th><th>金额</th><th>状态</th><th>创建时间</th><th>操作</th></tr></thead><tbody></tbody></table></div>
            <div style="margin-top:18px;">
                <h3>创建新订单</h3>
                <div class="form-row">
                    <input id="newOrderNo" type="text" placeholder="订单号，可留空自动生成" />
                    <input id="newUserName" type="text" placeholder="用户名" />
                    <input id="newAmount" type="number" step="0.01" placeholder="金额" />
                    <select id="newOrderStatus"><option value="pending">待处理</option><option value="paid">已支付</option><option value="shipped">已发货</option><option value="completed">已完成</option><option value="canceled">已取消</option></select>
                    <button type="button" onclick="createOrder()">创建订单</button>
                </div>
            </div>
        </div>
        <div id="config" class="section">
            <h2>系统配置</h2>
            <div class="table-wrap"><table id="configTable"><thead><tr><th>键</th><th>值</th><th>操作</th></tr></thead><tbody></tbody></table></div>
            <div style="margin-top:18px;">
                <div class="form-row"><input id="configKey" type="text" placeholder="配置键" /><input id="configValue" type="text" placeholder="配置值" /><button type="button" onclick="saveConfig()">保存配置</button></div>
                <p style="margin-top:10px;color:#6b7280;">配置会自动写入数据库，可用于网站参数、API钥匙等。</p>
            </div>
        </div>
        <div id="info" class="section">
            <h2>系统信息</h2>
            <div class="info-box"><div>登录用户：<?php echo $adminName; ?></div><div>入口地址：kejinshou.php</div><div>当前时间：<span id="currentTime"></span></div></div>
            <div style="margin-top:14px;"><button class="button-ghost" type="button" onclick="refreshAll()">刷新全部数据</button></div>
        </div>
    </div>
</main>
<script>
const tabButtons = document.querySelectorAll('.tab-button');
const sections = document.querySelectorAll('.section');
let currentSession = '';

function switchTab(sectionId) {
    tabButtons.forEach(btn => btn.classList.toggle('active', btn.dataset.section === sectionId));
    sections.forEach(sec => sec.classList.toggle('active', sec.id === sectionId));
}

tabButtons.forEach(btn => btn.addEventListener('click', () => switchTab(btn.dataset.section)));

function jsonFetch(url, options = {}) {
    options.credentials = 'same-origin';
    return fetch(url, options).then(r => r.json());
}

function refreshSessions() {
    jsonFetch('kejinshou.php?ajax=sessions').then(data => {
        const tbody = document.querySelector('#sessionTable tbody');
        tbody.innerHTML = '';
        if (data.code !== 0) return;
        data.data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${escapeHtml(item.session_id)}</td><td>${item.message_count}</td><td>${item.last_at}</td><td><button type="button" onclick="selectSession('${escapeJs(item.session_id)}')">查看</button></td>`;
            tbody.appendChild(tr);
        });
    });
}

function selectSession(sessionId) {
    document.getElementById('chatSessionId').value = sessionId;
    loadChatMessages();
}

function loadChatMessages() {
    currentSession = document.getElementById('chatSessionId').value.trim();
    if (!currentSession) {
        alert('请先输入会话ID');
        return;
    }
    jsonFetch('api/chat.php?session_id=' + encodeURIComponent(currentSession)).then(data => {
        const tbody = document.querySelector('#messageTable tbody');
        tbody.innerHTML = '';
        if (data.code !== 0) return;
        data.data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${escapeHtml(item.role)}</td><td>${escapeHtml(item.content)}</td><td>${item.created_at || ''}</td>`;
            tbody.appendChild(tr);
        });
    });
}

function sendChat() {
    const sessionId = document.getElementById('chatSessionId').value.trim();
    const content = document.getElementById('chatContent').value.trim();
    if (!sessionId || !content) {
        alert('请填写会话ID和回复内容');
        return;
    }
    jsonFetch('api/chat.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({session_id: sessionId, content})
    }).then(data => {
        if (data.code === 0) {
            document.getElementById('chatContent').value = '';
            loadChatMessages();
            refreshSessions();
        } else {
            alert(data.msg || '发送失败');
        }
    });
}

function refreshOrders() {
    jsonFetch('kejinshou.php?ajax=orders').then(data => {
        const tbody = document.querySelector('#orderTable tbody');
        tbody.innerHTML = '';
        if (data.code !== 0) return;
        data.data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${escapeHtml(item.order_no)}</td><td>${escapeHtml(item.user_name)}</td><td>${item.amount}</td><td>${escapeHtml(item.status)}</td><td>${item.created_at}</td><td><select onchange="updateOrderStatus(${item.id}, this.value)"><option${item.status==='pending'?' selected':''} value="pending">待处理</option><option${item.status==='paid'?' selected':''} value="paid">已支付</option><option${item.status==='shipped'?' selected':''} value="shipped">已发货</option><option${item.status==='completed'?' selected':''} value="completed">已完成</option><option${item.status==='canceled'?' selected':''} value="canceled">已取消</option></select></td>`;
            tbody.appendChild(tr);
        });
    });
}

function updateOrderStatus(id, status) {
    const form = new FormData();
    form.append('ajax', 'update_order');
    form.append('id', id);
    form.append('status', status);
    fetch('kejinshou.php', {method: 'POST', body: form, credentials: 'same-origin'}).then(r => r.json()).then(data => {
        if (data.code !== 0) {
            alert(data.msg || '更新失败');
        }
    });
}

function createOrder() {
    const orderNo = document.getElementById('newOrderNo').value.trim();
    const userName = document.getElementById('newUserName').value.trim();
    const amount = parseFloat(document.getElementById('newAmount').value) || 0;
    const status = document.getElementById('newOrderStatus').value;
    jsonFetch('api/orders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({order_no: orderNo, user_name: userName, amount, status})
    }).then(data => {
        if (data.code === 0) {
            alert('订单创建成功');
            document.getElementById('newOrderNo').value = '';
            document.getElementById('newUserName').value = '';
            document.getElementById('newAmount').value = '';
            refreshOrders();
        } else {
            alert(data.msg || '创建失败');
        }
    });
}

function refreshConfig() {
    jsonFetch('api/config.php').then(data => {
        const tbody = document.querySelector('#configTable tbody');
        tbody.innerHTML = '';
        if (data.code !== 0) return;
        data.data.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${escapeHtml(item.config_key)}</td><td>${escapeHtml(item.config_value)}</td><td><button type="button" class="button-ghost" onclick="editConfig('${escapeJs(item.config_key)}','${escapeJs(item.config_value)}')">编辑</button></td>`;
            tbody.appendChild(tr);
        });
    });
}

function saveConfig() {
    const key = document.getElementById('configKey').value.trim();
    const value = document.getElementById('configValue').value.trim();
    if (!key) {
        alert('请输入配置键');
        return;
    }
    const form = new FormData();
    form.append('ajax', 'save_config');
    form.append('config_key', key);
    form.append('config_value', value);
    fetch('kejinshou.php', {method:'POST',body:form,credentials:'same-origin'}).then(r => r.json()).then(data => {
        if (data.code === 0) {
            alert('配置已保存');
            document.getElementById('configKey').value = '';
            document.getElementById('configValue').value = '';
            refreshConfig();
        } else {
            alert(data.msg || '保存失败');
        }
    });
}

function editConfig(key, value) {
    document.getElementById('configKey').value = key;
    document.getElementById('configValue').value = value;
    switchTab('config');
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, tag => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[tag]));
}

function escapeJs(value) {
    return String(value).replace(/['"\\]/g, '\\$&');
}

function refreshAll() {
    refreshSessions();
    refreshOrders();
    refreshConfig();
    document.getElementById('currentTime').textContent = new Date().toLocaleString();
}

window.addEventListener('load', () => {
    refreshAll();
    setInterval(() => { document.getElementById('currentTime').textContent = new Date().toLocaleString(); }, 1000);
});
</script>
</body>
</html>
