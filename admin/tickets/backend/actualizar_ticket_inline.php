<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Cargar permisos de forma segura
$permisosPath = __DIR__ . '/../../../shared/permisos.php';
if (file_exists($permisosPath)) {
    require_once($permisosPath);
}

// Verificar sesión y rol
if (!isset($_SESSION['logueado']) || (function_exists('esAdminOSuperior') && !esAdminOSuperior())) {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'success' => false, 'message' => 'Método no permitido']);
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

$updateQuery = "UPDATE tickets SET descripcion = ?, estatus = ?, fecha_modificacion = ? WHERE id = ?";
$stmt = $conexion->prepare($updateQuery);
$stmt->bind_param("sssi", $nueva_descripcion, $nuevo_estatus, $fecha_actualizacion, $id);

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