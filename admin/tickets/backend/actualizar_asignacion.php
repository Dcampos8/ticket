<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/security.php';
require_once __DIR__ . '/../../../shared/ticket_activity.php';

function responderAsignacion(int $status, string $mensaje): void
{
    http_response_code($status);
    echo json_encode(['status' => $status < 400 ? 'ok' : 'error', 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responderAsignacion(405, 'Método no permitido.');
if (empty($_SESSION['logueado']) || !in_array(strtolower((string) ($_SESSION['rol'] ?? '')), ['admin', 'superadmin'], true) || !tieneModulo('tickets')) responderAsignacion(403, 'Solo admin y superadmin pueden cambiar la prioridad.');
if (!validarTokenCsrf()) responderAsignacion(403, 'La sesión expiró. Recarga la página.');

$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
$asignado = trim((string) ($_POST['asignado_a'] ?? ''));
$prioridad = trim((string) ($_POST['prioridad'] ?? ''));
$prioridades = ['Baja', 'Normal', 'Alta', 'Urgente'];
if ($ticketId < 1 || !in_array($prioridad, $prioridades, true) || mb_strlen($asignado, 'UTF-8') > 50) {
    responderAsignacion(422, 'Revisa la prioridad y el responsable.');
}

require_once __DIR__ . '/../../../backend/conexion.php';
if ($asignado !== '') {
    $stmt = $conexion->prepare("SELECT rol, modulos_permitidos FROM usuarios WHERE usuario = ? AND rol IN ('admin','superadmin') LIMIT 1");
    $stmt->bind_param('s', $asignado);
    $stmt->execute();
    $cuentaResponsable = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $puedeAtenderTickets = $cuentaResponsable && ($cuentaResponsable['rol'] === 'superadmin'
        || in_array('tickets', parsearModulos($cuentaResponsable['modulos_permitidos'] ?? ''), true));
    if (!$puedeAtenderTickets) responderAsignacion(422, 'El responsable debe tener acceso al módulo de tickets.');
}

$stmtTicket = $conexion->prepare('SELECT asignado_a, prioridad, estatus FROM tickets WHERE id = ? LIMIT 1');
$stmtTicket->bind_param('i', $ticketId);
$stmtTicket->execute();
$ticketActual = $stmtTicket->get_result()->fetch_assoc();
$stmtTicket->close();
if (!$ticketActual) responderAsignacion(404, 'No se encontró el ticket.');
if (in_array($ticketActual['estatus'], ['Finalizado', 'Cerrado', 'Cancelado'], true)
    && (string) ($ticketActual['prioridad'] ?? 'Normal') !== $prioridad) {
    responderAsignacion(409, 'No se puede cambiar la prioridad de un ticket cerrado.');
}

$stmt = $conexion->prepare('SELECT respuesta_minutos, resolucion_minutos FROM ticket_sla_politicas WHERE prioridad = ?');
$stmt->bind_param('s', $prioridad);
$stmt->execute();
$objetivos = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$objetivos) responderAsignacion(500, 'No existe una política SLA para esa prioridad.');

$stmt = $conexion->prepare('UPDATE tickets SET asignado_a = NULLIF(?, \'\'), prioridad = ?, objetivo_respuesta_minutos = ?, objetivo_resolucion_minutos = ? WHERE id = ?');
$respuestaMinutos = (int) $objetivos['respuesta_minutos'];
$resolucionMinutos = (int) $objetivos['resolucion_minutos'];
$stmt->bind_param('ssiii', $asignado, $prioridad, $respuestaMinutos, $resolucionMinutos, $ticketId);
if (!$stmt->execute() || $stmt->affected_rows < 0) responderAsignacion(500, 'No se pudo guardar la asignación.');
$stmt->close();
$responsableAnterior = (string) ($ticketActual['asignado_a'] ?? '');
$prioridadAnterior = (string) ($ticketActual['prioridad'] ?? 'Normal');
if ($responsableAnterior !== $asignado || $prioridadAnterior !== $prioridad) {
    $detalle = 'Responsable: ' . ($responsableAnterior !== '' ? $responsableAnterior : 'Sin asignar') . ' → ' . ($asignado !== '' ? $asignado : 'Sin asignar')
        . '; prioridad: ' . $prioridadAnterior . ' → ' . $prioridad . '.';
    registrarActividadTicket($conexion, $ticketId, 'asignacion', $detalle, false);
}
$conexion->close();
responderAsignacion(200, 'Asignación y prioridad guardadas.');
