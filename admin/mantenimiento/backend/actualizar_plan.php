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
$idEquipo = filter_input(INPUT_POST, 'id_equipo', FILTER_VALIDATE_INT);
$responsable = filter_input(INPUT_POST, 'responsable', FILTER_VALIDATE_INT);
$anio = filter_input(INPUT_POST, 'anio', FILTER_VALIDATE_INT);
$area = trim((string)($_POST['area'] ?? ''));
$tipo = (string)($_POST['tipo'] ?? '');
$frecuencia = (string)($_POST['frecuencia'] ?? '');

$tiposPermitidos = ['Preventivo', 'Correctivo'];
$frecuenciasPermitidas = ['Mensual', 'Trimestral', 'Semestral', 'Anual'];
if (!$id || !$idEquipo || !$responsable || !$anio || $area === '' ||
    !in_array($tipo, $tiposPermitidos, true) ||
    !in_array($frecuencia, $frecuenciasPermitidas, true) ||
    $anio < 2000 || $anio > 2100) {
    http_response_code(422);
    exit('Revisa los datos del plan e intenta de nuevo.');
}

$stmt = $conexion->prepare('UPDATE plan_mantenimiento SET id_equipo = ?, area = ?, tipo = ?, frecuencia = ?, responsable = ?, anio = ? WHERE id = ?');
$stmt->bind_param('isssiii', $idEquipo, $area, $tipo, $frecuencia, $responsable, $anio, $id);
$stmt->execute();
$stmt->close();

$_SESSION['flash_msg'] = 'Plan de mantenimiento actualizado.';
header('Location: ../plan_mantenimiento.php');
exit;
