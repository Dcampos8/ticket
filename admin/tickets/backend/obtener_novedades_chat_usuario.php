<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

require '../../../backend/conexion.php';

if (empty($_SESSION['logueado']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'msg' => 'No autorizado']);
    exit;
}

$idsEntrada = explode(',', (string) ($_GET['ticket_ids'] ?? ''));
$ticketIds = array_values(array_unique(array_filter(
    array_map('intval', array_slice($idsEntrada, 0, 20)),
    static fn(int $id): bool => $id > 0
)));

if (!$ticketIds) {
    echo json_encode(['status' => 'ok', 'tickets' => new stdClass()], JSON_UNESCAPED_UNICODE);
    exit;
}

$tickets = array_fill_keys(array_map('strval', $ticketIds), null);
$marcadores = implode(',', array_fill(0, count($ticketIds), '?'));
$usuario = (string) ($_SESSION['usuario'] ?? '');
$puedeVerTodos = !empty($_SESSION['puede_ver_todos_tickets']);

$sql = "SELECT c.ticket_id, c.usuario, c.mensaje, c.fecha
        FROM ticket_chat c
        INNER JOIN (
            SELECT ticket_id, MAX(id) AS ultimo_id
            FROM ticket_chat
            WHERE ticket_id IN ($marcadores)
              AND LOWER(TRIM(usuario)) <> LOWER(TRIM(?))
            GROUP BY ticket_id
        ) ultimos ON ultimos.ultimo_id = c.id
        INNER JOIN tickets t ON t.id = c.ticket_id";
$tipos = str_repeat('i', count($ticketIds)) . 's';
$parametros = array_merge($ticketIds, [$usuario]);

if (!$puedeVerTodos) {
    $sql .= ' AND t.nombre = ?';
    $tipos .= 's';
    $parametros[] = $usuario;
}
$sql .= ' ORDER BY c.ticket_id ASC, c.fecha DESC';

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => 'No se pudieron revisar los mensajes']);
    exit;
}

$referencias = [$tipos];
foreach ($parametros as $indice => $valor) {
    $referencias[] = &$parametros[$indice];
}
call_user_func_array([$stmt, 'bind_param'], $referencias);
$stmt->execute();
$resultado = $stmt->get_result();

while ($fila = $resultado->fetch_assoc()) {
    $id = (string) $fila['ticket_id'];
    // El orden descendente hace que la primera fila por ticket sea la más nueva.
    if (array_key_exists($id, $tickets) && $tickets[$id] === null) {
        $tickets[$id] = [
            'usuario' => $fila['usuario'],
            'mensaje' => $fila['mensaje'],
            'fecha' => $fila['fecha'],
        ];
    }
}

echo json_encode(['status' => 'ok', 'tickets' => $tickets], JSON_UNESCAPED_UNICODE);
$stmt->close();
$conexion->close();
