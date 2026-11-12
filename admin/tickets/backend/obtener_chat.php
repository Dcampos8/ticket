<?php
session_start();
date_default_timezone_set('America/Mexico_City');
header('Content-Type: application/json; charset=utf-8');

require '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';
require_once '../../../shared/security.php';

if (empty($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'No autorizado']);
    exit;
}

$ticketId = (int) ($_GET['ticket_id'] ?? 0);
if (!usuarioPuedeAccederTicket($conexion, $ticketId)) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'msg' => 'Ticket no encontrado']);
    exit;
}

$stmt = $conexion->prepare('SELECT usuario, mensaje, fecha FROM ticket_chat WHERE ticket_id = ? ORDER BY fecha ASC');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'No se pudo cargar el chat']);
    exit;
}
$stmt->bind_param('i', $ticketId);
$stmt->execute();
$result = $stmt->get_result();

$mensajes = [];
while ($fila = $result->fetch_assoc()) {
    $fechaMensaje = new DateTimeImmutable((string) $fila['fecha'], new DateTimeZone('UTC'));
    $fila['fecha'] = $fechaMensaje->setTimezone(new DateTimeZone('America/Mexico_City'))->format('d/m/Y H:i');
    $mensajes[] = $fila;
}

echo json_encode($mensajes, JSON_UNESCAPED_UNICODE);
$stmt->close();
$conexion->close();
