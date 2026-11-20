<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
require_once ROOT_PATH . 'shared/license_secrets.php';
requerirModulo('licencias');

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private');

if (!esAdminOSuperior() || !tieneModulo('licencias')) {
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado.']);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf()) {
    http_response_code(403);
    echo json_encode(['error' => 'La sesión expiró. Recarga la página e intenta de nuevo.']);
    exit;
}

$accion = (string) ($_POST['accion'] ?? 'revelar');
if ($accion === 'buscar') {
    $termino = trim((string) ($_POST['termino'] ?? ''));
    $coincidencias = [];
    if ($termino !== '') {
        $res = $conexion->query('SELECT id, license_key FROM licenses WHERE license_key IS NOT NULL AND license_key <> ""');
        while ($fila = $res->fetch_assoc()) {
            $plain = descifrarClaveLicencia($fila['license_key']);
            if ($plain !== '' && mb_stripos($plain, $termino, 0, 'UTF-8') !== false) $coincidencias[] = (int) $fila['id'];
        }
    }
    echo json_encode(['ids' => $coincidencias]);
    exit;
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
$stmt = $conexion->prepare('SELECT license_key FROM licenses WHERE id = ? LIMIT 1');
if (!$stmt || $id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Registro no válido.']);
    exit;
}
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'No se encontró la clave.']);
    exit;
}

$plain = descifrarClaveLicencia($row['license_key'] ?? '');
if (claveLicenciaEstaCifrada($row['license_key'] ?? '') && $plain === '') {
    http_response_code(503);
    echo json_encode(['error' => 'No se pudo descifrar. Verifica APP_SETTINGS_ENCRYPTION_KEY.']);
    exit;
}
echo json_encode(['license_key' => $plain], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
