<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('auditorias');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
if ($id <= 0) {
    header('Location: auditorias.php');
    exit;
}

$stmt = $conexion->prepare("SELECT ap.id, ap.area, ap.fecha_programada, ap.estado, u.nombre_completo AS responsable_nombre
    FROM auditorias_programadas ap
    LEFT JOIN plan_mantenimiento pm ON ap.area = pm.area
    LEFT JOIN usuarios u ON pm.responsable = u.id
    WHERE ap.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$datos = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$datos) {
    http_response_code(404);
    exit('Auditoría no encontrada.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('La sesión del formulario venció. Recarga e intenta de nuevo.');
    }
    $software = isset($_POST['software_autorizado']) ? 1 : 0;
    $sistema = isset($_POST['sistema_actualizado']) ? 1 : 0;
    $antivirus = isset($_POST['antivirus_instalado']) ? 1 : 0;
    $antivirusActualizado = isset($_POST['antivirus_actualizado']) ? 1 : 0;
    $firewall = isset($_POST['firewall_activo']) ? 1 : 0;
    $gestion = isset($_POST['gestion_archivos']) ? 1 : 0;
    $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
    $revisadoPor = trim((string) ($_POST['revisado_por'] ?? ''));
    if ($revisadoPor === '') {
        http_response_code(422);
        exit('Indica quién realizó la revisión.');
    }

    $stmt = $conexion->prepare("INSERT INTO auditorias_resultados
        (id_auditoria, software_autorizado, sistema_actualizado, antivirus_instalado, antivirus_actualizado, firewall_activo, gestion_archivos, observaciones, revisado_por, fecha_revision)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param('iiiiiiiss', $id, $software, $sistema, $antivirus, $antivirusActualizado, $firewall, $gestion, $observaciones, $revisadoPor);
    if (!$stmt->execute()) {
        error_log('No se pudo guardar resultado de auditoría: ' . $stmt->error);
        $stmt->close();
        http_response_code(500);
        exit('No se pudo guardar la auditoría.');
    }
    $stmt->close();

    $stmt = $conexion->prepare("UPDATE auditorias_programadas SET estado = 'Realizada' WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: auditorias.php?guardado=1');
    exit;
}

include ROOT_PATH . 'admin/menu.php';
?>
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__auditorias__auditar.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__auditorias__auditar.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="audit-card">
    <div class="audit-header">
        <h2>Checklist de Auditoría</h2>
    </div>

    <div class="info-box">
        <p><b>Área:</b> <?= htmlspecialchars($datos['area']) ?></p>
        <p><b>Responsable:</b> <?= htmlspecialchars($datos['responsable_nombre'] ?? 'Sin asignar') ?></p>
        <p><b>Fecha Programada:</b> <?= htmlspecialchars((string) $datos['fecha_programada'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <form method="POST" class="checklist-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        
        <label class="check-item">
            <input type="checkbox" name="software_autorizado"> Software autorizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="sistema_actualizado"> Sistema actualizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="antivirus_instalado"> Antivirus instalado
        </label>
        <label class="check-item">
            <input type="checkbox" name="antivirus_actualizado"> Antivirus actualizado
        </label>
        <label class="check-item">
            <input type="checkbox" name="firewall_activo"> Firewall activo
        </label>
        <label class="check-item">
            <input type="checkbox" name="gestion_archivos"> Gestión correcta de archivos
        </label>

        <textarea name="observaciones" placeholder="Escribe aquí las observaciones..."></textarea>

        <input type="text" name="revisado_por" class="input-rev" placeholder="Nombre de quien revisa" required>

        <button type="submit" name="guardar" class="btn-save">Guardar Auditoría</button>
    </form>
</div>
