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
    define('BASE_URL', 'https://ticket.transportesvaladez.com/');
}

// 3. Conexión a BD (Solo si el archivo existe)
$conexion_path = ROOT_PATH . 'backend/conexion.php';
if (file_exists($conexion_path)) {
    require_once $conexion_path;
} else {
    // Esto te ayudará a saber si la ruta está mal sin dar error 500
    die("Error técnico: No se encontró la conexión en: " . $conexion_path);
}