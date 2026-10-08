<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Detectar la ruta raíz de forma dinámica
// Esto es más seguro que escribir /home/u568802486/...
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__FILE__) . '/'); 
}

// 2. URL Base
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/shared/integration_settings.php';
    $baseUrl = rtrim((string) obtenerConfiguracionIntegracion('APP_BASE_URL', 'https://ticket.transportesvaladez.com/'), '/') . '/';
    define('BASE_URL', $baseUrl);
}

// 3. Conexión a BD (Solo si el archivo existe)
$conexion_path = ROOT_PATH . 'backend/conexion.php';
if (file_exists($conexion_path)) {
    require_once $conexion_path;
} else {
    error_log('No se encontró el archivo de conexión de la aplicación.');
    http_response_code(503);
    die('El servicio no está disponible temporalmente.');
}
