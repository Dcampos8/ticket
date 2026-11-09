<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../shared/permisos.php';

if (empty($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
if ($ticketId < 1) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'ID de ticket no válido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../../../backend/conexion.php';
$stmt = $conexion->prepare('SELECT actor_usuario, actor_nombre, accion, detalle, fecha FROM ticket_actividad_historial WHERE ticket_id = ? ORDER BY fecha DESC, id DESC');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo cargar la actividad.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$resultado = $stmt->get_result();
$actividad = [];
while ($fila = $resultado->fetch_assoc()) {
    $fila['fecha_fmt'] = date('d/m/Y H:i', strtotime($fila['fecha']));
    $actividad[] = $fila;
}
$stmt->close();
$conexion->close();
echo json_encode(['status' => 'ok', 'actividad' => $actividad], JSON_UNESCAPED_UNICODE);
