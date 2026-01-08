<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 0) {
    header("Location: index.php");
    exit;
}

$msg = "";

if (isset($_POST['add_eq'])) {
    $time = $_POST['origin_time'];
    $mag = $_POST['magnitude'];
    $depth = $_POST['depth'];
    $lat = $_POST['latitude'];
    $lon = $_POST['longitude'];
    $loc = $_POST['location_desc'];

    $sql = "INSERT INTO earthquake_data (origin_time, magnitude, depth, latitude, longitude, location_desc) 
            VALUES (?, ?, ?, ?, ?, ?)";
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$time, $mag, $depth, $lat, $lon, $loc]);
        $msg = "✅ 地震資料新增成功！";
    } catch (PDOException $e) {
        $msg = "❌ 新增失敗：" . $e->getMessage();
    }
}

if (isset($_POST['del_eq'])) {
    $id = $_POST['eq_id'];
    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM fear_reports WHERE earthquake_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM earthquake_data WHERE id = ?")->execute([$id]);
        $pdo->commit();
        $msg = "✅ 地震資料 (ID: $id) 已刪除";
    } catch (Exception $e) {
        $pdo->rollBack();
        $msg = "❌ 刪除失敗";
    }
}

if (isset($_POST['edit_user_role'])) {
    $uid = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$new_role, $uid]);
    $msg = "✅ 使用者 (ID: $uid) 權限已更新";
}

if (isset($_POST['del_user'])) {
    $uid = $_POST['user_id'];
    if ($uid == $_SESSION['user_id']) {
        $msg = "❌ 您不能刪除自己的管理員帳號";
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM fear_reports WHERE user_id = ?")->execute([$uid]);
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
            $pdo->commit();
            $msg = "✅ 使用者 (ID: $uid) 已刪除";
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg = "❌ 刪除失敗";
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll();
$earthquakes = $pdo->query("SELECT * FROM earthquake_data ORDER BY origin_time DESC LIMIT 50")->fetchAll();
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>後台管理系統</title>
    <style>
        :root { --bg: #121212; --card: #1e1e1e; --text: #e0e0e0; --accent: #d32f2f; --border: #333; }
        body { background: var(--bg); color: var(--text); font-family: sans-serif; padding: 20px; }
        .container { max-width: 1000px; margin: 0 auto; }
        h1, h2 { border-left: 5px solid var(--accent); padding-left: 10px; }
        .msg { background: #333; color: #69f0ae; padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: var(--card); }
        th, td { padding: 12px; border-bottom: 1px solid var(--border); text-align: left; }
        th { background: #2c2c2c; }
        tr:hover { background: #252526; }

        .form-box { background: var(--card); padding: 20px; border-radius: 10px; margin-bottom: 30px; border: 1px solid var(--border); }
        
        /* Grid Layout Fixes */
        .form-box form {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-box div {
            display: flex;
            flex-direction: column; 
            justify-content: flex-start;
        }
        .form-box label {
            margin-bottom: 8px;
            font-size: 0.9em;
            color: #bbb;
            font-weight: bold;
        }
        .form-box input, .form-box select {
            width: 100%;
            box-sizing: border-box;
            background: #333;
            border: 1px solid #555;
            color: white;
            padding: 10px;
            border-radius: 6px;
            font-size: 1em;
        }
        .form-box input:focus {
            border-color: #2979ff;
            outline: none;
        }
        .btn-container {
            grid-column: span 2; 
            text-align: right; 
            margin-top: 10px;
        }

        button { cursor: pointer; padding: 8px 15px; border-radius: 4px; border: none; font-weight: bold; }
        .btn-add { background: #2979ff; color: white; }
        .btn-del { background: #d32f2f; color: white; }
        .btn-edit { background: #ff9800; color: white; }
        
        .nav-btn { display: inline-block; padding: 10px 20px; background: #555; color: white; text-decoration: none; border-radius: 5px; margin-bottom: 20px; }
        .nav-btn:hover { background: #777; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="nav-btn">⬅ 回到前台首頁</a>
    <h1>🛠️ 後台管理介面</h1>

    <?php if ($msg): ?>
        <div class="msg"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <div class="form-box">
        <h2>➕ 手動新增地震資料</h2>
        <form method="POST">
            <div style="grid-column: span 2;">
                <label>📅 發生時間：</label>
                <input type="datetime-local" name="origin_time" required>
            </div>

            <div>
                <label>📊 芮氏規模：</label>
                <input type="number" step="0.1" name="magnitude" required placeholder="例如 5.5">
            </div>

            <div>
                <label>📉 深度 (km)：</label>
                <input type="number" step="0.1" name="depth" required placeholder="例如 10.2">
            </div>

            <div>
                <label>🌐 緯度 (Lat)：</label>
                <input type="number" step="0.0001" name="latitude" required placeholder="例如 23.5">
            </div>

            <div>
                <label>🌐 經度 (Lon)：</label>
                <input type="number" step="0.0001" name="longitude" required placeholder="例如 121.5">
            </div>

            <div style="grid-column: span 2;">
                <label>📍 位置描述：</label>
                <input type="text" name="location_desc" required placeholder="例如 花蓮縣政府東北方...">
            </div>

            <div class="btn-container">
                <button type="submit" name="add_eq" class="btn-add">新增資料</button>
            </div>
        </form>
    </div>

    <h2>👥 會員帳號管理</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>帳號</th>
                <th>權限 (Role)</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><?= htmlspecialchars($u['username']) ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <select name="new_role" style="width:auto; padding:5px;">
                            <option value="1" <?= $u['role'] == 1 ? 'selected' : '' ?>>一般使用者</option>
                            <option value="0" <?= $u['role'] == 0 ? 'selected' : '' ?>>管理員</option>
                        </select>
                        <button type="submit" name="edit_user_role" class="btn-edit">修改</button>
                    </form>
                </td>
                <td>
                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <form method="POST" onsubmit="return confirm('確定要刪除此帳號嗎？');" style="display:inline;">
                        <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                        <button type="submit" name="del_user" class="btn-del">刪除</button>
                    </form>
                    <?php else: ?>
                        <span style="color:#aaa;">(本人)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2>🌏 最近 50 筆地震資料管理</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>時間</th>
                <th>規模</th>
                <th>位置</th>
                <th>操作</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($earthquakes as $eq): ?>
            <tr>
                <td><?= $eq['id'] ?></td>
                <td><?= $eq['origin_time'] ?></td>
                <td style="color: <?= $eq['magnitude'] >= 4 ? '#ff5252' : '#fbc02d' ?>">M <?= $eq['magnitude'] ?></td>
                <td><?= htmlspecialchars($eq['location_desc']) ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('確定要刪除這筆地震資料嗎？');">
                        <input type="hidden" name="eq_id" value="<?= $eq['id'] ?>">
                        <button type="submit" name="del_eq" class="btn-del">刪除</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

</div>

</body>
</html>
