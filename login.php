<?php
session_start();
include 'config.php';

// Si ya está logueado, ir al admin
if (isset($_SESSION['admin'])) {
    header("Location: admin.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = mysqli_real_escape_string($conn, $_POST['username']);
    $pass = $_POST['password'];

    // Buscar usuario en la nueva tabla 'users'
    $sql = "SELECT * FROM users WHERE username = '$user' LIMIT 1";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Verificar contraseña encriptada
        if (password_verify($pass, $row['password'])) {
            $_SESSION['admin'] = true;
            header("Location: admin.php");
            exit;
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | ElBalo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background: #0b0f1a; 
            color: white; 
            font-family: 'Inter', sans-serif;
            display: flex; justify-content: center; align-items: center; 
            height: 100vh; margin: 0;
        }
        .login-card {
            background: #1e293b;
            padding: 40px;
            border-radius: 12px;
            border: 1px solid #334155;
            width: 100%; max-width: 350px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        input {
            width: 100%; padding: 12px; margin: 10px 0;
            background: #0f172a; border: 1px solid #334155;
            color: white; border-radius: 6px; box-sizing: border-box;
        }
        button {
            width: 100%; padding: 12px; margin-top: 10px;
            background: #38bdf8; color: #0b0f1a; border: none;
            border-radius: 6px; font-weight: bold; cursor: pointer;
        }
        button:hover { opacity: 0.9; }
        .error { color: #ef4444; margin-bottom: 15px; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2 style="margin-top:0">Acceso Admin</h2>
        
        <?php if($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="Usuario (admin)" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>