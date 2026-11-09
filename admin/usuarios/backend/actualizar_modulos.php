<?php
/**
 * Guarda la lista de módulos permitidos para un usuario con rol 'admin'.
 * Solo el superadmin puede llamar a este endpoint.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/../../../shared/permisos.php');

if (!isset($_SESSION['logueado']) || !esSuperAdmin()) {
    http_response_code(403);
    exit("No autorizado");
}

require_once __DIR__ . '/../../../backend/conexion.php';

if (!isset($_POST['usuario'])) {
    http_response_code(400);
    exit("Parámetros incompletos");
}

$usuario = $_POST['usuario'];
$modulosRecibidos = $_POST['modulos'] ?? [];
if (!is_array($modulosRecibidos)) {
    $modulosRecibidos = [];
}

// Nunca se guardan módulos para el superadmin (siempre tiene todo) ni
// se permite tocar la fila de otro superadmin desde aquí.
$check = $conexion->prepare("SELECT rol FROM usuarios WHERE usuario = ?");
$check->bind_param("s", $usuario);
$check->execute();
$rolActual = $check->get_result()->fetch_assoc()['rol'] ?? null;
$check->close();

if ($rolActual !== 'admin') {
    http_response_code(400);
    exit("Solo se pueden asignar módulos a usuarios con rol 'admin'");
}

$modulosCsv = serializarModulos($modulosRecibidos);

$stmt = $conexion->prepare("UPDATE usuarios SET modulos_permitidos = ? WHERE usuario = ?");
$stmt->bind_param("ss", $modulosCsv, $usuario);
$stmt->execute();
$stmt->close();
$conexion->close();

echo "OK";
?>
