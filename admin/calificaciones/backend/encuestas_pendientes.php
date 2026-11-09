<?php
session_start();
require('../../../backend/conexion.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$usuario = $_SESSION['usuario'] ?? null;
$id_usuario = $_SESSION['id'] ?? 0;
$pendientes = [];

// 1) Tickets finalizados/cerrados del usuario que aún no tienen encuesta
if ($usuario) {
    $stmt = mysqli_prepare($conexion, "
        SELECT t.id, t.descripcion
        FROM tickets t
        LEFT JOIN encuestas_satisfaccion e
               ON e.tipo = 'ticket' AND e.referencia_id = t.id
        WHERE t.nombre = ?
          AND t.estatus IN ('Finalizado', 'Cerrado')
          AND e.id IS NULL
        ORDER BY t.fecha_modificacion DESC
    ");
    mysqli_stmt_bind_param($stmt, "s", $usuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $pendientes[] = [
            'tipo' => 'ticket',
            'referencia_id' => (int) $row['id'],
            'titulo' => 'Ticket #' . $row['id'],
            'detalle' => mb_strimwidth($row['descripcion'], 0, 120, '...'),
            'pregunta_resuelto' => '¿Se resolvió tu problema?',
        ];
    }
}

// 2) Mantenimientos realizados donde el usuario es el responsable notificado
if ($id_usuario > 0) {
    $stmt = mysqli_prepare($conexion, "
        SELECT n.id_mantenimiento, n.mensaje
        FROM notificaciones n
        LEFT JOIN encuestas_satisfaccion e
               ON e.tipo = 'mantenimiento' AND e.referencia_id = n.id_mantenimiento
        WHERE n.id_usuario = ?
          AND n.leida = 0
          AND e.id IS NULL
        ORDER BY n.id DESC
    ");
    mysqli_stmt_bind_param($stmt, "i", $id_usuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $pendientes[] = [
            'tipo' => 'mantenimiento',
            'referencia_id' => (int) $row['id_mantenimiento'],
            'titulo' => 'Mantenimiento #' . $row['id_mantenimiento'],
            'detalle' => $row['mensaje'],
            'pregunta_resuelto' => '¿Quedó resuelto el problema con tu equipo?',
        ];
    }
}

echo json_encode($pendientes);
