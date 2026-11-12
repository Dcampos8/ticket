<?php
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (empty($_SESSION['logueado']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso no autorizado']);
    exit;
}

require_once ROOT_PATH . 'backend/conexion.php';
$puedeVerTodos = isset($_SESSION['puede_ver_todos_tickets']) && $_SESSION['puede_ver_todos_tickets'] == 1;
if ($puedeVerTodos) {
    $stmt = $conexion->prepare('SELECT id, estatus FROM tickets ORDER BY id');
} else {
    $stmt = $conexion->prepare('SELECT id, estatus FROM tickets WHERE nombre = ? ORDER BY id');
    $stmt->bind_param('s', $_SESSION['usuario']);
}

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'No se pudieron consultar los estados']);
    exit;
}

$tickets = [];
$resultado = $stmt->get_result();
while ($fila = $resultado->fetch_assoc()) {
    $tickets[] = ['id' => (int) $fila['id'], 'estatus' => (string) $fila['estatus']];
}
echo json_encode(['tickets' => $tickets], JSON_UNESCAPED_UNICODE);
