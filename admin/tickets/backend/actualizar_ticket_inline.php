<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/security.php';

// Verificar sesión y rol
if (empty($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Método no permitido']);
    exit;
}
if (!validarTokenCsrf()) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'La sesión expiró; recarga la página.']);
    exit;
}

$conexionPath = __DIR__ . '/../../../backend/conexion.php';
if (file_exists($conexionPath)) {
    include($conexionPath);
}

date_default_timezone_set('America/Mexico_City');

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$descripcion = isset($_POST['descripcion']) ? $_POST['descripcion'] : null;
$estatus = isset($_POST['estatus']) ? $_POST['estatus'] : null;

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'ID inválido']);
    exit;
}
if ($estatus !== null && !in_array($estatus, ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'], true)) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Estatus no válido']);
    exit;
}
if ($descripcion !== null && mb_strlen((string) $descripcion, 'UTF-8') > 10000) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'La descripción supera el límite permitido']);
    exit;
}

// Obtener ticket actual
$query = "SELECT descripcion, estatus FROM tickets WHERE id = ?";
$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Ticket no encontrado']);
    exit;
}
$ticket = $result->fetch_assoc();
$stmt->close();

$nueva_descripcion = $descripcion !== null ? $descripcion : $ticket['descripcion'];
$nuevo_estatus = $estatus !== null ? $estatus : $ticket['estatus'];
$fecha_actualizacion = date('Y-m-d H:i:s');

$updateQuery = "UPDATE tickets
    SET descripcion = ?, estatus = ?, fecha_modificacion = ?,
        fecha_primera_respuesta = CASE
            WHEN ? = 'En proceso' AND fecha_primera_respuesta IS NULL THEN ?
            ELSE fecha_primera_respuesta
        END,
        fecha_resolucion = CASE
            WHEN ? = 'Finalizado' AND fecha_resolucion IS NULL THEN ?
            WHEN ? IN ('Pendiente','En proceso') THEN NULL
            ELSE fecha_resolucion
        END
    WHERE id = ?";
$stmt = $conexion->prepare($updateQuery);
$stmt->bind_param("ssssssssi", $nueva_descripcion, $nuevo_estatus, $fecha_actualizacion,
    $nuevo_estatus, $fecha_actualizacion, $nuevo_estatus, $fecha_actualizacion, $nuevo_estatus, $id);

if ($stmt->execute()) {
    // Si el estatus cambia a Finalizado o Cerrado
    if ($nuevo_estatus === 'Finalizado' || $nuevo_estatus === 'Cerrado') {
        $stmt_t = $conexion->prepare("SELECT nombre FROM tickets WHERE id = ? LIMIT 1");
        $stmt_t->bind_param("i", $id);
        $stmt_t->execute();
        $q_ticket = $stmt_t->get_result();
        if ($row_t = $q_ticket->fetch_assoc()) {
            $colaborador = $row_t['nombre'];

            $stmt_n = $conexion->prepare("INSERT INTO notificaciones_pendientes (tipo, referencia_id, colaborador_nom) VALUES ('ticket', ?, ?)");
            if ($stmt_n) {
                $stmt_n->bind_param("is", $id, $colaborador);
                $stmt_n->execute();
                $stmt_n->close();
            }
        }
        $stmt_t->close();
    }

    echo json_encode([
        'status' => 'ok',
        'success' => true,
        'id' => $id,
        'estatus' => $nuevo_estatus,
        'fecha_modificacion' => $fecha_actualizacion
    ]);
} else {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Error al actualizar en BD']);
}

$stmt->close();
$conexion->close();
