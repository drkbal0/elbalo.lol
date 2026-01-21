<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'config.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 1. ACTUALIZACIÓN AUTOMÁTICA DE BASE DE DATOS ---

// Tablas básicas
$conn->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50), password VARCHAR(255))");
$conn->query("CREATE TABLE IF NOT EXISTS stats_visits (date DATE PRIMARY KEY, views INT DEFAULT 0)");
$conn->query("CREATE TABLE IF NOT EXISTS analytics_visitors (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45), os VARCHAR(50), browser TEXT, referrer TEXT, created_at DATETIME)");
$conn->query("CREATE TABLE IF NOT EXISTS analytics_clicks (id INT AUTO_INCREMENT PRIMARY KEY, visitor_id INT, link_id INT, clicked_at DATETIME)");
$conn->query("CREATE TABLE IF NOT EXISTS banned_ips (ip VARCHAR(45) PRIMARY KEY, banned_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

// 🆕 Columnas para MODO EN VIVO
$check_live = $conn->query("SHOW COLUMNS FROM settings LIKE 'live_mode'");
if ($check_live->num_rows == 0) {
    $conn->query("ALTER TABLE settings ADD COLUMN live_mode TINYINT DEFAULT 0"); // 0 = Off, 1 = On
}

// 🆕 Columnas para PROGRAMADOR DE ENLACES
$check_dates = $conn->query("SHOW COLUMNS FROM links LIKE 'start_date'");
if ($check_dates->num_rows == 0) {
    $conn->query("ALTER TABLE links ADD COLUMN start_date DATETIME NULL");
    $conn->query("ALTER TABLE links ADD COLUMN end_date DATETIME NULL");
}

// Columnas previas (SEO, Clics)
$check_seo = $conn->query("SHOW COLUMNS FROM settings LIKE 'meta_title'");
if ($check_seo->num_rows == 0) {
    $conn->query("ALTER TABLE settings ADD COLUMN meta_title VARCHAR(255) DEFAULT 'Mi Linktree'");
    $conn->query("ALTER TABLE settings ADD COLUMN meta_desc VARCHAR(255) DEFAULT 'Mis enlaces oficiales'");
}
$check_col = $conn->query("SHOW COLUMNS FROM links LIKE 'clicks'");
if ($check_col->num_rows == 0) $conn->query("ALTER TABLE links ADD COLUMN clicks INT DEFAULT 0");

// Crear admin si no existe
$check_user = $conn->query("SELECT * FROM users WHERE username = 'admin' LIMIT 1");
if ($check_user->num_rows == 0) {
    $default_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, password) VALUES ('admin', '$default_pass')");
}

// VERIFICAR LOGIN
if (!isset($_SESSION['admin'])) { header("Location: login.php"); exit; }


// --- 2. LÓGICA PHP (POST/GET) ---

// Banear IP
if (isset($_GET['ban_ip'])) {
    $ip_to_ban = mysqli_real_escape_string($conn, $_GET['ban_ip']);
    $conn->query("INSERT IGNORE INTO banned_ips (ip) VALUES ('$ip_to_ban')");
    header("Location: admin.php?msg=banned"); exit;
}

// Actualizar Perfil (Incluye MODO EN VIVO)
if (isset($_POST['update_settings'])) {
    $nombre = mysqli_real_escape_string($conn, $_POST['nombre_perfil']);
    $handle = mysqli_real_escape_string($conn, $_POST['youtube_handle']);
    $bio = mysqli_real_escape_string($conn, $_POST['bio']);
    $pic = mysqli_real_escape_string($conn, $_POST['profile_pic_url']);
    $meta_t = mysqli_real_escape_string($conn, $_POST['meta_title']);
    $meta_d = mysqli_real_escape_string($conn, $_POST['meta_desc']);
    
    // Checkbox Live Mode
    $live = isset($_POST['live_mode']) ? 1 : 0;

    $conn->query("UPDATE settings SET 
        nombre_perfil='$nombre', 
        youtube_handle='$handle', 
        bio='$bio', 
        profile_pic_url='$pic', 
        meta_title='$meta_t', 
        meta_desc='$meta_d',
        live_mode='$live'
        LIMIT 1");
    header("Location: admin.php?msg=saved"); exit;
}

// Actualizar Password
if (isset($_POST['update_pass'])) {
    $hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
    $conn->query("UPDATE users SET password = '$hash' WHERE username = 'admin'");
    header("Location: admin.php?msg=pass_ok"); exit;
}

// Reordenar
if (isset($_POST['new_order'])) {
    foreach ($_POST['new_order'] as $index => $id) {
        $id = intval($id); $index = intval($index);
        $conn->query("UPDATE links SET orden = $index WHERE id = $id");
    }
    exit('ok');
}

// Función Descargar Imagen
function downloadImage($url, $id, $conn) {
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    $img_data = curl_exec($ch); 
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    curl_close($ch);
    if ($img_data && $http_code == 200) {
        $dir = "avatars/";
        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $filename = $dir . "link_" . $id . ".jpg";
        file_put_contents($filename, $img_data);
        $conn->query("UPDATE links SET local_thumb = '$filename' WHERE id = $id");
        return true;
    }
    return false;
}

// Trigger Descargar
if (isset($_GET['download_thumb'])) {
    $id = intval($_GET['download_thumb']);
    $row = $conn->query("SELECT url FROM links WHERE id = $id")->fetch_assoc();
    if ($row) {
        $url = $row['url'];
        $parts = explode('/', trim($url, '/')); $username = end($parts);
        $platform = (strpos($url, 'instagram')) ? "instagram" : ((strpos($url, 'kick')) ? "kick" : ((strpos($url, 'tiktok')) ? "tiktok" : ((strpos($url, 'github')) ? "github" : "youtube")));
        if(downloadImage("https://unavatar.io/$platform/$username", $id, $conn)){
            header("Location: admin.php?msg=img_ok"); exit;
        }
    }
    header("Location: admin.php?msg=error"); exit;
}

// CRUD Links (CON PROGRAMADOR)
if (isset($_POST['edit_link'])) {
    $id = intval($_POST['link_id']);
    $t = mysqli_real_escape_string($conn, $_POST['title']); 
    $u = mysqli_real_escape_string($conn, $_POST['url']);
    
    // Manejo de Fechas (Si están vacías, NULL)
    $start = !empty($_POST['start_date']) ? "'" . $_POST['start_date'] . "'" : "NULL";
    $end = !empty($_POST['end_date']) ? "'" . $_POST['end_date'] . "'" : "NULL";

    $conn->query("UPDATE links SET title='$t', url='$u', start_date=$start, end_date=$end WHERE id=$id");
    
    if(!empty($_POST['img_url'])) { downloadImage($_POST['img_url'], $id, $conn); }
    header("Location: admin.php?msg=saved"); exit;
}

if (isset($_POST['add_link'])) {
    $t = mysqli_real_escape_string($conn, $_POST['title']); 
    $u = mysqli_real_escape_string($conn, $_POST['url']);
    
    // Manejo de Fechas
    $start = !empty($_POST['start_date']) ? "'" . $_POST['start_date'] . "'" : "NULL";
    $end = !empty($_POST['end_date']) ? "'" . $_POST['end_date'] . "'" : "NULL";

    $conn->query("INSERT INTO links (title, url, orden, clicks, start_date, end_date) VALUES ('$t', '$u', 99, 0, $start, $end)");
    header("Location: admin.php?msg=added"); exit;
}

if (isset($_GET['del'])) {
    $conn->query("DELETE FROM links WHERE id = ".intval($_GET['del']));
    header("Location: admin.php?msg=deleted"); exit;
}


// --- 3. CONSULTAS DE DATOS ---

// SPY (Logs)
$sql_logs = "
    SELECT v.id, v.ip, v.os, v.referrer, v.created_at,
    (SELECT COUNT(*) FROM analytics_visitors v2 WHERE v2.ip = v.ip) as total_visits,
    GROUP_CONCAT(l.title ORDER BY c.clicked_at SEPARATOR ' ➡️ ') as journey
    FROM analytics_visitors v
    LEFT JOIN analytics_clicks c ON v.id = c.visitor_id
    LEFT JOIN links l ON c.link_id = l.id
    WHERE v.ip NOT IN (SELECT ip FROM banned_ips)
    GROUP BY v.id ORDER BY v.created_at DESC LIMIT 50
";
$logs = $conn->query($sql_logs);

// Gráficos
$dates = []; $views = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $res = $conn->query("SELECT views FROM stats_visits WHERE date = '$d'");
    $row = $res->fetch_assoc();
    $dates[] = date('d/m', strtotime($d));
    $views[] = ($row) ? $row['views'] : 0;
}
$link_labels = []; $link_clicks = [];
$res_clicks = $conn->query("SELECT title, clicks FROM links WHERE clicks > 0 ORDER BY clicks DESC LIMIT 10");
while($r = $res_clicks->fetch_assoc()){ $link_labels[] = $r['title']; $link_clicks[] = $r['clicks']; }

$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$links = $conn->query("SELECT * FROM links ORDER BY orden ASC, id DESC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel ElBalo Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/water.css@2/out/water.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { max-width: 1100px; background: #0b0f1a; color: #e2e8f0; font-family: system-ui, sans-serif; }
        .header-bar { display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid #334155; padding-bottom: 20px; }
        .header-bar h1 { margin: 0; color: #38bdf8; }
        .btn-view { color: #38bdf8; text-decoration: none; border: 1px solid #38bdf8; padding: 8px 15px; border-radius: 6px; font-weight: bold; transition:0.3s; }
        .btn-view:hover { background: #38bdf8; color: #000; }

        .tabs { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .tab-btn { background: #1e293b; border: none; padding: 12px 20px; color: #94a3b8; cursor: pointer; border-radius: 8px; font-weight: bold; flex: 1; min-width: 120px; transition: 0.2s; }
        .tab-btn.active { background: #38bdf8; color: #0b0f1a; }
        .tab-content { display: none; animation: fadeIn 0.3s; }
        .tab-content.active { display: block; }

        .card { background: #1e293b; padding: 25px; border-radius: 12px; border: 1px solid #334155; margin-bottom: 20px; }
        input, textarea, select { background: #0f172a; border: 1px solid #334155; color: white; width: 100%; box-sizing: border-box; margin-bottom: 15px; }
        label { color: #94a3b8; font-size: 0.9rem; margin-bottom: 5px; display: block; }
        
        .link-item { display: flex; align-items: center; gap: 15px; background: #2d3a4f; padding: 12px; border-radius: 10px; margin-bottom: 10px; cursor: grab; border: 1px solid transparent; transition: 0.2s; position: relative;}
        .link-item:hover { border-color: #38bdf8; }
        .thumb-preview { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border: 2px solid #38bdf8; }
        .action-btn { background: #334155; border: none; color: white; width: 35px; height: 35px; border-radius: 6px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-size: 0.9rem; margin-left: 5px; transition: 0.2s; }
        .action-btn:hover { background: #38bdf8; color: black; }
        .action-btn.del:hover { background: #ef4444; color: white; }
        .action-btn.down:hover { background: #10b981; color: white; }
        
        .timer-badge { position: absolute; top: -8px; right: -8px; background: #fbbf24; color: black; font-size: 0.7rem; padding: 2px 6px; border-radius: 10px; font-weight: bold; box-shadow: 0 2px 5px rgba(0,0,0,0.5); }

        .edit-form { display: none; margin-top: 10px; background: #162031; padding: 20px; border-radius: 10px; border: 1px dashed #38bdf8; }
        .toast { position: fixed; top: 20px; right: 20px; background: #10b981; color: white; padding: 15px 25px; border-radius: 8px; display: none; z-index: 1000; box-shadow: 0 5px 15px rgba(0,0,0,0.5); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }

        /* Estilos Tabla Spy */
        .logs-table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        .logs-table th { text-align: left; color: #94a3b8; border-bottom: 1px solid #334155; padding: 12px; }
        .logs-table td { padding: 12px; border-bottom: 1px solid #334155; vertical-align: middle; }
        .tag { display: inline-block; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: bold; }
        .tag.iphone { background: #3b82f6; color: white; } .tag.android { background: #10b981; color: white; } .tag.pc { background: #8b5cf6; color: white; }
        .user-type { font-size: 0.75rem; padding: 2px 6px; border-radius: 4px; margin-left: 5px; }
        .user-type.new { background: #064e3b; color: #34d399; border: 1px solid #059669; }
        .user-type.returning { background: #1e3a8a; color: #60a5fa; border: 1px solid #2563eb; }
        .ban-btn { color: #ef4444; background: rgba(239,68,68,0.1); border: 1px solid #ef4444; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 0.8rem; }
        .ban-btn:hover { background: #ef4444; color: white; }

        /* Switch Toggle */
        .switch-container { display: flex; align-items: center; justify-content: space-between; background: #2d3a4f; padding: 15px; border-radius: 8px; border: 1px solid #475569; margin-bottom: 20px; }
        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #334155; transition: .4s; border-radius: 34px; }
        .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
        input:checked + .slider { background-color: #ef4444; box-shadow: 0 0 10px #ef4444; }
        input:checked + .slider:before { transform: translateX(24px); }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    </style>
</head>
<body>

<?php if(isset($_GET['msg'])): ?>
    <div id="toast" class="toast">
        <?php 
            if($_GET['msg']=='saved') echo "¡Guardado con éxito! ✅";
            elseif($_GET['msg']=='img_ok') echo "¡Imagen descargada! 🖼️";
            elseif($_GET['msg']=='banned') echo "¡IP Baneada! 🚫";
            else echo "Acción realizada.";
        ?>
    </div>
    <script>document.getElementById('toast').style.display = 'block'; setTimeout(()=>{document.getElementById('toast').style.display='none'},3000);</script>
<?php endif; ?>

<div class="header-bar">
    <h1>⚙️ Admin ElBalo</h1>
    <a href="index.php" target="_blank" class="btn-view">Ver mi Sitio <i class="fa fa-external-link"></i></a>
</div>

<div class="tabs">
    <button class="tab-btn active" onclick="openTab('tab-analytics')">📊 General</button>
    <button class="tab-btn" onclick="openTab('tab-spy')">🕵️ Tráfico en Vivo</button>
    <button class="tab-btn" onclick="openTab('tab-links')">🔗 Enlaces</button>
    <button class="tab-btn" onclick="openTab('tab-profile')">👤 Perfil & SEO</button>
    <button class="tab-btn" onclick="openTab('tab-security')">🔒 Seguridad</button>
</div>

<!-- 1. ANALÍTICAS -->
<div id="tab-analytics" class="tab-content active">
    <div class="card">
        <h3>📈 Tráfico del Sitio (7 días)</h3>
        <div style="height:300px;"><canvas id="viewsChart"></canvas></div>
    </div>
    <div class="stats-grid">
        <div class="card">
            <h3>🏆 Clics Totales</h3>
            <canvas id="clicksChart"></canvas>
        </div>
        <div class="card" style="text-align:center;">
            <h3>📱 QR Oficial</h3>
            <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=https://elbalo.lol&color=0b0f1a&bgcolor=38bdf8" style="border-radius:10px; margin-top:10px;">
        </div>
    </div>
</div>

<!-- 2. SPY (REPARADO HTTPS) -->
<div id="tab-spy" class="tab-content">
    <div class="card">
        <h3>🕵️ Últimos 50 Visitantes</h3>
        <p style="font-size:0.85rem; color:#94a3b8;">Monitorea quién entra. <b>Nota:</b> La bandera carga vía HTTPS.</p>
        <div style="overflow-x:auto;">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>País (HTTPS)</th>
                        <th>Visitante</th>
                        <th>Origen</th>
                        <th>Ruta</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($log = $logs->fetch_assoc()): ?>
                        <?php 
                            $os_class = ($log['os']=='iPhone') ? 'iphone' : (($log['os']=='Android') ? 'android' : 'pc');
                            $ref = $log['referrer'];
                            $ref_display = "Directo";
                            if(strpos($ref, 'http') !== false) {
                                $host = parse_url($ref, PHP_URL_HOST);
                                $ref_display = str_ireplace('www.', '', $host);
                                if($ref_display == $_SERVER['HTTP_HOST']) $ref_display = "Recarga";
                            }
                            $loyalty_badge = ($log['total_visits'] > 1) ? '<span class="user-type returning">Recurrente</span>' : '<span class="user-type new">Nuevo</span>';
                            $journey_html = $log['journey'] ? str_replace(',', ' ➡️ ', $log['journey']) : "<span style='opacity:0.3'>...</span>";
                        ?>
                        <tr>
                            <td style="white-space:nowrap;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></td>
                            <td class="geo-flag" data-ip="<?= $log['ip'] ?>">Searching...</td>
                            <td><span class="tag <?= $os_class ?>"><?= $log['os'] ?></span> <?= $loyalty_badge ?> <br><small style="color:#64748b; font-family:monospace;"><?= $log['ip'] ?></small></td>
                            <td style="color:#38bdf8;"><?= ucfirst($ref_display) ?></td>
                            <td><?= $journey_html ?></td>
                            <td><a href="?ban_ip=<?= $log['ip'] ?>" class="ban-btn" onclick="return confirm('¿Banear?')"><i class="fa fa-ban"></i> Ban</a></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 3. ENLACES (CON PROGRAMADOR) -->
<div id="tab-links" class="tab-content">
    <div class="card" style="margin-bottom:20px;">
        <h3>+ Nuevo Enlace</h3>
        <form method="POST" style="display:flex; gap:10px; flex-wrap:wrap;">
            <input type="text" name="title" placeholder="Título" required style="flex:1;">
            <input type="url" name="url" placeholder="URL" required style="flex:2;">
            
            <div style="flex-basis: 100%; display:flex; gap:10px;">
                <div style="flex:1">
                    <label>Mostrar desde (Opcional)</label>
                    <input type="datetime-local" name="start_date">
                </div>
                <div style="flex:1">
                    <label>Ocultar hasta (Opcional)</label>
                    <input type="datetime-local" name="end_date">
                </div>
            </div>

            <button type="submit" name="add_link" style="background:#38bdf8; color:black; font-weight:bold; width:100%;">Añadir</button>
        </form>
    </div>
    
    <div class="card">
        <h3>Tus Enlaces</h3>
        <div id="links-list">
            <?php while($row = $links->fetch_assoc()): 
                $has_timer = (!empty($row['start_date']) || !empty($row['end_date']));
            ?>
                <div class="link-item" data-id="<?= $row['id'] ?>">
                    <?php if($has_timer): ?><div class="timer-badge">⏱️ Programado</div><?php endif; ?>
                    <span style="color:#64748b; font-size:1.2rem; cursor:grab;">☰</span>
                    <img src="<?= (!empty($row['local_thumb'])) ? $row['local_thumb'] : 'https://img.icons8.com/fluency/48/000000/image-link.png' ?>" class="thumb-preview">
                    
                    <div style="flex-grow:1;">
                        <strong style="color:white;"><?= htmlspecialchars($row['title']) ?></strong><br>
                        <small style="color:#94a3b8;"><?= $row['url'] ?></small>
                        <span style="font-size:0.8rem; background:#1e293b; padding:2px 6px; border-radius:4px; color:#38bdf8;">🖱️ <?= $row['clicks'] ?></span>
                    </div>

                    <div style="display:flex;">
                        <button onclick="document.getElementById('edit-<?=$row['id']?>').style.display = (document.getElementById('edit-<?=$row['id']?>').style.display == 'block') ? 'none' : 'block'" class="action-btn"><i class="fa fa-pencil"></i></button>
                        <a href="?download_thumb=<?= $row['id'] ?>" class="action-btn down"><i class="fa fa-download"></i></a>
                        <a href="?del=<?= $row['id'] ?>" onclick="return confirm('¿Borrar?')" class="action-btn del"><i class="fa fa-trash"></i></a>
                    </div>
                </div>

                <div id="edit-<?= $row['id'] ?>" class="edit-form">
                    <form method="POST">
                        <input type="hidden" name="link_id" value="<?= $row['id'] ?>">
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                            <div><label>Título</label><input type="text" name="title" value="<?= htmlspecialchars($row['title']) ?>"></div>
                            <div><label>URL</label><input type="url" name="url" value="<?= $row['url'] ?>"></div>
                        </div>
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-top:10px;">
                            <div>
                                <label>Mostrar desde:</label>
                                <input type="datetime-local" name="start_date" value="<?= $row['start_date'] ? date('Y-m-d\TH:i', strtotime($row['start_date'])) : '' ?>">
                            </div>
                            <div>
                                <label>Ocultar hasta:</label>
                                <input type="datetime-local" name="end_date" value="<?= $row['end_date'] ? date('Y-m-d\TH:i', strtotime($row['end_date'])) : '' ?>">
                            </div>
                        </div>
                        <label>Imagen Manual</label><input type="url" name="img_url" placeholder="URL opcional...">
                        <button type="submit" name="edit_link" style="background:#38bdf8; color:black;">Guardar Cambios</button>
                    </form>
                </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<!-- 4. PERFIL (CON MODO EN VIVO) -->
<div id="tab-profile" class="tab-content">
    <div class="card">
        <h3>🎨 Perfil & Configuración</h3>
        <form method="POST">
            
            <!-- SWITCH LIVE -->
            <div class="switch-container">
                <div>
                    <strong style="color:white; font-size:1.1rem;">🔴 Modo En Vivo</strong>
                    <p style="margin:0; font-size:0.85rem; color:#94a3b8;">Activa esto cuando estés stremeando para destacar tu perfil.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="live_mode" value="1" <?= ($settings['live_mode'] == 1) ? 'checked' : '' ?>>
                    <span class="slider"></span>
                </label>
            </div>

            <label>Nombre del Perfil</label>
            <input type="text" name="nombre_perfil" value="<?= htmlspecialchars($settings['nombre_perfil']) ?>">
            
            <label>Handle</label>
            <input type="text" name="youtube_handle" value="<?= htmlspecialchars($settings['youtube_handle'] ?? '') ?>">
            
            <label>Bio</label>
            <textarea name="bio" rows="3"><?= htmlspecialchars($settings['bio']) ?></textarea>
            
            <label>URL Foto Perfil</label>
            <input type="url" name="profile_pic_url" value="<?= htmlspecialchars($settings['profile_pic_url']) ?>">
            
            <h4 style="color:#38bdf8; margin-top:20px;">🔍 SEO</h4>
            <label>Meta Título</label><input type="text" name="meta_title" value="<?= htmlspecialchars($settings['meta_title'] ?? '') ?>">
            <label>Meta Descripción</label><input type="text" name="meta_desc" value="<?= htmlspecialchars($settings['meta_desc'] ?? '') ?>">
            
            <button type="submit" name="update_settings" style="width:100%; background:#38bdf8; color:black; font-weight:bold;">Guardar Todo</button>
        </form>
    </div>
</div>

<div id="tab-security" class="tab-content">
    <div class="card">
        <h3 style="color:#ef4444;">🔒 Cambiar Clave</h3>
        <form method="POST">
            <input type="password" name="new_password" placeholder="Nueva contraseña..." required>
            <button type="submit" name="update_pass" style="background:#ef4444; color:white;">Actualizar</button>
        </form>
    </div>
</div>

<script>
    function openTab(id) {
        document.querySelectorAll('.tab-content').forEach(t=>t.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
        document.getElementById(id).classList.add('active');
        const btns = document.getElementsByClassName('tab-btn');
        for(let b of btns) { if(b.getAttribute('onclick').includes(id)) b.classList.add('active'); }
    }

    Sortable.create(document.getElementById('links-list'), {
        animation: 150, handle: '.link-item',
        onEnd: function() {
            const fd = new FormData();
            Array.from(document.querySelectorAll('.link-item')).forEach(i => fd.append('new_order[]', i.dataset.id));
            fetch('admin.php', { method: 'POST', body: fd });
        }
    });

    const ctxV = document.getElementById('viewsChart');
    if(ctxV) new Chart(ctxV, { type: 'line', data: { labels: <?= json_encode($dates) ?>, datasets: [{ label: 'Visitas', data: <?= json_encode($views) ?>, borderColor: '#38bdf8', fill: true, backgroundColor: 'rgba(56,189,248,0.1)' }] }, options: { responsive: true, scales: { y: { beginAtZero: true } } } });
    
    const ctxC = document.getElementById('clicksChart');
    if(ctxC) new Chart(ctxC, { type: 'bar', data: { labels: <?= json_encode($link_labels) ?>, datasets: [{ label: 'Clics', data: <?= json_encode($link_clicks) ?>, backgroundColor: '#10b981' }] } });

    // 🌍 FIX HTTPS FLAGS: Usamos ipwho.is que soporta HTTPS gratis
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.geo-flag').forEach(cell => {
            const ip = cell.dataset.ip;
            if(ip && ip !== '::1' && ip !== '127.0.0.1') {
                fetch(`https://ipwho.is/${ip}`)
                    .then(r => r.json())
                    .then(d => {
                        if(d.success) {
                            cell.innerHTML = `<img src="${d.flag.img}" width="20" style="vertical-align:middle;"> ${d.city}`;
                        } else { cell.innerHTML = 'N/A'; }
                    })
                    .catch(() => cell.innerHTML = 'Error');
            } else { cell.innerHTML = '🏠 Host'; }
        });
    });
</script>
</body>
</html>