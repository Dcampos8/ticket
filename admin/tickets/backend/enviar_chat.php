<?php
session_start();
header('Content-Type: application/json');
require_once '../../../shared/security.php';
require_once '../../../shared/ticket_activity.php';

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'No autorizado']);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(['status' => 'error', 'msg' => 'Método no permitido']);
    exit;
}
if (!validarTokenCsrf()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'La sesión expiró. Recarga la página e intenta de nuevo.']);
    exit;
}
if (!isset($_POST['ticket_id']) || trim((string) ($_POST['mensaje'] ?? '')) === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'Datos incompletos']);
    exit;
}

require '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';

$ticket_id = intval($_POST['ticket_id']);
if (!usuarioPuedeAccederTicket($conexion, $ticket_id)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'msg' => 'Ticket no encontrado']);
    exit;
}
$usuario = (string) ($_SESSION['usuario'] ?? '');
$mensaje = trim((string) $_POST['mensaje']);
if (mb_strlen($mensaje, 'UTF-8') > 5000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'msg' => 'El mensaje supera el límite permitido']);
    exit;
}

$stmt = $conexion->prepare("INSERT INTO ticket_chat (ticket_id, usuario, mensaje) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $ticket_id, $usuario, $mensaje);

if ($stmt->execute()) {
    if (esAdminOSuperior()) {
        $respuesta = $conexion->prepare('UPDATE tickets SET fecha_primera_respuesta = COALESCE(fecha_primera_respuesta, NOW()) WHERE id = ?');
        $respuesta->bind_param('i', $ticket_id);
        $respuesta->execute();
        $respuesta->close();
        registrarActividadTicket($conexion, $ticket_id, 'chat', 'Respondió en el chat del ticket.');
    }
    echo json_encode(['status' => 'ok']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'Error al guardar mensaje']);
}

$stmt->close();
$conexion->close();
?>
