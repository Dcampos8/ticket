<?php
session_start();
include '../../../backend/conexion.php';

header('Content-Type: application/json');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit;
}

$ticket_id = intval($_GET['ticket_id'] ?? 0);

if ($ticket_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
    exit;
}

$stmt = $conexion->prepare("
    SELECT admin_usuario, comentario, fecha
    FROM ticket_comentarios_historial
    WHERE ticket_id = ?
    ORDER BY fecha DESC
");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();

$historial = [];
while ($row = $result->fetch_assoc()) {
    $row['fecha_fmt'] = date("d/m/Y H:i", strtotime($row['fecha']));
    $historial[] = $row;
}

echo json_encode(['status' => 'ok', 'historial' => $historial]);

$stmt->close();
$conexion->close();
