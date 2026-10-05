<?php
// /backend/conexion.php
// Usamos una variable global para reutilizar la conexión existente
if (!isset($GLOBALS['db_conexion'])) {
    require_once dirname(__DIR__) . '/config/env.php';

    $host = env('DB_HOST');
    $usuario = env('DB_USER');
    $contrasena = env('DB_PASS');
    $basedatos = env('DB_NAME');

    $conn = new mysqli($host, $usuario, $contrasena, $basedatos);

    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    $GLOBALS['db_conexion'] = $conn;
}
$conexion = $GLOBALS['db_conexion'];
?>