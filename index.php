<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$roleName = ($_SESSION['role'] == 0) ? "管理員 (Admin)" : "一般使用者";

$viewAll = isset($_GET['view']) && $_GET['view'] == 'all';

if ($viewAll) {
    $sql = "SELECT e.*, 
            (SELECT AVG(score) FROM fear_reports WHERE earthquake_id = e.id) as avg_score,
            (SELECT COUNT(id) FROM fear_reports WHERE earthquake_id = e.id) as report_count
            FROM earthquake_data e 
            ORDER BY origin_time DESC LIMIT 100";
    $listTitle = "🌏 所有地震紀錄 (最近 100 筆)";
    $nextBtnText = "只顯示最近 3 天";
    $nextBtnLink = "index.php";
} else {
    $sql = "SELECT e.*, 
            (SELECT AVG(score) FROM fear_reports WHERE earthquake_id = e.id) as avg_score,
            (SELECT COUNT(id) FROM fear_reports WHERE earthquake_id = e.id) as report_count
            FROM earthquake_data e 
            WHERE origin_time >= DATE_SUB(NOW(), INTERVAL 3 DAY) 
            ORDER BY origin_time DESC";
    $listTitle = "📅 最近 3 天地震資訊";
    $nextBtnText = "查看所有地震資料";
    $nextBtnLink = "index.php?view=all";
}

$earthquakes = $pdo->query($sql)->fetchAll();

$mapSql = "SELECT e.*, 
           (SELECT AVG(score) FROM fear_reports WHERE earthquake_id = e.id) as avg_score
           FROM earthquake_data e 
           ORDER BY origin_time DESC LIMIT 100";
$mapEarthquakes = $pdo->query($mapSql)->fetchAll();
$eqJson = json_encode($mapEarthquakes);
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>台灣小區域地震觀測網</title>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+TC:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <style>
        :root { 
            --bg-color: #121212; 
            --card-bg: #1e1e1e; 
            --box-bg: #2d2d30; 
            --text-color: #e0e0e0; 
            --primary-red: #d32f2f; 
            --accent-blue: #536dfe; 
            --border-color: #444; 
            --purple-border: #9c27b0;
            --blue-border: #2979ff;
        }
        body { 
            background-color: transparent; 
            font-family: 'Noto Sans TC', sans-serif; 
            color: var(--text-color); 
            margin: 0; 
            padding: 20px; 
        }
        .video-background-container {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; z-index: -10;
        }
        .video-background-container video {
            min-width: 100%; min-height: 100%; width: auto; height: auto; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); object-fit: cover;
        }
        .video-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(18, 18, 18, 0.75); z-index: -5;
        }
        .container { max-width: 900px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 0 10px; margin-bottom: 10px; }
        .user-info { color: #90caf9; }
        .role-tag { background: #333; padding: 2px 8px; border-radius: 4px; font-size: 0.8em; margin-left: 5px; color: #aaa; }
        .logout-btn { background: #424242; color: #fff; text-decoration: none; padding: 5px 15px; border-radius: 4px; font-size: 0.9em; transition: 0.2s; }
        .logout-btn:hover { background: #616161; }
        header { background-color: var(--card-bg); padding: 20px; border-radius: 12px; text-align: center; border: 1px solid var(--border-color); }
        .sync-btn { background: linear-gradient(135deg, var(--accent-blue), #3d5afe); color: white; padding: 10px 25px; border-radius: 50px; border: none; cursor: pointer; font-weight: bold; margin-top: 10px; transition: 0.3s; }
        .sync-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(61, 90, 254, 0.4); }
        .disabled-btn { background: #333; color: #777; padding: 10px 25px; border-radius: 50px; border: 1px solid #444; margin-top: 10px; cursor: not-allowed; }
        .status-msg { margin-top: 10px; padding: 8px; border-radius: 8px; font-size: 0.9em; display: inline-block; }
        .success { background: rgba(76, 175, 80, 0.2); color: #81c784; border: 1px solid #2e7d32; }
        .error { background: rgba(244, 67, 54, 0.2); color: #e57373; border: 1px solid #c62828; }
        #map { height: 65vh; width: 100%; border-radius: 12px; border: 1px solid var(--border-color); z-index: 1; }
        
        .charts-row { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
        .chart-box { background-color: var(--card-bg); border: 1px solid #333; border-radius: 16px; padding: 15px; height: 300px; position: relative; }
        @media (max-width: 768px) { .charts-row { grid-template-columns: 1fr; } }

        .dashboard-list { display: flex; flex-direction: column; gap: 25px; }
        .quake-dashboard-card {
            background-color: var(--card-bg); border-radius: 16px; padding: 20px; border: 1px solid #333; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            cursor: pointer; transition: transform 0.2s, border-color 0.2s;
        }
        .quake-dashboard-card:hover { transform: translateX(5px); border-color: var(--accent-blue); }

        .card-title-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-left: 5px; border-left: 4px solid var(--accent-blue); }
        .card-time { font-size: 1.2em; font-weight: bold; color: #fff; margin-left: 10px; }
        .card-date { color: #aaa; font-size: 0.9em; margin-left: 10px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px; }
        .stat-box { background-color: var(--box-bg); border-radius: 12px; padding: 20px 10px; text-align: center; display: flex; flex-direction: column; justify-content: center; align-items: center; min-height: 80px; }
        .stat-label { font-size: 0.85em; color: #aaa; margin-bottom: 5px; }
        .stat-value { font-size: 1.5em; font-weight: bold; color: #fff; }
        .stat-value.mag { color: #ffeb3b; } 
        .stat-value.mag-high { color: #ff5252; text-shadow: 0 0 10px rgba(255, 82, 82, 0.4); } 
        .info-box-purple { border: 1px solid var(--purple-border); background: rgba(156, 39, 176, 0.05); border-radius: 12px; padding: 15px; margin-bottom: 15px; display: flex; align-items: center; gap: 10px; }
        .info-icon { font-size: 1.2em; }
        .info-title { color: #ce93d8; font-weight: bold; margin-bottom: 4px; display: block; font-size: 0.9em; }
        .info-text { color: #e0e0e0; font-size: 1em; }
        .info-box-blue { background: rgba(41, 121, 255, 0.1); border-left: 4px solid var(--blue-border); padding: 12px 15px; border-radius: 4px; color: #bbdefb; font-size: 0.95em; display: flex; align-items: center; justify-content: space-between; }
        .action-area { text-align: center; margin-top: 20px; margin-bottom: 40px; }
        .view-all-btn { display: inline-block; background-color: transparent; border: 1px solid #777; color: #ccc; padding: 12px 40px; border-radius: 50px; text-decoration: none; transition: 0.3s; font-size: 1em; }
        .view-all-btn:hover { background-color: #333; color: #fff; border-color: #fff; }
        @media (max-width: 600px) { .stats-grid { gap: 8px; } .stat-box { padding: 15px 5px; } .stat-value { font-size: 1.2em; } }
        .leaflet-popup-content-wrapper, .leaflet-popup-tip { background: #252526; color: #e0e0e0; }
        @keyframes pulse-ring { 0% { transform: scale(0.33); opacity: 1; } 80%, 100% { opacity: 0; } }
        @keyframes pulse-dot { 0% { transform: scale(0.8); } 50% { transform: scale(1); } 100% { transform: scale(0.8); } }

        .leaflet-control-layers { background-color: var(--card-bg) !important; color: var(--text-color) !important; border: 1px solid #444 !important; border-radius: 8px !important; box-shadow: 0 4px 15px rgba(0,0,0,0.5) !important; }
        .leaflet-control-layers-expanded { padding: 10px 15px !important; line-height: 2em; }
        .leaflet-control-layers label { display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 2px; }
        .leaflet-control-layers label:hover { background-color: rgba(255,255,255,0.05); border-radius: 4px; }
        .leaflet-control-layers-separator { border-top: 1px solid #444 !important; }

        .filter-bar { display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-bottom: 10px; }
        .filter-select { background: #252526; color: white; border: 1px solid #444; padding: 6px 12px; border-radius: 6px; cursor: pointer; }

        .fear-section { margin-top: 15px; border-top: 1px solid #333; padding-top: 15px; }
        .fear-label { font-size: 0.9em; color: #bbb; margin-bottom: 8px; display: block; }
        .fear-controls { display: flex; align-items: center; gap: 10px; }
        .fear-slider { flex-grow: 1; accent-color: var(--primary-red); cursor: pointer; height: 6px; background: #444; border-radius: 3px; -webkit-appearance: none; }
        .fear-slider::-webkit-slider-thumb { -webkit-appearance: none; width: 18px; height: 18px; background: #fff; border-radius: 50%; cursor: pointer; }
        .fear-value { font-weight: bold; color: var(--primary-red); width: 25px; text-align: center; }
        .fear-btn { background: #444; color: white; border: none; padding: 5px 15px; border-radius: 20px; font-size: 0.8em; cursor: pointer; transition: 0.2s; }
        .fear-btn:hover { background: var(--primary-red); }
        
        .avg-fear-display {
            background: rgba(255, 82, 82, 0.1);
            color: #ff8a80;
            padding: 8px 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 0.95em;
            display: flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>
    <div class="video-background-container">
        <video autoplay muted loop playsinline>
            <source src="video/light.mp4" type="video/mp4">
        </video>
        <div class="video-overlay"></div>
    </div>

<div class="container">
    <div class="navbar">
        <span class="user-info">Hi, <?= htmlspecialchars($_SESSION['username']) ?> <span class="role-tag"><?= $roleName ?></span></span>
        <a href="logout.php" class="logout-btn">登出</a>
    </div>

    <header>
        <h1>台灣小區域地震觀測</h1>
        <?php if ($_SESSION['role'] == 0): ?>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 10px;">
                <form action="eq_sync.php" method="POST" style="margin:0;">
                    <button type="submit" class="sync-btn" name="start_sync">📡 同步最新資料</button>
                </form>
                
                <a href="admin.php" style="text-decoration:none;">
                    <button class="sync-btn" style="background: linear-gradient(135deg, #424242, #616161);">🛠️ 進入後台管理</button>
                </a>
            </div>
        <?php endif; ?>

        <?php if(isset($_GET['status'])): ?>
            <?php if($_GET['status'] == 'success'): ?>
                <div class='status-msg success'>✓ 同步成功！新增 <?= htmlspecialchars($_GET['count']) ?> 筆資料</div>
            <?php elseif($_GET['status'] == 'error'): ?>
                <?php if(isset($_GET['msg']) && $_GET['msg'] == 'denied'): ?>
                    <div class='status-msg error'>⚠ 權限不足：操作已被拒絕</div>
                <?php else: ?>
                    <div class='status-msg error'>⚠ 同步失敗，請檢查網路或 API Key</div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </header>

    <div class="filter-bar">
        <label style="color: #ccc; font-size: 0.9em;">🔍 篩選規模：</label>
        <select id="magFilter" onchange="applyFilter()" class="filter-select">
            <option value="0">顯示全部</option>
            <option value="3">規模 3.0 以上</option>
            <option value="4">規模 4.0 以上 (顯著)</option>
            <option value="5">規模 5.0 以上 (強震)</option>
        </select>
    </div>

    <div id="map"></div>

    <div class="charts-row">
        <div class="chart-box"><canvas id="trendChart"></canvas></div>
        <div class="chart-box"><canvas id="pieChart"></canvas></div>
    </div>

    <h2 style="border-left: 5px solid #d32f2f; padding-left: 10px; margin-top: 30px;">
        <?= $listTitle ?> <span style="font-size:0.6em; color:#999; font-weight:normal;">(點擊卡片定位)</span>
    </h2>

    <div class="dashboard-list">
        <?php if (count($earthquakes) > 0): ?>
            <?php foreach ($earthquakes as $eq): 
                $dateTime = new DateTime($eq['origin_time']);
                $dateStr = $dateTime->format('m/d');
                $timeStr = $dateTime->format('H:i');
                $magClass = ($eq['magnitude'] >= 4.0) ? 'mag-high' : 'mag';
                
                $avgScore = $eq['avg_score'] ? number_format($eq['avg_score'], 1) : null;
                $reportCount = $eq['report_count'] ? $eq['report_count'] : 0;
            ?>
            <div class="quake-dashboard-card" data-mag="<?= $eq['magnitude'] ?>" onclick="flyToLoc(<?= $eq['latitude'] ?>, <?= $eq['longitude'] ?>, '<?= $eq['id'] ?>')">
                <div class="card-title-row">
                    <div><span class="card-time"><?= $timeStr ?></span><span class="card-date"><?= $dateStr ?></span></div>
                    <div style="font-size: 0.8em; color: #777;">ID: <?= $eq['id'] ?></div>
                </div>
                <div class="stats-grid">
                    <div class="stat-box"><div class="stat-value <?= $magClass ?>">M <?= number_format($eq['magnitude'], 1) ?></div><div class="stat-label">芮氏規模</div></div>
                    <div class="stat-box"><div class="stat-value"><?= $eq['depth'] ?> km</div><div class="stat-label">地震深度</div></div>
                    <div class="stat-box"><div class="stat-value" style="font-size:1.8em">〰️</div><div class="stat-label">地震觀測</div></div>
                </div>
                <div class="info-box-purple">
                    <div class="info-icon">📍</div>
                    <div><span class="info-title">震央位置</span><span class="info-text"><?= htmlspecialchars($eq['location_desc']) ?></span></div>
                </div>
                <div class="info-box-blue">
                    <span>詳細數據：緯度 <?= $eq['latitude'] ?> / 經度 <?= $eq['longitude'] ?></span>
                    <span>深度 <?= $eq['depth'] ?> km</span>
                </div>
                
                <div class="fear-section" onclick="event.stopPropagation()">
                    <?php if ($avgScore): ?>
                        <div class="avg-fear-display">
                            📊 大家平均覺得：<b><?= $avgScore ?> 分</b> <span style="font-size:0.8em; color:#bbb;">(<?= $reportCount ?> 人回報)</span>
                        </div>
                    <?php else: ?>
                        <div style="color:#777; font-size:0.9em; margin-bottom:10px;">📊 尚無人回報分數</div>
                    <?php endif; ?>

                    <span class="fear-label">😱 覺得可怕嗎？(1-10分)</span>
                    <div class="fear-controls">
                        <span style="font-size:0.8em">1</span>
                        <input type="range" min="1" max="10" value="5" class="fear-slider" id="slider-<?= $eq['id'] ?>" oninput="document.getElementById('val-<?= $eq['id'] ?>').innerText = this.value">
                        <span style="font-size:0.8em">10</span>
                        <span class="fear-value" id="val-<?= $eq['id'] ?>">5</span>
                        <button class="fear-btn" onclick="submitFear(<?= $eq['id'] ?>)">送出</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align:center; padding: 50px; background: var(--card-bg); border-radius: 12px; color: #777;"><h3>📭 最近 3 天無地震紀錄</h3></div>
        <?php endif; ?>
    </div>

    <div class="action-area"><a href="<?= $nextBtnLink ?>" class="view-all-btn"><?= $nextBtnText ?></a></div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
    var map = L.map('map').setView([23.6, 121.0], 8);

    var baseLayer = L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', {
        attribution: '&copy; OSM contributors &copy; CARTO',
        subdomains: 'abcd',
        maxZoom: 19
    }).addTo(map);

    var earthquakes = <?= $eqJson ?>;
    
    var markersLayer = L.layerGroup();
    var heatLayer; 
    var plateLayer = L.layerGroup();
    var faultLayer = L.layerGroup();

    var heatData = [];
    var markerMap = {};

    function initMarkers(minMag = 0) {
        markersLayer.clearLayers();
        heatData = [];
        markerMap = {};

        earthquakes.forEach(function(eq) {
            var mag = parseFloat(eq.magnitude);
            if(mag < minMag) return;

            var color = (mag >= 4.0) ? '#ff5252' : '#fbc02d';
            var radius = mag * 2.5;

            var circle = L.circleMarker([eq.latitude, eq.longitude], {
                color: color, fillColor: color, fillOpacity: 0.7, radius: radius, bubblingMouseEvents: false
            });

            var avgText = (eq.avg_score) ? `<br>👥 平均恐懼: <b>${parseFloat(eq.avg_score).toFixed(1)}</b>` : "";

            circle.bindPopup(`
                <div style="text-align:center;">
                    <b style="font-size:1.1em; color:${color}">M${mag.toFixed(1)}</b><br>
                    <span style="font-size:0.9em; color:#ccc">${eq.origin_time}</span><br>
                    <hr style="border-color:#555; margin:5px 0;">
                    深度: ${eq.depth} km<br>
                    ${eq.location_desc}
                    ${avgText}
                </div>
            `);
            
            markersLayer.addLayer(circle);
            markerMap[eq.id] = circle;
            heatData.push([eq.latitude, eq.longitude, mag * 20]);
        });
    }
    
    initMarkers(0);
    markersLayer.addTo(map);

    heatLayer = L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 10, gradient: {0.4: 'blue', 0.65: 'lime', 1: 'red'} });

    var plateLine = [
        [24.28, 121.74], [24.08, 121.62], [23.90, 121.55], [23.65, 121.40], [23.40, 121.35], 
        [23.15, 121.25], [22.85, 121.15], [22.70, 121.10]
    ];
    var platePolyline = L.polyline(plateLine, {
        color: '#29b6f6', weight: 4, dashArray: '10, 10', opacity: 0.8
    }).bindPopup("<b>板塊交界</b><br>菲律賓海板塊 vs 歐亞大陸板塊");
    plateLayer.addLayer(platePolyline);

    var taiwanFaultsData = {
        "type": "FeatureCollection",
        "features": [
            { "type": "Feature", "properties": { "name": "山腳斷層" }, "geometry": { "type": "LineString", "coordinates": [[121.49, 25.13], [121.45, 25.08], [121.42, 25.05], [121.38, 25.02]] } },
            { "type": "Feature", "properties": { "name": "新竹斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.95, 24.85], [120.98, 24.80], [121.02, 24.78]] } },
            { "type": "Feature", "properties": { "name": "車籠埔斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.73, 24.28], [120.72, 24.18], [120.70, 24.05], [120.71, 23.90], [120.74, 23.75]] } },
            { "type": "Feature", "properties": { "name": "彰化斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.58, 24.15], [120.60, 24.00], [120.62, 23.85]] } },
            { "type": "Feature", "properties": { "name": "大茅埔-雙冬斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.85, 24.20], [120.82, 24.05], [120.80, 23.95]] } },
            { "type": "Feature", "properties": { "name": "梅山斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.45, 23.58], [120.55, 23.57], [120.65, 23.56]] } },
            { "type": "Feature", "properties": { "name": "大尖山斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.65, 23.65], [120.68, 23.55], [120.70, 23.45]] } },
            { "type": "Feature", "properties": { "name": "六甲斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.35, 23.25], [120.38, 23.20], [120.40, 23.15]] } },
            { "type": "Feature", "properties": { "name": "旗山斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.50, 23.10], [120.45, 22.95], [120.40, 22.80]] } },
            { "type": "Feature", "properties": { "name": "潮州斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.65, 22.85], [120.60, 22.65], [120.58, 22.45]] } },
            { "type": "Feature", "properties": { "name": "恆春斷層" }, "geometry": { "type": "LineString", "coordinates": [[120.75, 22.15], [120.74, 22.05], [120.73, 21.95]] } },
            { "type": "Feature", "properties": { "name": "米崙斷層" }, "geometry": { "type": "LineString", "coordinates": [[121.64, 24.02], [121.62, 23.99]] } },
            { "type": "Feature", "properties": { "name": "花東縱谷斷層系" }, "geometry": { "type": "LineString", "coordinates": [[121.50, 23.80], [121.40, 23.50], [121.30, 23.20], [121.20, 22.90]] } },
            { "type": "Feature", "properties": { "name": "池上斷層" }, "geometry": { "type": "LineString", "coordinates": [[121.22, 23.15], [121.20, 23.10], [121.18, 23.05]] } }
        ]
    };

    L.geoJSON(taiwanFaultsData, {
        style: function(feature) { return { color: "#ff9800", weight: 3, opacity: 0.9 }; },
        onEachFeature: function(feature, layer) { layer.bindPopup("<b>⚠️ 活動斷層</b><br>" + feature.properties.name); }
    }).addTo(faultLayer);

    var baseMaps = {};
    var overlayMaps = {
        "<span style='color:#ff5252'>●</span> 地震圓點 (Markers)": markersLayer,
        "<span style='color:lime'>♨</span> 熱力圖 (Heatmap)": heatLayer,
        "<span style='color:#29b6f6'>➖</span> 板塊邊界 (Plates)": plateLayer,
        "<span style='color:#ff9800'>➖</span> 活動斷層 (Faults)": faultLayer
    };
    L.control.layers(null, overlayMaps, {collapsed: false}).addTo(map);

    function applyFilter() {
        var minMag = parseFloat(document.getElementById('magFilter').value);
        initMarkers(minMag);
        if (map.hasLayer(heatLayer)) {
            map.removeLayer(heatLayer);
            heatLayer = L.heatLayer(heatData, { radius: 25, blur: 15, maxZoom: 10, gradient: {0.4: 'blue', 0.65: 'lime', 1: 'red'} });
            map.addLayer(heatLayer);
        }
        var cards = document.querySelectorAll('.quake-dashboard-card');
        cards.forEach(card => {
            var cardMag = parseFloat(card.getAttribute('data-mag'));
            card.style.display = (cardMag >= minMag) ? 'block' : 'none';
        });
    }

    function flyToLoc(lat, lng, id) {
        if (!map.hasLayer(markersLayer)) {
            map.removeLayer(heatLayer);
            map.addLayer(markersLayer);
        }
        map.flyTo([lat, lng], 13, { duration: 1.5 });
        var targetMarker = markerMap[id];
        if (targetMarker) {
            setTimeout(function() { targetMarker.openPopup(); }, 1500);
        }
        document.getElementById('map').scrollIntoView({behavior: 'smooth'});
    }

    function submitFear(eqId) {
        var score = document.getElementById('slider-' + eqId).value;
        var btn = event.target; 
        
        var formData = new FormData();
        formData.append('eq_id', eqId);
        formData.append('score', score);

        fetch('submit_fear.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if(data.status === 'success') {
                btn.innerText = '已送出';
                btn.style.background = '#4caf50';
                btn.disabled = true;
            } else {
                alert(data.msg);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    }

    const dateCounts = {};
    const magCounts = { small: 0, medium: 0, large: 0 }; 

    earthquakes.forEach(eq => {
        const dateStr = eq.origin_time.substring(5, 10);
        dateCounts[dateStr] = (dateCounts[dateStr] || 0) + 1;
        const m = parseFloat(eq.magnitude);
        if (m < 3) magCounts.small++; else if (m < 4) magCounts.medium++; else magCounts.large++;
    });
    const sortedDates = Object.keys(dateCounts).sort();
    
    new Chart(document.getElementById('trendChart'), {
        type: 'bar',
        data: {
            labels: sortedDates,
            datasets: [{ label: '每日地震次數', data: sortedDates.map(d => dateCounts[d]), backgroundColor: '#536dfe', borderRadius: 4 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {display:false}, title: {display:true, text:'近 7 天趨勢', color:'#ccc'} }, scales: { y: {grid:{color:'#333'}}, x: {grid:{display:false}} } }
    });

    new Chart(document.getElementById('pieChart'), {
        type: 'doughnut',
        data: {
            labels: ['<3', '3-4', '>4'],
            datasets: [{ data: [magCounts.small, magCounts.medium, magCounts.large], backgroundColor: ['#4caf50', '#fbc02d', '#d32f2f'], borderWidth: 0 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: {position:'bottom', labels:{color:'#ccc'}}, title: {display:true, text:'規模分佈', color:'#ccc'} }, cutout: '70%' }
    });
</script>
</body>
</html>
