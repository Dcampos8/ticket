<?php
require '../../../backend/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido']);
        exit;
    }

    // Desactivar temporalmente las restricciones de clave foránea
    $conexion->query("SET FOREIGN_KEY_CHECKS=0");

    // Eliminar la entrega
    $stmt = $conexion->prepare("DELETE FROM phone_deliveries WHERE id = ?");
    $stmt->bind_param("i", $id);
    $success = $stmt->execute();

    // Reactivar restricciones
    $conexion->query("SET FOREIGN_KEY_CHECKS=1");

    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Entrega eliminada correctamente']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar la entrega']);
    }
}
?>
