<?php
require 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'msg' => '請先登入']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $eq_id = $_POST['eq_id'];
    $score = intval($_POST['score']);

    if ($score < 1 || $score > 10) {
        echo json_encode(['status' => 'error', 'msg' => '數值不正確']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO fear_reports (user_id, earthquake_id, score) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE score = VALUES(score)");
        $stmt->execute([$user_id, $eq_id, $score]);
        
        echo json_encode(['status' => 'success', 'msg' => '感謝回報！']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => '資料庫錯誤']);
    }
}
?>