<?php
// Evita que errores de aviso detengan la redirección o la ensucien
error_reporting(0); 

include 'config.php';

// CORRECCIÓN: Validar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    // 1. Buscamos la URL
    $res = $conn->query("SELECT url FROM links WHERE id = $id");
    
    if ($res && $row = $res->fetch_assoc()) {
        $url_destino = $row['url'];

        // 2. LOGICA AVANZADA: Registrar que ESTE visitante hizo clic
        if(isset($_SESSION['visitor_id'])) {
            $visitor_id = $_SESSION['visitor_id'];
            $now = date('Y-m-d H:i:s');
            
            // Usamos una sentencia preparada para evitar errores
            $stmt = $conn->prepare("INSERT INTO analytics_clicks (visitor_id, link_id, clicked_at) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iis", $visitor_id, $id, $now);
                $stmt->execute();
            }
        }

        // 3. Contador general
        $conn->query("UPDATE links SET clicks = clicks + 1 WHERE id = $id");

        // 4. Redirección limpia
        header("Location: " . $url_destino);
        exit;
    }
}

// Si falla, cerramos
echo "<script>window.close(); window.location.href='index.php';</script>";
exit;
?>