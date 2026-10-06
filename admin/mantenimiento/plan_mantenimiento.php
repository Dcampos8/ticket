<?php
// 1. Intentar activar visualización de errores (por si el servidor lo permite)
@ini_set('display_errors', 0);
ini_set('log_errors', 1);
@ini_set('display_startup_errors', 0);
@error_reporting(E_ALL);

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Rutas Relativas Blindadas
$ruta_config   = __DIR__ . '/../../config.php';
$ruta_conexion = __DIR__ . '/../../backend/conexion.php';
$ruta_menu     = __DIR__ . '/../menu.php';

if (!file_exists($ruta_config)) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; font-family:sans-serif;'><strong>Error Crítico:</strong> No se pudo localizar 'config.php' usando la ruta: <br><code>" . htmlspecialchars($ruta_config) . "</code></div>");
}
require_once($ruta_config);

if (!file_exists($ruta_conexion)) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; font-family:sans-serif;'><strong>Error Crítico:</strong> No se pudo localizar la conexión en: <br><code>" . htmlspecialchars($ruta_conexion) . "</code></div>");
}
require_once($ruta_conexion);

// 4. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('mantenimiento');
requerirAdmin();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$flash_msg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);

// Asegurar codificación utf8
$conexion->set_charset("utf8");

// 5. Carga opcional de maquetación visual
if (file_exists($ruta_menu)) { include($ruta_menu); }


// --- LÓGICA DE QUERIES: SOLO EQUIPOS PRINCIPALES (PC, LAPTOP, SERVIDOR) ---

// Query A: Trae directamente de inventario_equipos SOLO los tipos PC, Laptop y Servidor.
$selectEquiposPC = $conexion->query("
    SELECT ie.id, ie.marca, ie.modelo, ie.no_serie, ie.tipo_dispositivo, u.nombre_completo
    FROM inventario_equipos ie
    LEFT JOIN usuarios u ON ie.responsable = u.id
    WHERE ie.tipo_dispositivo IN ('PC','Laptop','Servidor')
    ORDER BY ie.tipo_dispositivo, ie.marca, ie.modelo
");

// Query B: Traer áreas del inventario (mismo filtro: solo PC, Laptop y Servidor)
$areasExistentes = $conexion->query("
    SELECT DISTINCT area
    FROM inventario_equipos
    WHERE area IS NOT NULL AND area != ''
      AND tipo_dispositivo IN ('PC','Laptop','Servidor')
    ORDER BY area
");

// Query C: Conteo general de planes
$totalPlanes = 0;
$resTotal = $conexion->query("SELECT COUNT(*) as total FROM plan_mantenimiento");
if ($resTotal) {
    $rowTotal = $resTotal->fetch_assoc();
    $totalPlanes = $rowTotal['total'];
}

// Query D: Lista principal de la tabla de planes
$planes = $conexion->query("
    SELECT 
        pm.*, 
        u.nombre_completo, 
        ie.marca, 
        ie.modelo, 
        ie.no_serie,
        ie.tipo_dispositivo
    FROM plan_mantenimiento pm
    LEFT JOIN usuarios u ON pm.responsable = u.id
    LEFT JOIN inventario_equipos ie ON pm.id_equipo = ie.id
    ORDER BY pm.area
");
?>

<style>
    :root { --primary-red: #E30613; --dark-gray: #1a1a1a; }
    body { margin-top: 0; background-color: #f8f9fa; }
    .mantenimiento-content { padding: 20px; min-height: 100vh; }
    .main-card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; margin-bottom: 30px; border-top: 5px solid var(--primary-red); }
    .table thead { background: var(--dark-gray); color: #fff; }
    .btn-primary { background-color: var(--primary-red); border-color: var(--primary-red); }
    .btn-primary:hover { background-color: #b3050f; border-color: #b3050f; }
</style>

<div class="mantenimiento-content">
    <div class="container-fluid mt-4">

        <?php if ($flash_msg !== ''): ?>
            <div class="alert alert-info" role="status"><?= htmlspecialchars($flash_msg, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h2 class="fw-bold mb-0">Gestión de Mantenimiento Anual</h2>
                <p class="text-muted">Filtro de Infraestructura: Equipos Principales (PC, Laptop y Servidor)</p>
            </div>
            <div class="text-end">
                <span class="badge bg-dark p-2">Total de Planes: <?= $totalPlanes ?></span>
            </div>
        </div>

        <!-- FORMULARIO NUEVO PLAN -->
        <div class="main-card text-dark">
            <h5 class="mb-3 fw-bold"><i class="bi bi-plus-circle-fill me-2"></i>Nuevo Plan de Mantenimiento</h5>
            <form method="POST" action="backend/guardar_plan.php" class="row g-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                
                <!-- Selector Dinámico de Hardware Informático -->
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-muted text-uppercase">Equipo Asignado</label>
                    <select name="id_equipo" class="form-select" required>
                        <option value="">Seleccione el equipo...</option>
                        <?php if($selectEquiposPC && $selectEquiposPC->num_rows > 0): ?>
                            <?php while($eq = $selectEquiposPC->fetch_assoc()) { 
                                $titular = !empty($eq['nombre_completo']) ? ' (Resguardo: ' . $eq['nombre_completo'] . ')' : ' (Sin usuario)';
                                ?>
                                <option value="<?= $eq['id'] ?>">
                                    [<?= htmlspecialchars($eq['tipo_dispositivo']) ?>] <?= htmlspecialchars($eq['marca'] . ' ' . $eq['modelo']) ?> - S/N: <?= htmlspecialchars($eq['no_serie']) ?> <?= $titular ?>
                                </option>
                            <?php } ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Input autocompletable para las Áreas -->
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted text-uppercase">Área / Departamento</label>
                    <input name="area" class="form-control" list="datalistAreas" placeholder="Escriba o seleccione área..." required>
                    <datalist id="datalistAreas">
                        <?php if($areasExistentes): ?>
                            <?php while($ar = $areasExistentes->fetch_assoc()) { ?>
                                <option value="<?= htmlspecialchars($ar['area']) ?>"></option>
                            <?php } ?>
                        <?php endif; ?>
                    </datalist>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Tipo</label>
                    <select name="tipo" class="form-select" required>
                        <option>Preventivo</option>
                        <option>Correctivo</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted text-uppercase">Frecuencia</label>
                    <select name="frecuencia" class="form-select" required>
                        <option>Mensual</option>
                        <option>Trimestral</option>
                        <option>Semestral</option>
                        <option>Anual</option>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label small fw-bold text-muted text-uppercase">Año</label>
                    <input type="number" name="anio" value="<?= date('Y') ?>" class="form-control" required>
                </div>

                <div class="col-12 d-flex gap-2 border-top pt-3">
                    <button type="submit" class="btn btn-primary px-4 fw-bold">Crear Plan</button>
                </div>
            </form>
        </div>

        <!-- TABLA DE PLANES GENERADOS -->
        <div class="table-responsive">
            <table class="table table-hover align-middle border shadow-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Hardware / Dispositivo</th>
                        <th>Ubicación / Responsable</th>
                        <th>Identificadores</th>
                        <th>Modalidad</th>
                        <th class="text-center">Año</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody class="bg-white text-dark">
                <?php if($planes && $planes->num_rows > 0): ?>
                    <?php while($p = $planes->fetch_assoc()) { ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">
                                    <i class="bi bi-pc-display text-danger me-1"></i> 
                                    <?= htmlspecialchars(($p['marca'] ?? 'Equipo') . ' ' . ($p['modelo'] ?? '')) ?>
                                </div>
                                <div class="small text-muted">Tipo: <?= htmlspecialchars($p['tipo_dispositivo'] ?? 'Cómputo') ?></div>
                            </td>
                            <td>
                                <div><i class="bi bi-geo-alt text-muted"></i> <?= htmlspecialchars($p['area'] ?? 'General') ?></div>
                                <div class="small fw-bold text-danger"><?= htmlspecialchars($p['nombre_completo'] ?? 'Sin asignar') ?></div>
                            </td>
                            <td>
                                <div class="small text-muted">S/N: <code><?= htmlspecialchars($p['no_serie'] ?? 'N/A') ?></code></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($p['tipo']) ?></span>
                                <div class="small text-muted mt-1 fw-bold"><?= htmlspecialchars($p['frecuencia']) ?></div>
                            </td>
                            <td class="text-center fw-bold"><?= htmlspecialchars($p['anio']) ?></td>
                            <td class="text-end">
                                <div class="btn-group shadow-sm">
                                    <a href="backend/editar_plan.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-dark text-white">Editar</a>
                                    <form method="POST" action="backend/eliminar_plan.php" class="d-inline" onsubmit="return confirm('¿Está seguro de eliminar este plan?')">
                                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash"></i> Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <i class="bi bi-info-circle me-1"></i> No existen registros en el plan de mantenimiento actual.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        
    </div>
</div>

<?php 
if (isset($conexion)) { $conexion->close(); } 
?>
