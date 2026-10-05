<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('licencias');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token CSRF de la sesión
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

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
    $provider        = trim($_POST['provider'] ?? '');
    $license_key     = trim($_POST['license_key'] ?? '');
    $cost            = floatval($_POST['cost'] ?? 0);
    $currency        = trim($_POST['currency'] ?? 'MXN');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido.";
    } elseif (empty($service_name) || empty($expiration_date)) {
        $_SESSION['flash_msg'] = "❌ El nombre del servicio y la fecha de vencimiento son obligatorios.";
    } else {
        $stmt = $conexion->prepare("INSERT INTO licenses (service_name, provider, license_key, cost, currency, expiration_date, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdsss", $service_name, $provider, $license_key, $cost, $currency, $expiration_date, $notes);

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
    $provider        = trim($_POST['provider'] ?? '');
    $license_key     = trim($_POST['license_key'] ?? '');
    $cost            = floatval($_POST['cost'] ?? 0);
    $currency        = trim($_POST['currency'] ?? 'MXN');
    $expiration_date = trim($_POST['expiration_date'] ?? '');
    $notes           = trim($_POST['notes'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido.";
    } elseif ($id <= 0 || empty($service_name) || empty($expiration_date)) {
        $_SESSION['flash_msg'] = "❌ Datos incompletos para actualizar el registro.";
    } else {
        $stmt = $conexion->prepare("UPDATE licenses SET service_name = ?, provider = ?, license_key = ?, cost = ?, currency = ?, expiration_date = ?, notes = ? WHERE id = ?");
        $stmt->bind_param("sssdsssi", $service_name, $provider, $license_key, $cost, $currency, $expiration_date, $notes, $id);

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
include ($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');

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
$result = $conexion->query("SELECT *, DATEDIFF(expiration_date, CURDATE()) AS dias_restantes FROM licenses ORDER BY expiration_date ASC");

/* =========================
   ESTADÍSTICAS RÁPIDAS
   ========================= */
$stats = $conexion->query("
    SELECT
        COUNT(*) AS total_licencias,
        COALESCE(SUM(CASE WHEN expiration_date < CURDATE() THEN 1 ELSE 0 END), 0) AS vencidas,
        COALESCE(SUM(CASE WHEN expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END), 0) AS por_vencer
    FROM licenses
")->fetch_assoc();
?>

<style>
    body { margin-top: 100px; }

    .lic-page {
        --lic-bg: #f8fafc;
        --lic-surface: #ffffff;
        --lic-border: #e2e8f0;
        --lic-border-soft: #eef1f5;
        --lic-text: #0f172a;
        --lic-muted: #64748b;
        --lic-accent: #4f46e5;
        --lic-accent-dark: #4338ca;
        --lic-accent-light: #eef2ff;
        --lic-danger: #dc2626;
        --lic-warning: #d97706;
        --lic-success: #16a34a;
        --lic-radius: 14px;
        color: var(--lic-text);
    }
    .lic-page .lic-card {
        background: var(--lic-surface);
        border: 1px solid var(--lic-border);
        border-radius: var(--lic-radius);
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }
    .lic-page .lic-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .5rem;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--lic-border-soft);
    }
    .lic-page .lic-card-header h3 {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: .55rem;
        color: var(--lic-text);
    }
    .lic-page .lic-card-icon {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: var(--lic-accent-light);
        color: var(--lic-accent);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: .95rem;
        flex: none;
    }
    .lic-page .lic-card-body { padding: 1.5rem; }

    /* Tarjetas de estadísticas */
    .lic-page .lic-stat {
        background: var(--lic-surface);
        border: 1px solid var(--lic-border);
        border-radius: var(--lic-radius);
        padding: 1.1rem 1.25rem;
        display: flex;
        align-items: center;
        gap: .9rem;
        height: 100%;
    }
    .lic-page .lic-stat-icon {
        width: 42px;
        height: 42px;
        border-radius: 11px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
        flex: none;
        background: var(--lic-accent-light);
        color: var(--lic-accent);
    }
    .lic-page .lic-stat-label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: var(--lic-muted);
        font-weight: 600;
        margin: 0 0 .15rem;
    }
    .lic-page .lic-stat-value {
        font-size: 1.35rem;
        font-weight: 700;
        color: var(--lic-text);
        line-height: 1.1;
    }

    /* Formulario */
    .lic-page .form-label {
        font-size: .8rem;
        font-weight: 600;
        color: var(--lic-muted);
        text-transform: uppercase;
        letter-spacing: .03em;
        margin-bottom: .35rem;
    }
    .lic-page .form-control, .lic-page .form-select {
        border-color: var(--lic-border);
        border-radius: 9px;
        padding: .55rem .8rem;
    }
    .lic-page .form-control:focus, .lic-page .form-select:focus {
        border-color: var(--lic-accent);
        box-shadow: 0 0 0 .2rem rgba(79, 70, 229, .12);
    }

    /* Botones */
    .lic-page .btn-lic-primary {
        background: var(--lic-accent);
        border-color: var(--lic-accent);
        color: #fff;
        font-weight: 600;
        border-radius: 9px;
        padding: .6rem 1.6rem;
    }
    .lic-page .btn-lic-primary:hover {
        background: var(--lic-accent-dark);
        border-color: var(--lic-accent-dark);
        color: #fff;
    }

    /* Tablas y Badges */
    .lic-page .lic-search { max-width: 280px; }
    .lic-page #tablaLicencias thead th {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .05em;
        color: var(--lic-muted);
        font-weight: 700;
        border-bottom: 1px solid var(--lic-border);
        background: transparent;
    }
    .lic-page #tablaLicencias td { border-color: var(--lic-border-soft); }
    .lic-page #tablaLicencias tbody tr:hover { background: var(--lic-bg); }

    .badge-status-ok { background: #dcfce7; color: #15803d; font-weight: 600; }
    .badge-status-warn { background: #fef3c7; color: #b45309; font-weight: 600; }
    .badge-status-danger { background: #fee2e2; color: #b91c1c; font-weight: 600; }

    .lic-page .lic-icon-btn {
        width: 32px;
        height: 32px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        border: 1px solid var(--lic-border);
        background: var(--lic-surface);
        color: var(--lic-muted);
        transition: all .15s ease;
    }
    .lic-page .lic-icon-btn:hover {
        background: var(--lic-bg);
        color: var(--lic-text);
    }
    .lic-page .lic-icon-btn.lic-icon-danger:hover {
        background: #fef2f2;
        color: var(--lic-danger);
        border-color: #fecaca;
    }
    .lic-page .lic-empty {
        text-align: center;
        padding: 3rem 1rem;
        color: var(--lic-muted);
    }
    .lic-page .lic-empty i {
        font-size: 2rem;
        color: var(--lic-border);
        margin-bottom: .75rem;
        display: block;
    }
</style>

<div class="container-fluid mt-4 lic-page">

    <!-- Tarjetas de Resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-4">
            <div class="lic-stat">
                <div class="lic-stat-icon"><i class="fa-solid fa-key"></i></div>
                <div>
                    <p class="lic-stat-label">Total Registrados</p>
                    <div class="lic-stat-value"><?= number_format($stats['total_licencias']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="lic-stat">
                <div class="lic-stat-icon" style="background:#fef3c7; color:#d97706;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div>
                    <p class="lic-stat-label">Por Vencer (30 Días)</p>
                    <div class="lic-stat-value"><?= number_format($stats['por_vencer']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-4">
            <div class="lic-stat">
                <div class="lic-stat-icon" style="background:#fee2e2; color:#dc2626;"><i class="fa-solid fa-circle-xmark"></i></div>
                <div>
                    <p class="lic-stat-label">Vencidas / Expiradas</p>
                    <div class="lic-stat-value"><?= number_format($stats['vencidas']) ?></div>
                </div>
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

            <form method="POST" id="formLicencia">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Servicio / Servidor / Software</label>
                        <input type="text" name="service_name" class="form-control" placeholder="Ej. VPS DigitalOcean, Office 365" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Proveedor / Dominio</label>
                        <input type="text" name="provider" class="form-control" placeholder="Ej. Namecheap, Microsoft, AWS">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Licencia / Serial / IP / ID</label>
                        <input type="text" name="license_key" class="form-control" placeholder="Clave, IP o referencia">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Costo</label>
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
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="date" name="expiration_date" class="form-control" required>
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
            <input type="text" id="buscarLicencia" class="form-control form-control-sm lic-search" placeholder="Buscar servicio, proveedor...">
        </div>
        <div class="lic-card-body">
            <?php if ($result->num_rows === 0): ?>
                <div class="lic-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    Aún no hay licencias o servicios registrados.<br>Agrega el primero desde el formulario superior.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaLicencias" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Servicio / Servidor</th>
                            <th>Proveedor</th>
                            <th>Licencia / Ref</th>
                            <th>Costo</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Notas</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <?php
                            $dias = (int)$row['dias_restantes'];
                            if ($dias < 0) {
                                $badgeClass = 'badge-status-danger';
                                $statusText = 'Vencida (' . abs($dias) . ' días)';
                            } elseif ($dias <= 30) {
                                $badgeClass = 'badge-status-warn';
                                $statusText = 'Por vencer (' . $dias . ' días)';
                            } else {
                                $badgeClass = 'badge-status-ok';
                                $statusText = 'Vigente (' . $dias . ' días)';
                            }
                        ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($row['service_name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($row['provider'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($row['license_key'] ?? '-', ENT_QUOTES, 'UTF-8') ?></code></td>
                            <td class="fw-bold">$<?= number_format($row['cost'], 2) ?> <small class="text-muted"><?= htmlspecialchars($row['currency']) ?></small></td>
                            <td><?= date('d/m/Y', strtotime($row['expiration_date'])) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                            <td class="small text-muted"><?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <!-- Botón Editar (Modal) -->
                                <button type="button" class="lic-icon-btn btn-edit me-1" 
                                    data-id="<?= $row['id'] ?>"
                                    data-service_name="<?= htmlspecialchars($row['service_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-provider="<?= htmlspecialchars($row['provider'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-license_key="<?= htmlspecialchars($row['license_key'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-cost="<?= $row['cost'] ?>"
                                    data-currency="<?= $row['currency'] ?>"
                                    data-expiration_date="<?= $row['expiration_date'] ?>"
                                    data-notes="<?= htmlspecialchars($row['notes'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    title="Editar">
                                    <i class="fa fa-pen"></i>
                                </button>

                                <!-- Formulario Eliminar -->
                                <form method="POST" style="display:inline" onsubmit="return confirm('¿Deseas eliminar este registro?');">
                                    <input type="hidden" name="eliminar" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="lic-icon-btn lic-icon-danger" title="Eliminar">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
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
                        <div class="col-md-6">
                            <label class="form-label">Servicio / Servidor / Software</label>
                            <input type="text" name="service_name" id="edit_service_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Proveedor / Dominio</label>
                            <input type="text" name="provider" id="edit_provider" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Licencia / Serial / IP / ID</label>
                            <input type="text" name="license_key" id="edit_license_key" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Costo</label>
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
                        <div class="col-md-4">
                            <label class="form-label">Fecha de Vencimiento</label>
                            <input type="date" name="expiration_date" id="edit_expiration_date" class="form-control" required>
                        </div>
                        <div class="col-md-8">
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

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
$(document).ready(function(){
    // Buscador interactivo en tiempo real
    $('#buscarLicencia').on('input', function(){
        const q = $(this).val().toLowerCase().trim();
        $('#tablaLicencias tbody tr').each(function(){
            const texto = $(this).text().toLowerCase();
            $(this).toggle(texto.indexOf(q) !== -1);
        });
    });

    // Cargar datos en el Modal de Edición
    $('.btn-edit').on('click', function(){
        const $btn = $(this);
        $('#edit_id').val($btn.data('id'));
        $('#edit_service_name').val($btn.data('service_name'));
        $('#edit_provider').val($btn.data('provider'));
        $('#edit_license_key').val($btn.data('license_key'));
        $('#edit_cost').val($btn.data('cost'));
        $('#edit_currency').val($btn.data('currency'));
        $('#edit_expiration_date').val($btn.data('expiration_date'));
        $('#edit_notes').val($btn.data('notes'));

        const modalEditar = new bootstrap.Modal(document.getElementById('modalEditar'));
        modalEditar.show();
    });
});
</script>