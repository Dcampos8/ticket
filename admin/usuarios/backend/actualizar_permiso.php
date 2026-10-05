<?php
session_start();
require_once __DIR__ . '/../../../shared/permisos.php';
if (!isset($_SESSION['logueado']) || !tieneModulo('tickets')) {
  http_response_code(403);
  exit("No autorizado");
}

include '../../../backend/conexion.php';

if (isset($_POST['usuario'], $_POST['permiso'])) {
  $usuario = $_POST['usuario'];
  $permiso = intval($_POST['permiso']);

  // Esta opción también modifica la fila de usuarios: un admin no puede
  // cambiar permisos de tickets del superadmin mediante una petición directa.
  if (!esSuperAdmin()) {
    $check = $conexion->prepare("SELECT rol FROM usuarios WHERE usuario = ?");
    $check->bind_param("s", $usuario);
    $check->execute();
    $rolObjetivo = $check->get_result()->fetch_assoc()['rol'] ?? null;
    $check->close();
    if (strtolower(trim((string) $rolObjetivo)) === 'superadmin') {
      http_response_code(403);
      exit("No tienes permiso para modificar esta cuenta");
    }
  }

  $stmt = $conexion->prepare("UPDATE usuarios SET puede_ver_todos_tickets = ? WHERE usuario = ?");
  $stmt->bind_param("is", $permiso, $usuario);
  $stmt->execute();
  $stmt->close();

  echo "OK";
} else {
  http_response_code(400);
  echo "Parámetros incompletos";
}
?>
