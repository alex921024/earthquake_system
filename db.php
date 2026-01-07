<?php
// db.php - 資料庫連線與 Session 啟動
$host = 'localhost';
$db   = 'earthquake_system';
$user = 'root';
$pass = '';

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}
?>