<?php
session_start();
include '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Verificación de sesión / rol (admin o superadmin) ---
    if (!isset($_SESSION['logueado']) || !esAdminOSuperior()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }

    $ticket_id  = intval($_POST['ticket_id'] ?? 0);
    $descripcion = trim($_POST['descripcion'] ?? '');

    if ($ticket_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    $stmt = $conexion->prepare("
        UPDATE tickets
        SET descripcion = ?,
            fecha_modificacion = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("si", $descripcion, $ticket_id);

    if ($stmt->execute()) {
        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
    }

    $stmt->close();
    $conexion->close();
}
