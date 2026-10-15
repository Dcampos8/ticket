<?php
// /backend/conexion.php
// Usamos una variable global para reutilizar la conexión existente
// Este archivo también puede cargarse desde funciones; declarar el alias global
// evita que require_once deje $conexion limitado al ámbito local de esa función.
global $conexion;

if (!isset($GLOBALS['db_conexion'])) {
    require_once dirname(__DIR__) . '/config/env.php';

    $host = env('DB_HOST');
    $usuario = env('DB_USER');
    $contrasena = env('DB_PASS');
    $basedatos = env('DB_NAME');

    try {
        $conn = new mysqli($host, $usuario, $contrasena, $basedatos);
    } catch (mysqli_sql_exception $e) {
        error_log('Conexión a base de datos fallida (código ' . $e->getCode() . ').');
        http_response_code(503);
        exit('El servicio no está disponible temporalmente.');
    }

    if ($conn->connect_error) {
        error_log('Conexión a base de datos fallida (código ' . $conn->connect_errno . ').');
        http_response_code(503);
        exit('El servicio no está disponible temporalmente.');
    }
    $conn->set_charset("utf8mb4");
    $GLOBALS['db_conexion'] = $conn;
}
$conexion = $GLOBALS['db_conexion'];
?>
