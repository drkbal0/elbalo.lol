<?php
// 1. Forzamos que se muestren los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Iniciando proceso...<br>";

// 2. Credenciales (Cámbialas por las tuyas aquí mismo para probar)
$host = 'sql108.byetcluster.com'; // Tu MySQL hostname
$user = 'mseet_40694072'; // Tu FTP/MySQL username
$pass = 'UjO1qflpDJmy';    // Tu contraseña de hosting
$db   = 'mseet_40694072_links'; // Nombre de la DB creada

// 3. Intento de conexión
$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
echo "Conexión a Base de Datos: OK<br>";

// 4. Crear tabla settings si no existe (por si acaso)
$sql_table = "CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50),
    password VARCHAR(255),
    nombre_perfil VARCHAR(100),
    bio TEXT
)";
$conn->query($sql_table);

// 5. Limpiar e Insertar
$conn->query("TRUNCATE TABLE settings");

$usuario = 'admin';
$password_plana = '12345';
$hash = password_hash($password_plana, PASSWORD_DEFAULT);

$sql_insert = "INSERT INTO settings (username, password, nombre_perfil, bio) 
               VALUES ('$usuario', '$hash', 'Mi Perfil', 'Bienvenido')";

if ($conn->query($sql_insert)) {
    echo "-----------------------------------<br>";
    echo "¡USUARIO CREADO CON ÉXITO!<br>";
    echo "Usuario: <b>$usuario</b><br>";
    echo "Password: <b>$password_plana</b><br>";
    echo "-----------------------------------<br>";
    echo "<a href='login.php'>Ir al Login ahora</a>";
} else {
    echo "Error al insertar: " . $conn->error;
}
?>