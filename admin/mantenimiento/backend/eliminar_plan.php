<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
requerirModulo('mantenimiento');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}

$tokenSesion = $_SESSION['csrf_token'] ?? '';
$tokenEnviado = $_POST['csrf_token'] ?? '';
if ($tokenSesion === '' || !is_string($tokenEnviado) || !hash_equals($tokenSesion, $tokenEnviado)) {
    http_response_code(403);
    exit('La sesión del formulario venció. Recarga la página e intenta de nuevo.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    http_response_code(422);
    exit('ID de plan inválido.');
}

$stmtCheck = $conexion->prepare('SELECT COUNT(*) AS total FROM mantenimientos_realizados mr JOIN programacion_mantenimiento pm ON mr.id_programacion = pm.id WHERE pm.id_plan = ?');
$stmtCheck->bind_param('i', $id);
$stmtCheck->execute();
$tieneHistorial = (int)$stmtCheck->get_result()->fetch_assoc()['total'] > 0;
$stmtCheck->close();

if ($tieneHistorial) {
    $_SESSION['flash_msg'] = 'No se puede eliminar el plan porque tiene mantenimientos realizados.';
    header('Location: ../plan_mantenimiento.php');
    exit;
}

$conexion->begin_transaction();
try {
    $stmtProgramacion = $conexion->prepare('DELETE FROM programacion_mantenimiento WHERE id_plan = ?');
    $stmtProgramacion->bind_param('i', $id);
    $stmtProgramacion->execute();
    $stmtProgramacion->close();

    $stmtPlan = $conexion->prepare('DELETE FROM plan_mantenimiento WHERE id = ?');
    $stmtPlan->bind_param('i', $id);
    $stmtPlan->execute();
    $stmtPlan->close();

    $conexion->commit();
    $_SESSION['flash_msg'] = 'Plan de mantenimiento eliminado.';
} catch (Throwable $e) {
    $conexion->rollback();
    error_log('Error al eliminar plan de mantenimiento: ' . $e->getMessage());
    $_SESSION['flash_msg'] = 'No se pudo eliminar el plan de mantenimiento.';
}

header('Location: ../plan_mantenimiento.php');
exit;
