<?php
session_start();
include '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';

// Verificar que sea admin o superadmin
if (!isset($_SESSION['logueado']) || !esAdminOSuperior()) {
    http_response_code(403);
    echo "Acceso no autorizado";
    exit();
}

// Verificar que lleguen los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen_admin']) && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    $imagen = $_FILES['imagen_admin'];

    // Directorio de uploads
    $targetDir = "../../../uploads/";
    $ext = pathinfo($imagen['name'], PATHINFO_EXTENSION);
    $nombreArchivo = "admin_ticket_" . $ticket_id . "_" . time() . "." . $ext;
    $targetFile = $targetDir . $nombreArchivo;

    // Subir archivo
    if (move_uploaded_file($imagen['tmp_name'], $targetFile)) {
        // Guardar nombre de archivo en DB
        $stmt = $conexion->prepare("UPDATE tickets SET imagen_admin = ? WHERE id = ?");
        $stmt->bind_param("si", $nombreArchivo, $ticket_id);
        if ($stmt->execute()) {
            // Redirigir a la página de mis tickets
            header("Location: ../admin/tickets/index.php?success=1");
            exit();
        } else {
            echo "Error al guardar en la base de datos.";
        }
    } else {
        echo "Error al subir el archivo.";
    }
} else {
    echo "Datos incompletos.";
}
?>