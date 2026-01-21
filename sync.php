<?php
include 'config.php';

function sincronizarYoutube($handle, $conn) {
    // 1. Limpiar el handle (asegurar que tenga el @)
    if (strpos($handle, '@') === false) $handle = '@' . $handle;
    
    $url = "https://www.youtube.com/" . $handle;
    
    // 2. Obtener el contenido del canal
    $html = file_get_contents($url);
    if (!$html) return false;

    // 3. Extraer Nombre y Foto usando Meta Tags (OG Tags)
    preg_match('/<meta property="og:title" content="(.*?)">/', $html, $title_match);
    preg_match('/<meta property="og:image" content="(.*?)">/', $html, $image_match);

    $nombre = isset($title_match[1]) ? $title_match[1] : 'Balo';
    $foto = isset($image_match[1]) ? $image_match[1] : '';

    // 4. Guardar en la base de datos
    if ($foto) {
        $stmt = $conn->prepare("UPDATE settings SET nombre_perfil = ?, profile_pic_url = ?, youtube_handle = ? WHERE id = 1");
        $stmt->bind_param("sss", $nombre, $foto, $handle);
        $stmt->execute();
        return true;
    }
    return false;
}

// Si se llama desde el admin
if (isset($_GET['run'])) {
    if (sincronizarYoutube('@ElBalo', $conn)) {
        header("Location: admin.php?sync=ok");
    } else {
        header("Location: admin.php?sync=error");
    }
}
?>