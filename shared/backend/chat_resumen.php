<?php
session_start();
require('../../backend/conexion.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['success' => false]);
    exit;
}

$id_usuario = (int) $_SESSION['id'];

// --- No leídos del canal general ---
$ultimo_visto = 0;
$stmt = mysqli_prepare($conexion, "SELECT ultimo_id_visto FROM chat_grupo_visto WHERE usuario_id = ?");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$r = mysqli_stmt_get_result($stmt);
if ($row = mysqli_fetch_assoc($r)) {
    $ultimo_visto = (int) $row['ultimo_id_visto'];
}

$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS c FROM chat_mensajes WHERE tipo = 'grupo' AND id > ? AND remitente_id != ?");
mysqli_stmt_bind_param($stmt, "ii", $ultimo_visto, $id_usuario);
mysqli_stmt_execute($stmt);
$grupo_no_leidos = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['c'];

// Último mensaje del grupo (para detectar mensajes nuevos vía polling)
$r = mysqli_query($conexion, "SELECT id, remitente_nombre, mensaje FROM chat_mensajes WHERE tipo = 'grupo' ORDER BY id DESC LIMIT 1");
$ultimo_msg_grupo = mysqli_fetch_assoc($r);

// --- No leídos por conversación privada, agrupados por remitente ---
$stmt = mysqli_prepare($conexion, "
    SELECT remitente_id, remitente_nombre, COUNT(*) AS no_leidos, MAX(id) AS ultimo_id, SUBSTRING(MAX(mensaje), 1, 80) AS ultimo_mensaje
    FROM chat_mensajes
    WHERE tipo = 'privado' AND destinatario_id = ? AND leido = 0
    GROUP BY remitente_id, remitente_nombre
");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$privados = [];
$total_privados = 0;
while ($row = mysqli_fetch_assoc($res)) {
    $privados[] = [
        'usuario_id' => (int) $row['remitente_id'],
        'nombre' => $row['remitente_nombre'],
        'no_leidos' => (int) $row['no_leidos'],
        'ultimo_id' => (int) $row['ultimo_id'],
        'ultimo_mensaje' => $row['ultimo_mensaje'],
    ];
    $total_privados += (int) $row['no_leidos'];
}

echo json_encode([
    'success' => true,
    'grupo_no_leidos' => $grupo_no_leidos,
    'ultimo_msg_grupo' => $ultimo_msg_grupo ?: null,
    'privados' => $privados,
    'total_no_leidos' => $grupo_no_leidos + $total_privados,
]);
