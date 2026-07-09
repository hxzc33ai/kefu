<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = getJsonBody();
    $username = trim((string)($data['username'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $user = loginAdmin($username, $password);
    if ($user) {
        jsonResponse(0, '登录成功', $user);
    } else {
        http_response_code(401);
        jsonResponse(401, '账号或密码错误');
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>后台登录</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f7fb;margin:0;display:flex;align-items:center;justify-content:center;height:100vh;} .card{background:#fff;padding:24px;border-radius:8px;box-shadow:0 10px 30px rgba(0,0,0,.1);width:360px;} input{width:100%;padding:10px;margin:8px 0;border:1px solid #ddd;border-radius:4px;} button{width:100%;padding:10px;background:#2563eb;color:#fff;border:none;border-radius:4px;cursor:pointer;}
</style>
</head>
<body>
<div class="card">
  <h2>后台登录</h2>
  <input id="username" placeholder="账号" value="hxzc33" />
  <input id="password" type="password" placeholder="密码" value="123456" />
  <button onclick="doLogin()">登录</button>
  <div id="msg" style="margin-top:10px;color:red"></div>
</div>
<script>
async function doLogin(){
  const msg=document.getElementById('msg');
  msg.textContent='';
  const res=await fetch('login.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:document.getElementById('username').value,password:document.getElementById('password').value})});
  const data=await res.json();
  if(data.code===0){window.location.href='index.php';} else {msg.textContent=data.msg;}
}
</script>
</body>
</html>
