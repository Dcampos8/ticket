<?php
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
require_once ROOT_PATH . 'shared/license_secrets.php';
requerirModulo('licencias');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token CSRF de la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function fechaLicenciaValida(?string $fecha): bool
{
    if ($fecha === null || $fecha === '') return true;
    $obj = DateTime::createFromFormat('!Y-m-d', $fecha);
    return $obj !== false && $obj->format('Y-m-d') === $fecha;
}

/* =========================================================
   1. PROCESAMIENTO POST (Guardar, Editar, Eliminar)
   Se ejecuta ANTES de cargar el menú o cualquier HTML
   ========================================================= */

// --- ELIMINAR LICENCIA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido.";
    } else {
        $id = intval($_POST['eliminar']);
        if ($id > 0) {
            $stmt = $conexion->prepare("DELETE FROM licenses WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $_SESSION['flash_msg'] = "🗑️ Registro eliminado correctamente.";
            } else {
                $_SESSION['flash_msg'] = "❌ Error al eliminar el registro.";
            }
            $stmt->close();
        }
    }
    header("Location: index.php");
    exit;
}

// --- GUARDAR / CREAR LICENCIA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_licencia'])) {
    $service_name    = trim($_POST['service_name'] ?? '');
    $license_type    = trim($_POST['license_type'] ?? 'Servicio');
    $license_mode    = trim($_POST['license_mode'] ?? 'Perpetua');
    $version_edition = trim($_POST['version_edition'] ?? '');
    $quantity        = min(100000, max(1, (int) ($_POST['quantity'] ?? 1)));
    $assigned_to     = trim($_POST['assigned_to'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $purchase_date   = $purchase_date !== '' ? $purchase_date : null;
    $provider        = trim($_POST['provider'] ?? '');
    $license_key     = cifrarClaveLicencia(trim((string) ($_POST['license_key'] ?? '')));
    $cost            = floatval($_POST['cost'] ?? 0);
    $currency        = trim($_POST['currency'] ?? 'MXN');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $expiration_date = $expiration_date !== '' ? $expiration_date : null;
    $notes           = trim($_POST['notes'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido.";
    } elseif (empty($service_name) || !in_array($license_type, ['Servicio', 'Software', 'Sistema operativo', 'Servidor', 'Otro'], true)
        || !in_array($license_mode, ['Perpetua', 'OEM', 'Suscripción', 'Licencia por volumen', 'Freemium', 'Otro'], true)
        || !in_array($currency, ['MXN', 'USD'], true)
        || !fechaLicenciaValida($purchase_date) || !fechaLicenciaValida($expiration_date)
        || $license_key === null) {
        $_SESSION['flash_msg'] = $license_key === null
            ? '❌ Configura APP_SETTINGS_ENCRYPTION_KEY con al menos 32 caracteres para guardar claves de licencia.'
            : '❌ Revisa el nombre, el tipo y la modalidad de la licencia.';
    } else {
        $stmt = $conexion->prepare("INSERT INTO licenses (service_name, license_type, license_mode, version_edition, quantity, assigned_to, purchase_date, provider, license_key, cost, currency, expiration_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssissssdsss", $service_name, $license_type, $license_mode, $version_edition, $quantity, $assigned_to, $purchase_date, $provider, $license_key, $cost, $currency, $expiration_date, $notes);

        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "✅ Licencia guardada exitosamente: $service_name";
        } else {
            $_SESSION['flash_msg'] = "❌ Error en base de datos: " . $conexion->error;
        }
        $stmt->close();
    }
    header("Location: index.php");
    exit;
}

// --- EDITAR / ACTUALIZAR LICENCIA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_licencia'])) {
    $id              = intval($_POST['id'] ?? 0);
    $service_name    = trim($_POST['service_name'] ?? '');
    $license_type    = trim($_POST['license_type'] ?? 'Servicio');
    $license_mode    = trim($_POST['license_mode'] ?? 'Perpetua');
    $version_edition = trim($_POST['version_edition'] ?? '');
    $quantity        = min(100000, max(1, (int) ($_POST['quantity'] ?? 1)));
    $assigned_to     = trim($_POST['assigned_to'] ?? '');
    $purchase_date   = trim($_POST['purchase_date'] ?? '');
    $purchase_date   = $purchase_date !== '' ? $purchase_date : null;
    $provider        = trim($_POST['provider'] ?? '');
    $cost            = floatval($_POST['cost'] ?? 0);
    $currency        = trim($_POST['currency'] ?? 'MXN');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $expiration_date = $expiration_date !== '' ? $expiration_date : null;
    $notes           = trim($_POST['notes'] ?? '');
    $keySubmitted = trim((string) ($_POST['license_key'] ?? ''));
    $clearKey = isset($_POST['clear_license_key']);
    $encryptedKey = $keySubmitted !== '' ? cifrarClaveLicencia($keySubmitted) : '';

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido.";
    } elseif ($id <= 0 || empty($service_name) || !in_array($license_type, ['Servicio', 'Software', 'Sistema operativo', 'Servidor', 'Otro'], true)
        || !in_array($license_mode, ['Perpetua', 'OEM', 'Suscripción', 'Licencia por volumen', 'Freemium', 'Otro'], true)
        || !in_array($currency, ['MXN', 'USD'], true)
        || !fechaLicenciaValida($purchase_date) || !fechaLicenciaValida($expiration_date)
        || $encryptedKey === null) {
        $_SESSION['flash_msg'] = $encryptedKey === null
            ? '❌ Configura APP_SETTINGS_ENCRYPTION_KEY con al menos 32 caracteres para guardar claves de licencia.'
            : '❌ Revisa los datos, fechas, moneda y modalidad del registro.';
    } else {
        $keyAction = $clearKey ? '__CLEAR__' : $encryptedKey;
        $stmt = $conexion->prepare("UPDATE licenses SET service_name = ?, license_type = ?, license_mode = ?, version_edition = ?, quantity = ?, assigned_to = ?, purchase_date = ?, provider = ?, license_key = IF(? = '__CLEAR__', '', IF(? = '', license_key, ?)), cost = ?, currency = ?, expiration_date = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("ssssissssssdsssi", $service_name, $license_type, $license_mode, $version_edition, $quantity, $assigned_to, $purchase_date, $provider, $keyAction, $keyAction, $keyAction, $cost, $currency, $expiration_date, $notes, $id);

        if ($stmt->execute()) {
            $_SESSION['flash_msg'] = "✅ Licencia actualizada correctamente: $service_name";
        } else {
            $_SESSION['flash_msg'] = "❌ Error al actualizar: " . $conexion->error;
        }
        $stmt->close();
    }
    header("Location: index.php");
    exit;
}

/* =========================================================
   2. CARGA DE MENÚ Y SALIDA HTML
   ========================================================= */
include (ROOT_PATH . 'admin/menu.php');

$msg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);

function claseAlerta($msg) {
    if (str_starts_with($msg, '✅')) return 'alert-success';
    if (str_starts_with($msg, '❌')) return 'alert-danger';
    if (str_starts_with($msg, '🗑️')) return 'alert-secondary';
    return 'alert-info';
}

/* =========================
   CONSULTA REGISTROS
   ========================= */
$result = $conexion->query("SELECT *, DATEDIFF(expiration_date, CURDATE()) AS dias_restantes FROM licenses ORDER BY expiration_date IS NULL ASC, expiration_date ASC, service_name ASC");
$registros = [];
$clavesLegadasSinCifrar = false;
$cifradoLicenciasDisponible = claveCifradoIntegraciones() !== null;
$actualizarClave = $conexion->prepare('UPDATE licenses SET license_key = ? WHERE id = ?');
while ($row = $result->fetch_assoc()) {
    $storedKey = (string) ($row['license_key'] ?? '');
    if ($storedKey !== '' && !claveLicenciaEstaCifrada($storedKey)) {
        $encrypted = cifrarClaveLicencia($storedKey);
        if ($encrypted !== null) {
            $row['license_key'] = $encrypted;
            $updateId = (int) $row['id'];
            $actualizarClave->bind_param('si', $encrypted, $updateId);
            $actualizarClave->execute();
        } else {
            $clavesLegadasSinCifrar = true;
        }
    }
    $row['license_key_mask'] = mascaraClaveLicencia($row['license_key'] ?? '');
    $registros[] = $row;
}
$actualizarClave->close();

/* =========================
   ESTADÍSTICAS RÁPIDAS
   ========================= */
$stats = $conexion->query("
    SELECT
        COUNT(*) AS total_licencias,
        COALESCE(SUM(CASE WHEN expiration_date IS NULL THEN 1 ELSE 0 END), 0) AS sin_vencimiento,
        COALESCE(SUM(CASE WHEN expiration_date < CURDATE() THEN 1 ELSE 0 END), 0) AS vencidas,
        COALESCE(SUM(CASE WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS por_vencer
    FROM licenses
")->fetch_assoc();
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__vigencias__index.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__vigencias__index.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container-fluid mt-4 lic-page">

    <!-- Tarjetas de Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="lic-stat">
                <div class="lic-stat-icon"><i class="fa-solid fa-key"></i></div>
                <div>
                    <p class="lic-stat-label">Total Registrados</p>
                    <div class="lic-stat-value"><?= number_format($stats['total_licencias']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="lic-stat">
                <div class="lic-stat-icon" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <p class="lic-stat-label">Por Vencer (30 Días)</p>
                    <div class="lic-stat-value"><?= number_format($stats['por_vencer']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="lic-stat">
                <div class="lic-stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
            <p class="lic-stat-label">Vencidas</p>
                    <div class="lic-stat-value"><?= number_format($stats['vencidas']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="lic-stat">
                <div class="lic-stat-icon" style="background:#e0f2fe; color:#0369a1;"><i class="fa-solid fa-infinity"></i></div>
                <div><p class="lic-stat-label">Sin vencimiento</p><div class="lic-stat-value"><?= number_format($stats['sin_vencimiento']) ?></div></div>
            </div>
        </div>
    </div>

    <!-- Card Nuevo Registro -->
    <div class="lic-card mb-4">
        <div class="lic-card-header">
            <h3><span class="lic-card-icon"><i class="fa-solid fa-server"></i></span> Registrar Licencia o Servidor</h3>
        </div>
        <div class="lic-card-body">
            <?php if ($msg): ?><div class="alert <?= claseAlerta($msg) ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <?php if (!$cifradoLicenciasDisponible): ?><div class="alert alert-warning">Configura <code>APP_SETTINGS_ENCRYPTION_KEY</code> con al menos 32 caracteres en <code>.env</code> para guardar y revelar claves cifradas.</div><?php endif; ?>
            <?php if ($clavesLegadasSinCifrar && $cifradoLicenciasDisponible): ?><div class="alert alert-warning">Hay claves antiguas sin cifrar que no se pudieron migrar automáticamente. Revisa OpenSSL y los permisos de base de datos.</div><?php endif; ?>

            <form method="POST" id="formLicencia">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Servicio / Servidor / Software</label>
                        <input type="text" name="service_name" class="form-control" placeholder="Ej. VPS DigitalOcean, Office 365" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Tipo de licencia</label>
                        <select name="license_type" class="form-select">
                            <option>Servicio</option><option>Software</option><option>Sistema operativo</option><option>Servidor</option><option>Otro</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Modalidad</label>
                        <select name="license_mode" class="form-select">
                            <option>Perpetua</option><option>OEM</option><option>Suscripción</option><option>Licencia por volumen</option><option>Freemium</option><option>Otro</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Proveedor / Dominio</label>
                        <input type="text" name="provider" class="form-control" placeholder="Ej. Namecheap, Microsoft, AWS">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Versión / edición</label>
                        <input type="text" name="version_edition" class="form-control" placeholder="Ej. Windows 11 Pro">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Clave / serial / referencia</label>
                        <input type="password" name="license_key" class="form-control" placeholder="Se guardará cifrada" autocomplete="new-password">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cantidad</label>
                        <input type="number" name="quantity" class="form-control" min="1" max="100000" value="1" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Asignada a / equipo</label>
                        <input type="text" name="assigned_to" class="form-control" placeholder="Ej. PC-VENTAS-01 o María López">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Costo total</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" name="cost" class="form-control" value="0.00" min="0">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda</label>
                        <select name="currency" class="form-select fw-bold">
                            <option value="MXN">Peso Mexicano (MXN)</option>
                            <option value="USD">Dólar Americano (USD)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de compra</label>
                        <input type="date" name="purchase_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de vencimiento (opcional)</label>
                        <input type="date" name="expiration_date" class="form-control">
                        <small class="text-muted">Déjala vacía si es perpetua o no tiene vencimiento.</small>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Notas / Observaciones</label>
                        <input type="text" name="notes" class="form-control" placeholder="Ej. Renovación automática activa">
                    </div>
                </div>

                <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                    <button type="submit" name="guardar_licencia" class="btn btn-lic-primary">
                        <i class="fa fa-save me-1"></i> Guardar Registro
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Card Historial y Estado -->
    <div class="lic-card">
        <div class="lic-card-header">
            <h3><span class="lic-card-icon"><i class="fa-solid fa-list-check"></i></span> Estado de Licencias y Vigencias</h3>
            <div class="lic-table-tools">
                <input type="search" id="buscarLicencia" class="form-control form-control-sm lic-search" placeholder="Buscar en todos los datos...">
                <select id="filtrarTipo" class="form-select form-select-sm lic-filter" aria-label="Filtrar por tipo">
                    <option value="">Todos los tipos</option><option>Servicio</option><option>Software</option><option>Sistema operativo</option><option>Servidor</option><option>Otro</option>
                </select>
                <select id="filtrarEstado" class="form-select form-select-sm lic-filter" aria-label="Filtrar por estado">
                    <option value="">Todos los estados</option><option value="Sin vencimiento">Sin vencimiento</option><option value="Vigente">Vigentes</option><option value="Por vencer">Por vencer</option><option value="Vencida">Vencidas</option>
                </select>
            </div>
        </div>
        <div class="lic-card-body">
            <?php if (count($registros) === 0): ?>
                <div class="lic-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    Aún no hay licencias o servicios registrados.<br>Agrega el primero desde el formulario superior.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaLicencias" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Activo</th>
                            <th>Tipo</th>
                            <th>Proveedor</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
        <?php foreach ($registros as $row): ?>
                        <?php
                            $dias = (int)$row['dias_restantes'];
                            if ($row['expiration_date'] === null || $row['expiration_date'] === '') {
                                $badgeClass = 'badge-status-ok';
                                $statusText = 'Sin vencimiento';
                                $statusGroup = 'Sin vencimiento';
                            } elseif ($dias < 0) {
                                $badgeClass = 'badge-status-danger';
                                $statusText = 'Vencida (' . abs($dias) . ' días)';
                                $statusGroup = 'Vencida';
                            } elseif ($dias <= 30) {
                                $badgeClass = 'badge-status-warn';
                                $statusText = 'Por vencer (' . $dias . ' días)';
                                $statusGroup = 'Por vencer';
                            } else {
                                $badgeClass = 'badge-status-ok';
                                $statusText = 'Vigente (' . $dias . ' días)';
                                $statusGroup = 'Vigente';
                            }
                        ?>
                        <?php $searchIndex = implode(' ', [$row['service_name'], $row['license_type'] ?? '', $row['license_mode'] ?? 'Otro', $row['version_edition'] ?? '', $row['provider'] ?? '', $row['assigned_to'] ?? '', $row['quantity'] ?? 1, $row['cost'], $row['currency'], $row['purchase_date'] ?? '', $row['expiration_date'] ? date('d/m/Y', strtotime($row['expiration_date'])) : '', $statusText, $row['notes'] ?? '', $row['license_key_mask'] ?? '']); ?>
                        <tr data-id="<?= (int) $row['id'] ?>" data-search="<?= htmlspecialchars(mb_strtolower($searchIndex, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>" data-type="<?= htmlspecialchars($row['license_type'] ?? 'Servicio', ENT_QUOTES, 'UTF-8') ?>" data-status-group="<?= htmlspecialchars($statusGroup, ENT_QUOTES, 'UTF-8') ?>">
                            <td class="fw-bold lic-cell-wrap"><?= htmlspecialchars($row['service_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['license_type'] ?? 'Servicio', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="lic-cell-wrap"><?= htmlspecialchars($row['provider'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= $row['expiration_date'] ? date('d/m/Y', strtotime($row['expiration_date'])) : '—' ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-primary btn-detail"
                                    data-id="<?= $row['id'] ?>"
                                    data-service_name="<?= htmlspecialchars($row['service_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-license_type="<?= htmlspecialchars($row['license_type'] ?? 'Servicio', ENT_QUOTES, 'UTF-8') ?>"
                                    data-provider="<?= htmlspecialchars($row['provider'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-license_key_mask="<?= htmlspecialchars($row['license_key_mask'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-cost="<?= $row['cost'] ?>"
                                    data-currency="<?= $row['currency'] ?>"
                                    data-expiration_date="<?= $row['expiration_date'] ?>"
                                    data-license_mode="<?= htmlspecialchars($row['license_mode'] ?? 'Otro', ENT_QUOTES, 'UTF-8') ?>"
                                    data-version_edition="<?= htmlspecialchars($row['version_edition'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-quantity="<?= (int) ($row['quantity'] ?? 1) ?>"
                                    data-assigned_to="<?= htmlspecialchars($row['assigned_to'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-purchase_date="<?= $row['purchase_date'] ?>"
                                    data-notes="<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-status="<?= htmlspecialchars($statusText, ENT_QUOTES, 'UTF-8') ?>"
                                    data-status_class="<?= $badgeClass ?>">
                                    <i class="fa-regular fa-eye" aria-hidden="true"></i> <span>Ver datos</span>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="sinResultadosLicencias" hidden><td colspan="6" class="text-center text-muted py-4">No hay registros que coincidan con la búsqueda o los filtros.</td></tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal de datos completos -->
<div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content lic-page">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-info me-2 text-primary"></i>Datos del registro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <dl class="lic-detail-list">
                    <div><dt>Activo</dt><dd id="detail_service_name"></dd></div>
                    <div><dt>Tipo</dt><dd id="detail_license_type"></dd></div>
                    <div><dt>Modalidad</dt><dd id="detail_license_mode"></dd></div>
                    <div><dt>Versión / edición</dt><dd id="detail_version_edition"></dd></div>
                    <div><dt>Cantidad</dt><dd id="detail_quantity"></dd></div>
                    <div><dt>Asignada a / equipo</dt><dd id="detail_assigned_to"></dd></div>
                    <div><dt>Proveedor / dominio</dt><dd id="detail_provider"></dd></div>
                    <div><dt>Clave / serial / referencia</dt><dd><span id="detail_license_key"></span> <button type="button" id="revelarClave" class="btn btn-sm btn-outline-secondary ms-2">Mostrar</button></dd></div>
                    <div><dt>Costo total</dt><dd id="detail_cost"></dd></div>
                    <div><dt>Fecha de compra</dt><dd id="detail_purchase_date"></dd></div>
                    <div><dt>Vencimiento</dt><dd id="detail_expiration_date"></dd></div>
                    <div><dt>Estado</dt><dd><span id="detail_status" class="badge"></span></dd></div>
                    <div><dt>Notas</dt><dd id="detail_notes"></dd></div>
                </dl>
            </div>
            <div class="modal-footer justify-content-between">
                <form method="POST" id="formEliminarLicencia" onsubmit="return confirm('¿Deseas eliminar este registro?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" name="eliminar" id="detail_delete" class="btn btn-outline-danger"><i class="fa fa-trash me-1"></i> Eliminar</button>
                </form>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" id="detail_edit" class="btn btn-lic-primary"><i class="fa fa-pen me-1"></i> Editar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Editar Registro -->
<div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content lic-page">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-primary"></i>Editar Licencia o Vigencia</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="id" id="edit_id">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Servicio / Servidor / Software</label>
                            <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tipo de licencia</label>
                            <select name="license_type" id="edit_license_type" class="form-select">
                                <option>Servicio</option><option>Software</option><option>Sistema operativo</option><option>Servidor</option><option>Otro</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Modalidad</label>
                            <select name="license_mode" id="edit_license_mode" class="form-select"><option>Perpetua</option><option>OEM</option><option>Suscripción</option><option>Licencia por volumen</option><option>Freemium</option><option>Otro</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proveedor / Dominio</label>
                            <input type="text" name="provider" id="edit_provider" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Versión / edición</label>
                            <input type="text" name="version_edition" id="edit_version_edition" class="form-control">
                        </div>
                        <div class="col-md-9">
                            <label class="form-label">Asignada a / equipo</label>
                            <input type="text" name="assigned_to" id="edit_assigned_to" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1" max="100000" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Cambiar clave / serial / referencia</label>
                            <input type="password" name="license_key" id="edit_license_key" class="form-control" placeholder="Déjalo vacío para conservar la clave actual" autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')">
                            <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="clear_license_key" id="edit_clear_license_key"><label class="form-check-label" for="edit_clear_license_key">Eliminar clave guardada</label></div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Costo total</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" name="cost" id="edit_cost" class="form-control" min="0">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Moneda</label>
                            <select name="currency" id="edit_currency" class="form-select fw-bold">
                                <option value="MXN">Peso Mexicano (MXN)</option>
                                <option value="USD">Dólar Americano (USD)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha de compra</label>
                            <input type="date" name="purchase_date" id="edit_purchase_date" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha de vencimiento (opcional)</label>
                            <input type="date" name="expiration_date" id="edit_expiration_date" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Notas / Observaciones</label>
                            <input type="text" name="notes" id="edit_notes" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_licencia" class="btn btn-lic-primary"><i class="fa fa-sync me-1"></i> Actualizar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    let registroSeleccionado = null;
    let temporizadorBusqueda = null;
    let secuenciaBusqueda = 0;
    const modalDetalleEl = document.getElementById('modalDetalle');
    const modalEdicionEl = document.getElementById('modalEditar');
    const modalDetalle = new bootstrap.Modal(modalDetalleEl);
    const modalEdicion = new bootstrap.Modal(modalEdicionEl);
    const csrfToken = document.querySelector('#formLicencia [name="csrf_token"]').value;
    const endpointClave = <?= json_encode((defined('BASE_URL') ? rtrim(BASE_URL, '/') : '') . '/admin/vigencias/clave.php', JSON_UNESCAPED_SLASHES) ?>;

    async function clavesCoincidentes(termino) {
        if (termino.length < 2) return [];
        const body = new URLSearchParams({ accion: 'buscar', termino, csrf_token: csrfToken });
        try {
            const response = await fetch(endpointClave, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            if (!response.ok) return [];
            const data = await response.json();
            return data.ids || [];
        } catch (_) { return []; }
    }

    async function aplicarFiltros() {
        const secuencia = ++secuenciaBusqueda;
        const termino = ($('#buscarLicencia').val() || '').toLocaleLowerCase('es-MX').trim();
        const tipo = $('#filtrarTipo').val();
        const estado = $('#filtrarEstado').val();
        const idsClave = new Set(await clavesCoincidentes(termino));
        if (secuencia !== secuenciaBusqueda) return;
        let visibles = 0;
        $('#tablaLicencias tbody tr').each(function(){
            if (this.id === 'sinResultadosLicencias') return;
            const $fila = $(this);
            const datos = (this.dataset.search || '').toLocaleLowerCase('es-MX');
            const coincideTexto = !termino || datos.includes(termino) || idsClave.has(Number(this.dataset.id));
            const coincideTipo = !tipo || this.dataset.type === tipo;
            const coincideEstado = !estado || this.dataset.statusGroup === estado;
            const mostrar = coincideTexto && coincideTipo && coincideEstado;
            $fila.toggle(mostrar);
            if (mostrar) visibles++;
        });
        const vacio = document.getElementById('sinResultadosLicencias');
        if (vacio) vacio.hidden = visibles > 0;
    }

    $('#buscarLicencia').on('input', function(){ clearTimeout(temporizadorBusqueda); temporizadorBusqueda = setTimeout(aplicarFiltros, 180); });
    $('#filtrarTipo, #filtrarEstado').on('change', aplicarFiltros);

    $('.btn-detail').on('click', function(){
        const $btn = $(this);
        registroSeleccionado = $btn.data();
        const texto = (id, valor) => document.getElementById(id).textContent = valor || '—';
        $('#detail_license_key').text(registroSeleccionado.license_key_mask || 'Sin clave');
        $('#revelarClave').text('Mostrar').prop('disabled', false).each(function(){ this.dataset.visible = 'false'; });
        texto('detail_service_name', registroSeleccionado.service_name);
        texto('detail_license_type', registroSeleccionado.license_type);
        texto('detail_license_mode', registroSeleccionado.license_mode);
        texto('detail_version_edition', registroSeleccionado.version_edition);
        texto('detail_quantity', registroSeleccionado.quantity);
        texto('detail_assigned_to', registroSeleccionado.assigned_to);
        texto('detail_provider', registroSeleccionado.provider);
        texto('detail_cost', '$' + Number(registroSeleccionado.cost || 0).toFixed(2) + ' ' + registroSeleccionado.currency);
        texto('detail_purchase_date', registroSeleccionado.purchase_date ? new Date(registroSeleccionado.purchase_date + 'T00:00:00').toLocaleDateString('es-MX') : '—');
        texto('detail_expiration_date', registroSeleccionado.expiration_date ? new Date(registroSeleccionado.expiration_date + 'T00:00:00').toLocaleDateString('es-MX') : 'Sin vencimiento');
        texto('detail_notes', registroSeleccionado.notes);
        $('#detail_status').text(registroSeleccionado.status || '—').attr('class', 'badge ' + (registroSeleccionado.status_class || 'badge-status-ok'));
        $('#detail_delete').val(registroSeleccionado.id);
        modalDetalle.show();
    });

    $('#revelarClave').on('click', async function(){
        const boton = this;
        if (!registroSeleccionado) return;
        if (boton.dataset.visible === 'true') {
            $('#detail_license_key').text(registroSeleccionado.license_key_mask || 'Sin clave');
            boton.dataset.visible = 'false'; boton.textContent = 'Mostrar'; return;
        }
        boton.disabled = true; boton.textContent = 'Cargando…';
        try {
            const body = new URLSearchParams({ accion: 'revelar', id: registroSeleccionado.id, csrf_token: csrfToken });
            const response = await fetch(endpointClave, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body });
            const data = await response.json();
            if (!response.ok) throw new Error(data.error || 'No se pudo mostrar la clave.');
            $('#detail_license_key').text(data.license_key || 'Sin clave');
            boton.dataset.visible = 'true'; boton.textContent = 'Ocultar';
        } catch (error) {
            $('#detail_license_key').text(error.message || 'No se pudo mostrar la clave.');
            boton.textContent = 'Reintentar';
        } finally { boton.disabled = false; }
    });

    $('#detail_edit').on('click', function(){
        if (!registroSeleccionado) return;
        const datos = registroSeleccionado;
        $('#edit_id').val(datos.id);
        $('#edit_service_name').val(datos.service_name);
        $('#edit_license_type').val(datos.license_type);
        $('#edit_license_mode').val(datos.license_mode);
        $('#edit_provider').val(datos.provider);
        $('#edit_version_edition').val(datos.version_edition);
        $('#edit_license_key').val('');
        $('#edit_clear_license_key').prop('checked', false);
        $('#edit_quantity').val(datos.quantity);
        $('#edit_assigned_to').val(datos.assigned_to);
        $('#edit_cost').val(datos.cost);
        $('#edit_currency').val(datos.currency);
        $('#edit_purchase_date').val(datos.purchase_date);
        $('#edit_expiration_date').val(datos.expiration_date);
        $('#edit_notes').val(datos.notes);
        modalDetalle.hide();
        modalDetalleEl.addEventListener('hidden.bs.modal', function abrirEdicion(){
            modalDetalleEl.removeEventListener('hidden.bs.modal', abrirEdicion);
            modalEdicion.show();
        });
    });

});
</script>
