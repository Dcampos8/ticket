<?php
session_start();
require('../../backend/conexion.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$id_usuario = (int) $_SESSION['id'];
$tipo = $_GET['tipo'] ?? 'grupo';
$con  = isset($_GET['con']) ? intval($_GET['con']) : 0;
$despues_de = isset($_GET['despues_de']) ? intval($_GET['despues_de']) : 0;

if (!in_array($tipo, ['grupo', 'privado'], true)) {
    http_response_code(400);
    echo json_encode([]);
    exit;
}

if ($tipo === 'grupo') {
    $stmt = mysqli_prepare($conexion, "
        SELECT id, remitente_id, remitente_nombre, mensaje, fecha
        FROM chat_mensajes
        WHERE tipo = 'grupo' AND id > ?
        ORDER BY id ASC
        LIMIT 200
    ");
    mysqli_stmt_bind_param($stmt, "i", $despues_de);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    // Actualizar "hasta dónde vio" el usuario el canal general
    $stmt2 = mysqli_prepare($conexion, "
        INSERT INTO chat_grupo_visto (usuario_id, ultimo_id_visto)
        VALUES (?, (SELECT COALESCE(MAX(id), 0) FROM chat_mensajes WHERE tipo = 'grupo'))
        ON DUPLICATE KEY UPDATE ultimo_id_visto = (SELECT COALESCE(MAX(id), 0) FROM chat_mensajes WHERE tipo = 'grupo')
    ");
    mysqli_stmt_bind_param($stmt2, "i", $id_usuario);
    mysqli_stmt_execute($stmt2);
} else {
    if ($con <= 0) {
        http_response_code(400);
        echo json_encode([]);
        exit;
    }
    $stmt = mysqli_prepare($conexion, "
        SELECT id, remitente_id, remitente_nombre, mensaje, fecha
        FROM chat_mensajes
        WHERE tipo = 'privado' AND id > ?
          AND ((remitente_id = ? AND destinatario_id = ?) OR (remitente_id = ? AND destinatario_id = ?))
        ORDER BY id ASC
        LIMIT 200
    ");
    mysqli_stmt_bind_param($stmt, "iiiii", $despues_de, $id_usuario, $con, $con, $id_usuario);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    // Marcar como leídos los mensajes que me envió ese usuario
    $stmtL = mysqli_prepare($conexion, "UPDATE chat_mensajes SET leido = 1 WHERE tipo = 'privado' AND remitente_id = ? AND destinatario_id = ? AND leido = 0");
    mysqli_stmt_bind_param($stmtL, "ii", $con, $id_usuario);
    mysqli_stmt_execute($stmtL);
}

$mensajes = [];
while ($row = mysqli_fetch_assoc($res)) {
    $mensajes[] = [
        'id' => (int) $row['id'],
        'remitente_id' => (int) $row['remitente_id'],
        'remitente_nombre' => $row['remitente_nombre'],
        'mensaje' => $row['mensaje'],
        'fecha' => $row['fecha'],
        'propio' => (int) $row['remitente_id'] === $id_usuario,
    ];
}

echo json_encode($mensajes);
