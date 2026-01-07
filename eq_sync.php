<?php
// eq_sync.php - 負責抓取資料並寫入
require 'db.php'; 

// 1. 檢查是否登入
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
// 2. 檢查權限 (必須是 role 0)
if ($_SESSION['role'] != 0) {
    header("Location: index.php?status=error&msg=denied");
    exit;
}

// API 設定
$apiKey = ""; 
$apiUrl = "https://opendata.cwa.gov.tw/api/v1/rest/datastore/E-A0016-001?Authorization={$apiKey}&format=JSON";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$json = curl_exec($ch);
curl_close($ch);

$data = json_decode($json, true);
$insertedCount = 0;

if ($data && isset($data['success']) && $data['success'] === 'true') {
    $records = $data['records']['Earthquake'];
    
    // 使用 IGNORE 忽略已存在的資料
    $sql = "INSERT IGNORE INTO earthquake_data 
            (origin_time, longitude, latitude, depth, magnitude, location_desc) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);

    foreach ($records as $eq) {
        $info = $eq['EarthquakeInfo'];
        $time = $info['OriginTime'];
        
        $epi = $info['Epicenter'];
        $rawLon = $epi['EpicenterLongitude'] ?? $epi['Longitude'] ?? 0;
        $rawLat = $epi['EpicenterLatitude']  ?? $epi['Latitude']  ?? 0;
        $lon = is_array($rawLon) ? ($rawLon['Value'] ?? 0) : $rawLon;
        $lat = is_array($rawLat) ? ($rawLat['Value'] ?? 0) : $rawLat;

        $rawDep = $info['FocalDepth'];
        $dep = is_array($rawDep) ? ($rawDep['Value'] ?? 0) : $rawDep;

        $magInfo = $info['EarthquakeMagnitude']['MagnitudeValue'];
        $mag = is_array($magInfo) ? ($magInfo['Value'] ?? 0) : $magInfo;

        $loc = $eq['ReportContent'];

        $stmt->execute([$time, $lon, $lat, $dep, $mag, $loc]);
        
        if ($stmt->rowCount() > 0) {
            $insertedCount++;
        }
    }
    header("Location: index.php?status=success&count=$insertedCount");
    exit;
} else {
    header("Location: index.php?status=error");
    exit;
}

?>
