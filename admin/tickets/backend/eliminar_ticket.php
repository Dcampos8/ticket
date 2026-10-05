<?php
session_start();

require('../../../backend/conexion.php');
require_once '../../../shared/permisos.php';

if (!isset($_SESSION['logueado']) || !esAdminOSuperior()) {
    echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ticket_id'])) {


    if ($conexion->connect_error) {
        echo json_encode(['status' => 'error', 'message' => 'Error conexión']);
        exit();
    }

    $ticket_id = intval($_POST['ticket_id']);

    $stmt = $conexion->prepare("DELETE FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo eliminar']);
    }

    $stmt->close();
    $conexion->close();
    exit();
}

echo json_encode(['status' => 'error', 'message' => 'Petición inválida']);
exit();