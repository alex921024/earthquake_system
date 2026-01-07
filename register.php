<?php
require 'db.php';

$msg = "";
if (isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($username) || empty($password)) {
        $msg = "欄位請勿留空";
    } elseif ($password !== $confirm) {
        $msg = "兩次密碼輸入不一致";
    } else {
        // 檢查帳號是否存在
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $msg = "此帳號已被註冊";
        } else {
            // 使用 SHA-256 加密
            $hash = hash('sha256', $password);
            
            // 預設 role 為 1 (一般使用者)
            $sql = "INSERT INTO users (username, password, role) VALUES (?, ?, 1)";
            if ($pdo->prepare($sql)->execute([$username, $hash])) {
                header("Location: login.php?status=registered");
                exit;
            } else {
                $msg = "註冊失敗，請重試";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>註冊 - 地震觀測網</title>
    <style>
        body { font-family: sans-serif; background: #121212; color: #e0e0e0; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .box { background: #1e1e1e; padding: 40px; border-radius: 12px; border: 1px solid #333; width: 300px; text-align: center; }
        input { width: 100%; padding: 10px; margin: 10px 0; background: #2c2c2c; border: 1px solid #444; color: #fff; border-radius: 5px; box-sizing: border-box; }
        button { width: 100%; padding: 10px; background: #536dfe; color: white; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px; font-weight: bold; }
        button:hover { background: #3d5afe; }
        .error { color: #ff5252; font-size: 0.9em; margin-bottom: 10px; }
        a { color: #90caf9; text-decoration: none; font-size: 0.9em; }
    </style>
</head>
<body>
    <div class="box">
        <h2>註冊新帳號</h2>
        <?php if($msg): ?><div class="error"><?= $msg ?></div><?php endif; ?>
        <form method="POST">
            <input type="text" name="username" placeholder="使用者名稱" required>
            <input type="password" name="password" placeholder="密碼" required>
            <input type="password" name="confirm_password" placeholder="確認密碼" required>
            <button type="submit" name="register">註冊</button>
        </form>
        <p><a href="login.php">已經有帳號？登入</a></p>
    </div>
</body>
</html>