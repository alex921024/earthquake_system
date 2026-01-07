<?php
require 'db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$msg = "";
if (isset($_GET['status']) && $_GET['status'] == 'registered') {
    $msg = "<span style='color:#69f0ae'>註冊成功，請登入！</span>";
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // SHA-256 比對
    if ($user && $user['password'] === hash('sha256', $password)) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // 存入權限
        header("Location: index.php");
        exit;
    } else {
        $msg = "帳號或密碼錯誤";
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>登入 - 地震觀測網</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 12px; border: 1px solid #333; width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #2c2c2c; border: 1px solid #444; color: #fff; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #536dfe; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; font-weight: bold; }
        button:hover { background: #3d5afe; }
        .msg { color: #ff5252; font-size: 0.9em; margin-bottom: 10px; }
        a { color: #90caf9; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="box">
        <h2>登入系統</h2>
        <?php if($msg): ?><div class="msg"><?= $msg ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="使用者名稱" required>
            <input type="password" name="password" placeholder="密碼" required>
            <button type="submit" name="login">登入</button>
        </form>
        <p><a href="register.php">還沒有帳號？註冊</a></p>
    </div>
</body>
</html>