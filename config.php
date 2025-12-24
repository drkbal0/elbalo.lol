<?php
    
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
$host = 'sql108.byetcluster.com'; // Tu MySQL hostname
$user = 'mseet_40694072'; // Tu FTP/MySQL username
$pass = 'UjO1qflpDJmy';    // Tu contraseña de hosting
$db   = 'mseet_40694072_links'; // Nombre de la DB creada

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Error de conexión");
?>