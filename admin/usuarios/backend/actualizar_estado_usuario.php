<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'backend/conexion.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['logueado']) || !esAdminOSuperior()) {
    http_response_code(403);
    exit('Acceso denegado.');
}
if (!esSuperAdmin() && !tieneModulo('usuarios')) {
    http_response_code(403);
    exit('No autorizado.');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf()) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$activoPost = (string) ($_POST['activo'] ?? '');
if (!$id || !in_array($activoPost, ['0', '1'], true)) {
    header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=estado_error');
    exit;
}
$activo = (int) $activoPost;
$idActor = (int) ($_SESSION['id'] ?? 0);

// Evita que un administrador quite su propio acceso por accidente.
if ($id === $idActor && $activo === 0) {
    header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=estado_error');
    exit;
}

$stmtObjetivo = $conexion->prepare('SELECT rol FROM usuarios WHERE id = ? LIMIT 1');
if (!$stmtObjetivo) {
    header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=estado_error');
    exit;
}
$stmtObjetivo->bind_param('i', $id);
$stmtObjetivo->execute();
$objetivo = $stmtObjetivo->get_result()->fetch_assoc();
$stmtObjetivo->close();
if (!$objetivo) {
    header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=estado_error');
    exit;
}
if (strtolower(trim((string) ($objetivo['rol'] ?? ''))) === 'superadmin' && !esSuperAdmin()) {
    http_response_code(403);
    exit('No tienes permiso para administrar esta cuenta.');
}

$stmt = $conexion->prepare('UPDATE usuarios SET activo = ? WHERE id = ?');
if (!$stmt) {
    header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=estado_error');
    exit;
}
$stmt->bind_param('ii', $activo, $id);
$ok = $stmt->execute();
$stmt->close();
$conexion->close();

header('Location: ' . BASE_URL . 'admin/usuarios/index.php?msg=' . ($ok ? 'estado_actualizado' : 'estado_error'));
exit;
