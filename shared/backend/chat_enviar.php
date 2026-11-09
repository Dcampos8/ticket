<?php
session_start();
require('../../backend/conexion.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$id_usuario = (int) $_SESSION['id'];
$nombre = $_SESSION['nombre_completo'] ?: $_SESSION['usuario'];

$tipo = $_POST['tipo'] ?? 'grupo';
$destinatario_id = isset($_POST['destinatario_id']) ? intval($_POST['destinatario_id']) : null;
$mensaje = trim($_POST['mensaje'] ?? '');

if (!in_array($tipo, ['grupo', 'privado'], true) || $mensaje === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Mensaje vacío o tipo inválido.']);
    exit;
}

if (mb_strlen($mensaje) > 2000) {
    $mensaje = mb_substr($mensaje, 0, 2000);
}

if ($tipo === 'privado') {
    if (!$destinatario_id || $destinatario_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Falta el destinatario.']);
        exit;
    }
    $stmt = mysqli_prepare($conexion, "INSERT INTO chat_mensajes (tipo, remitente_id, remitente_nombre, destinatario_id, mensaje) VALUES ('privado', ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "isis", $id_usuario, $nombre, $destinatario_id, $mensaje);
} else {
    $stmt = mysqli_prepare($conexion, "INSERT INTO chat_mensajes (tipo, remitente_id, remitente_nombre, destinatario_id, mensaje) VALUES ('grupo', ?, ?, NULL, ?)");
    mysqli_stmt_bind_param($stmt, "iss", $id_usuario, $nombre, $mensaje);
}

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'id' => mysqli_insert_id($conexion)]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el mensaje.']);
}
