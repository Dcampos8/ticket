<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logueado'])) {
    echo json_encode(['status' => 'error', 'msg' => 'No autorizado']);
    exit;
}

if (!isset($_POST['ticket_id']) || empty($_POST['mensaje'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Datos incompletos']);
    exit;
}

require '../../../backend/conexion.php';

$ticket_id = intval($_POST['ticket_id']);
$usuario = $_SESSION['usuario'];
$mensaje = trim($_POST['mensaje']);

$stmt = $conexion->prepare("INSERT INTO ticket_chat (ticket_id, usuario, mensaje) VALUES (?, ?, ?)");
$stmt->bind_param("iss", $ticket_id, $usuario, $mensaje);

if ($stmt->execute()) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'msg' => 'Error al guardar mensaje']);
}

$stmt->close();
$conexion->close();
?>
