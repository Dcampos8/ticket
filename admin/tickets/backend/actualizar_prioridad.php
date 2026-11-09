<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/security.php';
require_once __DIR__ . '/../../../shared/ticket_activity.php';

function responderPrioridad(int $status, string $mensaje): void
{
    http_response_code($status);
    echo json_encode(['status' => $status < 400 ? 'ok' : 'error', 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responderPrioridad(405, 'Método no permitido.');
if (empty($_SESSION['logueado']) || !in_array(strtolower((string) ($_SESSION['rol'] ?? '')), ['admin', 'superadmin'], true) || !tieneModulo('tickets')) responderPrioridad(403, 'Solo admin y superadmin pueden cambiar la prioridad.');
if (!validarTokenCsrf()) responderPrioridad(403, 'La sesión expiró. Recarga la página.');

$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
$prioridad = trim((string) ($_POST['prioridad'] ?? ''));
if ($ticketId < 1 || !in_array($prioridad, ['Baja', 'Normal', 'Alta', 'Urgente'], true)) {
    responderPrioridad(422, 'Selecciona una prioridad válida.');
}

require_once __DIR__ . '/../../../backend/conexion.php';
$stmt = $conexion->prepare('SELECT estatus, prioridad FROM tickets WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$ticket) responderPrioridad(404, 'No se encontró el ticket.');

if (in_array($ticket['estatus'], ['Finalizado', 'Cerrado', 'Cancelado'], true)) {
    responderPrioridad(409, 'No se puede cambiar la prioridad de un ticket cerrado.');
}

$stmt = $conexion->prepare('SELECT respuesta_minutos, resolucion_minutos FROM ticket_sla_politicas WHERE prioridad = ? LIMIT 1');
$stmt->bind_param('s', $prioridad);
$stmt->execute();
$objetivos = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$objetivos) responderPrioridad(500, 'No existe una política SLA para esa prioridad.');

$respuestaMinutos = (int) $objetivos['respuesta_minutos'];
$resolucionMinutos = (int) $objetivos['resolucion_minutos'];
$stmt = $conexion->prepare('UPDATE tickets SET prioridad = ?, objetivo_respuesta_minutos = ?, objetivo_resolucion_minutos = ? WHERE id = ?');
$stmt->bind_param('siii', $prioridad, $respuestaMinutos, $resolucionMinutos, $ticketId);

if (!$stmt->execute()) responderPrioridad(500, 'No se pudo guardar la prioridad.');
$stmt->close();
$prioridadAnterior = (string) ($ticket['prioridad'] ?? 'Normal');
if ($prioridadAnterior !== $prioridad) {
    registrarActividadTicket($conexion, $ticketId, 'prioridad', 'Cambió la prioridad de ' . $prioridadAnterior . ' a ' . $prioridad . '.');
}
$conexion->close();
responderPrioridad(200, 'Prioridad guardada.');
