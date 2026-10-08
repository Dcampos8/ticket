<?php
session_start();
include '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';
require_once '../../../shared/security.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- Verificación de sesión / rol (admin o superadmin) ---
    if (!isset($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'No autorizado']);
        exit;
    }
    if (!validarTokenCsrf()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'La sesión expiró; recarga la página.']);
        exit;
    }

    $ticket_id  = intval($_POST['ticket_id'] ?? 0);
    $comentario = trim($_POST['comentario_admin'] ?? '');
    if (mb_strlen($comentario, 'UTF-8') > 10000) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'El comentario supera el límite permitido']);
        exit;
    }

    // Identidad del admin que está comentando (confirmado en backend/validar_login.php:
    // $_SESSION['nombre_completo'] y $_SESSION['usuario'] se llenan al hacer login).
    $admin = $_SESSION['nombre_completo'] ?? ($_SESSION['usuario'] ?? 'admin');

    if ($ticket_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'ID inválido']);
        exit;
    }

    // Leemos el comentario actual para comparar y no duplicar historial
    // cuando el textarea pierde foco sin cambios (evento blur).
    $stmtCheck = $conexion->prepare("SELECT comentario_admin FROM tickets WHERE id = ?");
    $stmtCheck->bind_param("i", $ticket_id);
    $stmtCheck->execute();
    $actual = $stmtCheck->get_result()->fetch_assoc();
    $stmtCheck->close();

    $comentarioAnterior = trim((string)($actual['comentario_admin'] ?? ''));
    $esPrimeraRespuesta = ($comentario !== '' && $comentario !== $comentarioAnterior) ? 1 : 0;

    $stmt = $conexion->prepare("
        UPDATE tickets
        SET comentario_admin = ?,
            fecha_modificacion = NOW(),
            fecha_primera_respuesta = CASE
                WHEN ? = 1 AND fecha_primera_respuesta IS NULL THEN NOW()
                ELSE fecha_primera_respuesta
            END
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $comentario, $esPrimeraRespuesta, $ticket_id);

    if ($stmt->execute()) {

        // Solo registramos en el historial si hay texto y realmente cambió.
        if ($comentario !== '' && $comentario !== $comentarioAnterior) {
            $stmtHist = $conexion->prepare("
                INSERT INTO ticket_comentarios_historial (ticket_id, admin_usuario, comentario)
                VALUES (?, ?, ?)
            ");
            $stmtHist->bind_param("iss", $ticket_id, $admin, $comentario);
            $stmtHist->execute();
            $stmtHist->close();
        }

        echo json_encode(['status' => 'ok']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No se pudo actualizar']);
    }

    $stmt->close();
    $conexion->close();
}
