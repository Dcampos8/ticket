<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['logueado'])) {
    echo json_encode([]);
    exit;
}

require '../../../backend/conexion.php';

$ticket_id = isset($_GET['ticket_id']) ? intval($_GET['ticket_id']) : 0;

$result = $conexion->query("
    SELECT usuario, mensaje, fecha 
    FROM ticket_chat 
    WHERE ticket_id = $ticket_id 
    ORDER BY fecha ASC
");

$mensajes = [];
while ($fila = $result->fetch_assoc()) {
    $mensajes[] = $fila;
}

echo json_encode($mensajes);
$conexion->close();
?>
