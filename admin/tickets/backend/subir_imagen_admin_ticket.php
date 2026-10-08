<?php
session_start();
include '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';
require_once '../../../shared/security.php';

// Verificar que sea admin o superadmin
if (!isset($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
    http_response_code(403);
    echo "Acceso no autorizado";
    exit();
}
if (!validarTokenCsrf()) {
    http_response_code(403);
    exit('La sesión expiró. Recarga la página e intenta de nuevo.');
}

// Verificar que lleguen los datos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['imagen_admin']) && isset($_POST['ticket_id'])) {
    $ticket_id = intval($_POST['ticket_id']);
    if ($ticket_id <= 0) {
        http_response_code(422);
        exit('Ticket no válido.');
    }
    $imagen = $_FILES['imagen_admin'];

    // Directorio de uploads
    $targetDir = __DIR__ . "/../../../uploads/";
    $infoImagen = is_uploaded_file($imagen['tmp_name'] ?? '') ? @getimagesize($imagen['tmp_name']) : false;
    $tamanioImagen = isset($imagen['tmp_name']) ? @filesize($imagen['tmp_name']) : false;
    $extensionesPermitidas = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $mime = is_array($infoImagen) ? ($infoImagen['mime'] ?? '') : '';
    if (($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || $tamanioImagen === false || $tamanioImagen < 1 || $tamanioImagen > 5 * 1024 * 1024
        || !isset($extensionesPermitidas[$mime])
        || ((int) ($infoImagen[0] ?? 0) * (int) ($infoImagen[1] ?? 0)) > 30000000) {
        http_response_code(422);
        exit('La imagen debe ser JPG, PNG o WebP y no superar 5 MB.');
    }
    $nombreArchivo = 'admin_ticket_' . $ticket_id . '_' . bin2hex(random_bytes(12)) . '.' . $extensionesPermitidas[$mime];
    $targetFile = $targetDir . $nombreArchivo;

    // Subir archivo
    if (move_uploaded_file($imagen['tmp_name'], $targetFile)) {
        // Guardar nombre de archivo en DB
        $stmt = $conexion->prepare("UPDATE tickets SET imagen_admin = ? WHERE id = ?");
        $stmt->bind_param("si", $nombreArchivo, $ticket_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows === 0) {
                @unlink($targetFile);
                http_response_code(404);
                exit('Ticket no encontrado.');
            }
            header("Location: ../index.php?success=1");
            exit();
        } else {
            @unlink($targetFile);
            http_response_code(500);
            echo "No se pudo guardar el adjunto.";
        }
    } else {
        echo "Error al subir el archivo.";
    }
} else {
    echo "Datos incompletos.";
}
?>
