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

// Aplicar también el cierre cuando un endpoint conecta directamente a la BD
// sin pasar por config.php. Así cubre páginas y acciones AJAX/backend.
if (PHP_SAPI !== 'cli') {
    require_once dirname(__DIR__) . '/shared/integration_settings.php';
    $sitioBaja = obtenerConfiguracionIntegracion('SITIO_DADO_DE_BAJA', '0') === '1';
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $rutaPermitida = preg_match('#/core/(validar_login|logout)\.php$#', $script) === 1;
    if ($sitioBaja
        && !empty($_SESSION['logueado'])
        && strtolower((string) ($_SESSION['rol'] ?? '')) !== 'superadmin'
        && strtolower(pathinfo($script, PATHINFO_EXTENSION)) === 'php'
        && !$rutaPermitida) {
        http_response_code(503);
        header('Retry-After: 3600');
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sitio temporalmente fuera de servicio</title><body style="font:16px system-ui,sans-serif;max-width:620px;margin:12vh auto;padding:24px;color:#273142"><h1>Sitio temporalmente fuera de servicio</h1><p>El acceso está suspendido temporalmente. Intenta de nuevo más tarde o contacta al administrador.</p></body></html>');
    }
}
?>
