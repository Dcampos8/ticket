<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../backend/conexion.php';
require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/security.php';
require_once __DIR__ . '/../../../shared/ticket_activity.php';

function responderReapertura(int $status, string $mensaje, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(['status' => $status < 400 ? 'ok' : 'error', 'message' => $mensaje] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    responderReapertura(405, 'Método no permitido.');
}
$rol = strtolower(trim((string) ($_SESSION['rol'] ?? '')));
if (empty($_SESSION['logueado']) || !in_array($rol, ['admin', 'superadmin'], true) || !tieneModulo('tickets')) {
    responderReapertura(403, 'Solo admin y superadmin pueden reabrir tickets.');
}
if (!validarTokenCsrf()) {
    responderReapertura(403, 'La sesión expiró. Recarga la página.');
}

$ticketId = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
if ($ticketId < 1) {
    responderReapertura(422, 'El ticket indicado no es válido.');
}

$conexion->begin_transaction();
try {
    $stmt = $conexion->prepare('SELECT estatus FROM tickets WHERE id = ? LIMIT 1 FOR UPDATE');
    if (!$stmt) throw new RuntimeException('No se pudo consultar el ticket.');
    $stmt->bind_param('i', $ticketId);
    $stmt->execute();
    $ticket = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$ticket) {
        $conexion->rollback();
        responderReapertura(404, 'No se encontró el ticket.');
    }

    $estatusAnterior = trim((string) ($ticket['estatus'] ?? ''));
    if ($estatusAnterior === 'Pendiente') {
        $conexion->commit();
        responderReapertura(200, 'El ticket ya se encuentra Pendiente.', ['changed' => false]);
    }

    $stmt = $conexion->prepare("UPDATE tickets SET estatus = 'Pendiente', fecha_resolucion = NULL, fecha_modificacion = NOW() WHERE id = ?");
    if (!$stmt) throw new RuntimeException('No se pudo preparar la reapertura.');
    $stmt->bind_param('i', $ticketId);
    if (!$stmt->execute()) throw new RuntimeException('No se pudo reabrir el ticket.');
    $stmt->close();

    $tablaHistorial = $conexion->query("SHOW TABLES LIKE 'ticket_actividad_historial'");
    if ($tablaHistorial && $tablaHistorial->num_rows > 0) {
        $detalle = 'Cambió el estatus de ' . $estatusAnterior . ' a Pendiente (ticket reabierto).';
        registrarActividadTicket($conexion, $ticketId, 'estatus', $detalle);
    }

    $conexion->commit();
    responderReapertura(200, 'Ticket reabierto como Pendiente.', ['changed' => true, 'estatus' => 'Pendiente']);
} catch (Throwable $error) {
    $conexion->rollback();
    error_log('Tickets: error al reabrir ticket #' . $ticketId . '.');
    responderReapertura(500, 'No se pudo reabrir el ticket.');
}
