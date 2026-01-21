<?php 
// Ocultar notificaciones de error visuales
error_reporting(0); 

include 'config.php';

// 1. CORRECCIÓN DE SESIÓN
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. SISTEMA DE RASTREO
function getOS($user_agent) {
    if (strpos($user_agent, 'iPhone') !== false) return 'iPhone';
    if (strpos($user_agent, 'Android') !== false) return 'Android';
    if (strpos($user_agent, 'Windows') !== false) return 'Windows PC';
    if (strpos($user_agent, 'Mac') !== false) return 'Mac';
    return 'Otro';
}

if (!isset($_SESSION['visitor_id'])) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $ua = $_SERVER['HTTP_USER_AGENT'];
    $os = getOS($ua);
    $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Directo';
    $date = date('Y-m-d H:i:s');
    $today_date = date('Y-m-d');

    $stmt = $conn->prepare("INSERT INTO analytics_visitors (ip, os, browser, referrer, created_at) VALUES (?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("sssss", $ip, $os, $ua, $referrer, $date);
        $stmt->execute();
        $_SESSION['visitor_id'] = $conn->insert_id;
    }
    
    $conn->query("INSERT INTO stats_visits (date, views) VALUES ('$today_date', 1) ON DUPLICATE KEY UPDATE views = views + 1");
}

$settings = $conn->query("SELECT * FROM settings LIMIT 1")->fetch_assoc();
$links = $conn->query("SELECT * FROM links ORDER BY orden ASC, id DESC");

// VARIABLES PARA MODO EN VIVO
$is_live = (isset($settings['live_mode']) && $settings['live_mode'] == 1);

// SEO
$seo_title = !empty($settings['meta_title']) ? $settings['meta_title'] : $settings['nombre_perfil'] . " | @elbalo";
$seo_desc  = !empty($settings['meta_desc']) ? $settings['meta_desc'] : $settings['bio'];

function getPlatformData($url) {
    $data = ['class' => 'default', 'icon' => 'fa-solid fa-link', 'custom_img' => null];
    if (strpos($url, 'kick.com') !== false) {
        $data['class'] = 'kick';
        $data['custom_img'] = 'https://downloads.intercomcdn.com/i/o/392376/726bfa27d2180b351b122551/957267843d48c6dddedd3e225bf709cb.png';
    } elseif (strpos($url, 'instagram.com') !== false) {
        $data['class'] = 'instagram'; $data['icon'] = 'fa-brands fa-instagram';
    } elseif (strpos($url, 'tiktok.com') !== false) {
        $data['class'] = 'tiktok'; $data['icon'] = 'fa-brands fa-tiktok';
    } elseif (strpos($url, 'github.com') !== false) {
        $data['class'] = 'github'; $data['icon'] = 'fa-brands fa-github';
    } elseif (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
        $data['class'] = 'youtube'; $data['icon'] = 'fa-brands fa-youtube';
    }
    return $data;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($seo_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo_desc) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo_desc) ?>">
    <meta property="og:image" content="<?= $settings['profile_pic_url'] ?>">
    <meta property="og:type" content="website">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root { --primary: #38bdf8; --bg: #0b0f1a; --card: rgba(30, 41, 59, 0.6); }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: var(--bg); color: white; display: flex; justify-content: center; min-height: 100vh; background: radial-gradient(circle at 50% 10%, #1e293b 0%, #0b0f1a 90%); }
        .container { width: 100%; max-width: 480px; padding: 60px 20px; text-align: center; }
        
        /* ANIMACIONES */
        @keyframes avatarPulse { 0% { box-shadow: 0 0 15px rgba(56, 189, 248, 0.3); border-color: #38bdf8; } 50% { box-shadow: 0 0 30px rgba(56, 189, 248, 0.6); border-color: #7dd3fc; } 100% { box-shadow: 0 0 15px rgba(56, 189, 248, 0.3); border-color: #38bdf8; } }
        @keyframes livePulse { 0% { box-shadow: 0 0 15px rgba(239, 68, 68, 0.5); border-color: #ef4444; } 50% { box-shadow: 0 0 40px rgba(239, 68, 68, 0.8); border-color: #fca5a5; } 100% { box-shadow: 0 0 15px rgba(239, 68, 68, 0.5); border-color: #ef4444; } }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.7; } 100% { opacity: 1; } }

        .avatar-main { width: 110px; height: 110px; border-radius: 50%; border: 3px solid var(--primary); padding: 3px; object-fit: cover; margin-bottom: 15px; animation: avatarPulse 3s infinite ease-in-out; position: relative; }
        .avatar-main.live { border-color: #ef4444; animation: livePulse 1.5s infinite ease-in-out; }

        h1 { font-size: 1.8rem; font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 8px; line-height: 1; }
        .verified-icon { color: #a0a0a0; background: #2d2d2d; border-radius: 50%; font-size: 0.5em; padding: 4px; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); position: relative; top: 2px; }
        .verified-icon:hover::after { content: "Verificado"; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 4px 8px; border-radius: 4px; font-size: 10px; white-space: nowrap; pointer-events: none; }
        
        .live-badge { background: #ef4444; color: white; font-size: 0.7rem; padding: 2px 6px; border-radius: 4px; text-transform: uppercase; font-weight: 800; animation: pulse 1s infinite; vertical-align: middle; margin-left: 5px; }

        .handle { color: var(--primary); font-weight: 600; font-size: 1rem; margin-bottom: 12px; display: block; opacity: 0.9; }
        .bio { color: #94a3b8; font-size: 0.95rem; line-height: 1.5; margin-bottom: 40px; }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        
        .links-container { display: flex; flex-direction: column; gap: 16px; }
        .link-card { background: var(--card); backdrop-filter: blur(12px); padding: 16px; border-radius: 16px; text-decoration: none; color: white; font-weight: 600; font-size: 1.05rem; display: flex; align-items: center; justify-content: center; transition: all 0.3s cubic-bezier(0.23, 1, 0.32, 1); position: relative; overflow: hidden; border: 1px solid rgba(255, 255, 255, 0.08); border-left: 6px solid transparent; border-right: 6px solid transparent; min-height: 68px; opacity: 0; animation: fadeInUp 0.6s ease-out forwards; }
        .link-card:nth-child(1) { animation-delay: 0.1s; } .link-card:nth-child(2) { animation-delay: 0.2s; } .link-card:nth-child(3) { animation-delay: 0.3s; } .link-card:nth-child(4) { animation-delay: 0.4s; } .link-card:nth-child(5) { animation-delay: 0.5s; }
        
        .img-wrapper { position: absolute; left: 18px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
        .link-icon { font-size: 1.5rem; transition: all 0.4s ease; z-index: 2; }
        .link-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; position: absolute; opacity: 0; transform: scale(0.5); transition: all 0.4s ease; z-index: 1; border: 1.5px solid white; }
        .link-text { z-index: 3; transition: all 0.3s; letter-spacing: 0.5px; }
        
        /* HOVER GENERAL */
        .link-card:hover { transform: scale(1.02) translateY(-2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        .link-card:hover .link-icon { opacity: 0; transform: scale(1.4); }
        .link-card:hover .link-avatar { opacity: 1; transform: scale(1); }

        /* YOUTUBE */
        .link-card.youtube { border-left-color: #ff0000; border-right-color: #ff0000; } 
        .link-card.youtube .link-icon { color: #ff0000; } 
        .link-card.youtube:hover { background: #ff0000; color: #fff; border-color: #ff0000; }

        /* KICK */
        .link-card.kick { border-left-color: #53fc18; border-right-color: #53fc18; } 
        .link-card.kick .link-icon { color: #53fc18; } 
        .link-card.kick:hover { background: #53fc18; color: #000; border-color: #53fc18; }

        /* 🆕 INSTAGRAM (CORREGIDO: BORDES SÓLIDOS EN HOVER) */
        .link-card.instagram { border-left-color: #f09433; border-right-color: #bc1888; } 
        .link-card.instagram .link-icon { color: #e1306c; } 
        .link-card.instagram:hover { 
            /* Degradado invertido */
            background: linear-gradient(45deg, #bc1888 0%, #cc2366 25%, #dc2743 50%, #e6683c 75%, #f09433 100%); 
            color: #fff; 
            /* REPARADO: Asignamos los colores extremos a los bordes en lugar de transparent */
            border-left-color: #bc1888;  /* Coincide con el inicio del degradado */
            border-right-color: #f09433; /* Coincide con el final del degradado */
            border-top-color: transparent;
            border-bottom-color: transparent;
        }

        /* TIKTOK */
        .link-card.tiktok { border-left-color: #ff0050; border-right-color: #00f2ea; } 
        .link-card.tiktok .link-icon { color: #fff; } 
        .link-card.tiktok:hover { 
            background: #000; 
            border-left-color: #ff0050; 
            border-right-color: #00f2ea;
        }

        /* GITHUB */
        .link-card.github { border-left-color: #fff; border-right-color: #fff; } 
        .link-card.github .link-icon { color: #fff; } 
        .link-card.github:hover { background: #fff; color: #000; border-color: #fff; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <img src="<?= $settings['profile_pic_url'] ?>" class="avatar-main <?= $is_live ? 'live' : '' ?>">
            
            <h1>
                <?= htmlspecialchars($settings['nombre_perfil']) ?>
                <span class="verified-icon" title="Verificado"><i class="fa-solid fa-check"></i></span>
                <?php if($is_live): ?><span class="live-badge">EN VIVO</span><?php endif; ?>
            </h1>
            
            <span class="handle"><?= htmlspecialchars($settings['youtube_handle'] ?? '@ElBalo') ?></span>
            <p class="bio"><?= nl2br(htmlspecialchars($settings['bio'])) ?></p>
        </header>

        <main class="links-container">
            <?php 
            $now = date('Y-m-d H:i:s');
            
            while($row = $links->fetch_assoc()): 
                if (!empty($row['start_date']) && $now < $row['start_date']) continue;
                if (!empty($row['end_date']) && $now > $row['end_date']) continue;

                $platform = getPlatformData($row['url']);
                $img_src = (!empty($row['local_thumb']) && file_exists($row['local_thumb'])) ? $row['local_thumb'] : $settings['profile_pic_url'];
            ?>
                <a href="go.php?id=<?= $row['id'] ?>" target="_blank" rel="noopener noreferrer" class="link-card <?= $platform['class'] ?>">
                    <div class="img-wrapper">
                        <?php if($platform['custom_img']): ?>
                            <img src="<?= $platform['custom_img'] ?>" class="link-icon" style="width: 28px; height: 28px; object-fit: contain;">
                        <?php else: ?>
                            <i class="<?= $platform['icon'] ?> link-icon"></i>
                        <?php endif; ?>
                        <img src="<?= $img_src ?>" class="link-avatar" loading="lazy">
                    </div>
                    <span class="link-text"><?= htmlspecialchars($row['title']) ?></span>
                </a>
            <?php endwhile; ?>
        </main>
        <footer style="margin-top: 50px; opacity: 0.3; font-size: 0.8rem;">© <?= date('Y') ?> elbalo.lol</footer>
    </div>
</body>
</html>