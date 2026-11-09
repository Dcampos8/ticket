<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../../shared/permisos.php');

if (!isset($_SESSION['logueado']) || !esSuperAdmin()) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'No autorizado']));
}

require_once __DIR__ . '/../../../backend/conexion.php';

header('Content-Type: application/json');

$accion = $_POST['accion'] ?? 'crear';

if ($accion === 'crear') {
    $clave = strtolower(trim($_POST['clave'] ?? ''));
    $label = trim($_POST['label'] ?? '');
    $icon  = trim($_POST['icon'] ?? 'fa-cubes');
    $ruta  = trim($_POST['ruta'] ?? '');

    // Limpiar clave (solo letras, números y guiones bajos)
    $clave = preg_replace('/[^a-z0-9_]/', '', $clave);

    if (empty($clave) || empty($label)) {
        echo json_encode(['status' => 'error', 'message' => 'La clave y la etiqueta son obligatorias.']);
        exit();
    }

    $stmt = $conexion->prepare("INSERT INTO modulos_sistema (clave, label, icon, ruta) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE label=?, icon=?, ruta=?");
    $stmt->bind_param("sssssss", $clave, $label, $icon, $ruta, $label, $icon, $ruta);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Módulo guardado correctamente.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al guardar en la base de datos.']);
    }
    $stmt->close();
} elseif ($accion === 'eliminar') {
    $clave = trim($_POST['clave'] ?? '');
    
    $stmt = $conexion->prepare("DELETE FROM modulos_sistema WHERE clave = ?");
    $stmt->bind_param("s", $clave);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Módulo eliminado.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al eliminar.']);
    }
    $stmt->close();
}

$conexion->close();