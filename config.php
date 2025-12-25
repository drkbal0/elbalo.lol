<?php
    
    ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
$host = ''; // Tu MySQL hostname
$user = ''; // Tu FTP/MySQL username
$pass =     // Tu contraseña de hosting
$db   =  // Nombre de la DB creada

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) die("Error de conexión");

?>
