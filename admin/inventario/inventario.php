<?php
/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * Vista Principal - Versión Extendida con Detalles Técnicos
 */

$base_path = '/home/u568802486/domains/transportesvaladez.com/public_html/ticket/';

if (file_exists($base_path . 'config.php')) {
    require_once $base_path . 'config.php';
} else {
    die("Error Crítico: No se pudo cargar la configuración.");
}

// Asegurar que la sesión está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('inventario');

include(ROOT_PATH . 'admin/header.php');
include(ROOT_PATH . 'admin/menu.php');

/* ================= FILTROS Y SANITIZACIÓN ================= */
$estado           = trim($_GET['estado'] ?? '');
$buscar           = trim($_GET['buscar'] ?? '');
$tipo_dispositivo = trim($_GET['tipo_dispositivo'] ?? '');

// Construcción segura de la consulta
$where = "WHERE 1=1";
$params = [];
$types = "";

if ($estado !== '') {
    $where .= " AND e.estado = ?";
    $params[] = $estado;
    $types .= "s";
}

if ($tipo_dispositivo !== '') {
    $where .= " AND e.tipo_dispositivo = ?";
    $params[] = $tipo_dispositivo;
    $types .= "s";
}

if ($buscar !== '') {
    // Búsqueda ampliada: incluye ID, Periféricos (Teclado, Mouse, Monitor) y demás especificaciones
    $where .= " AND (e.id = ? OR e.numero_resguardo LIKE ? OR e.no_serie LIKE ? OR e.marca LIKE ? OR e.modelo LIKE ? OR e.procesador LIKE ? OR e.ram LIKE ? OR e.disco_duro LIKE ? OR e.marca_teclado LIKE ? OR e.modelo_teclado LIKE ? OR e.marca_mouse LIKE ? OR e.modelo_mouse LIKE ? OR e.marca_monitor LIKE ? OR e.modelo_monitor LIKE ? OR u.nombre_completo LIKE ?)";
    $term = "%" . $buscar . "%";
    $id_search = is_numeric($buscar) ? intval($buscar) : 0;
    
    array_push($params, $id_search, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term);
    $types .= "issssssssssssss"; // 1 entero ('i') y 14 strings ('s')
}

// Consulta principal
$query = "SELECT e.*, u.nombre_completo 
          FROM inventario_equipos e 
          LEFT JOIN usuarios u ON e.responsable = u.id 
          $where 
          ORDER BY e.id DESC";

$stmt = $conexion->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Consulta para llenar el filtro de tipos de dispositivo
$tipos_q = $conexion->query("SELECT DISTINCT tipo_dispositivo FROM inventario_equipos WHERE tipo_dispositivo IS NOT NULL AND tipo_dispositivo != '' ORDER BY tipo_dispositivo ASC");
?>

<style>
    .contenedor-inventario {
        margin-top: 0 !important;
        padding: 20px;
    }
    .tabla-valadez thead {
        background-color: #2c3e50 !important;
        color: white !important;
        font-size: 11px;
        letter-spacing: 0.5px;
    }
    .btn-acciones {
        width: 32px;
        height: 32px;
        line-height: 32px;
        padding: 0;
        text-align: center;
        border-radius: 6px;
        color: white;
        margin: 0 2px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.15s ease, opacity 0.15s ease;
        text-decoration: none;
    }
    .btn-acciones:hover {
        color: #fff;
        transform: translateY(-2px);
        opacity: 0.9;
    }
    .header-acciones {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .badge-estatus {
        font-size: 11px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 50px;
    }
    .tech-spec {
        font-size: 11px;
        color: #495057;
        background-color: #e9ecef;
        padding: 2px 6px;
        border-radius: 4px;
        display: inline-block;
        margin-top: 2px;
    }
</style>

<div class="contenedor-inventario">
    <div class="container-fluid">
        
        <!-- ENCABEZADO -->
        <div class="header-acciones">
            <div>
                <h2 class="fw-bold text-dark m-0"><i class="fa-solid fa-microchip text-primary me-2"></i>Gestión de Activos</h2>
                <p class="text-muted small m-0">Inventario general de equipos y detalles técnicos.</p>
            </div>
            <div>
                <a href="exportar_excel.php?<?= http_build_query($_GET) ?>" class="btn btn-outline-success me-2">
                    <i class="fa-solid fa-file-excel me-1"></i> Exportar a Excel
                </a>
                <a href="nuevo_equipo.php" class="btn btn-primary shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Nuevo Registro
                </a>
            </div>
        </div>
        
        <!-- FILTROS DE BÚSQUEDA -->
        <div class="card mb-4 shadow-sm" style="border-radius: 10px; border: 1px solid #e3e6f0;">
            <div class="card-body bg-light">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Búsqueda general:</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" name="buscar" class="form-control" placeholder="ID, Resguardo, Serie, Specs, Responsable..." value="<?= htmlspecialchars($buscar) ?>">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Tipo de Dispositivo:</label>
                        <select name="tipo_dispositivo" class="form-select text-uppercase">
                            <option value="">-- Todos --</option>
                            <?php while($t = $tipos_q->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($t['tipo_dispositivo']) ?>" <?= ($tipo_dispositivo === $t['tipo_dispositivo']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(strtoupper($t['tipo_dispositivo'])) ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Estatus:</label>
                        <select name="estado" class="form-select">
                            <option value="">-- Todos --</option>
                            <option value="Activo" <?= ($estado === 'Activo') ? 'selected' : '' ?>>Activo</option>
                            <option value="Baja" <?= ($estado === 'Baja') ? 'selected' : '' ?>>Baja</option>
                            <option value="Prestamo" <?= ($estado === 'Prestamo') ? 'selected' : '' ?>>Préstamo</option>
                            <option value="Reparacion" <?= ($estado === 'Reparacion') ? 'selected' : '' ?>>Reparación</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button type="submit" class="btn btn-dark w-100"><i class="fa-solid fa-filter me-1"></i> Filtrar</button>
                        <a href="inventario.php" class="btn btn-outline-secondary px-3" title="Limpiar Filtros"><i class="fa-solid fa-rotate-left"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- TABLA DE RESULTADOS -->
        <div class="card shadow-sm" style="border-radius: 10px; overflow: hidden; border: none;">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 tabla-valadez">
                    <thead>
                        <tr class="text-center">
                            <th>ID / RESGUARDO</th>
                            <th>CATEGORÍA</th>
                            <th>MARCA / MODELO</th>
                            <th>NO. SERIE</th>
                            <th>ESPECIFICACIONES</th>
                            <th>RESPONSABLE / ÁREA</th>
                            <th>ESTATUS</th>
                            <th>ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result && $result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <!-- ID Y RESGUARDO -->
                                <td class="text-center">
                                    <span class="badge bg-secondary text-white mb-1" style="font-size: 10px;">ID: #<?= $row['id'] ?></span><br>
                                    <span class="badge bg-light text-dark border px-2 py-1 fw-bold">
                                        <?= htmlspecialchars($row['numero_resguardo'] ?: 'S/R') ?>
                                    </span>
                                </td>

                                <!-- CATEGORÍA -->
                                <td>
                                    <span class="fw-bold text-uppercase small text-dark"><?= htmlspecialchars($row['tipo_dispositivo'] ?: 'N/A') ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['tipo_equipo'] ?: '') ?></small>
                                </td>

                                <!-- MARCA / MODELO -->
                                <td>
                                    <span class="small fw-semibold"><?= htmlspecialchars($row['marca'] ?: 'S/M') ?></span><br>
                                    <small class="text-muted"><?= htmlspecialchars($row['modelo'] ?: 'S/M') ?></small>
                                </td>

                                <!-- SERIE -->
                                <td class="text-center">
                                    <?php if(!empty($row['no_serie'])): ?>
                                        <code class="fw-bold" style="font-size: 12px; color: #d63384;">
                                            <?= htmlspecialchars($row['no_serie']) ?>
                                        </code>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border fw-normal" style="font-size: 10px;">SIN SERIE</span>
                                    <?php endif; ?>
                                </td>

                                <!-- ESPECIFICACIONES TÉCNICAS Y PERIFÉRICOS -->
                                <td>
                                    <?php 
                                        $has_specs = !empty($row['procesador']) || !empty($row['ram']) || !empty($row['disco_duro']) || 
                                                     !empty($row['marca_teclado']) || !empty($row['marca_mouse']) || !empty($row['marca_monitor']);
                                    ?>
                                    <?php if($has_specs): ?>
                                        <?php if(!empty($row['procesador'])): ?>
                                            <span class="tech-spec"><i class="fa-solid fa-microchip me-1 text-primary"></i><?= htmlspecialchars($row['procesador']) ?></span><br>
                                        <?php endif; ?>
                                        <?php if(!empty($row['ram'])): ?>
                                            <span class="tech-spec"><i class="fa-solid fa-memory me-1 text-info"></i><?= htmlspecialchars($row['ram']) ?></span>
                                        <?php endif; ?>
                                        <?php if(!empty($row['disco_duro'])): ?>
                                            <span class="tech-spec"><i class="fa-solid fa-hard-drive me-1 text-warning"></i><?= htmlspecialchars($row['disco_duro']) ?></span><br>
                                        <?php endif; ?>
                                
                                        <!-- Periféricos -->
                                        <?php if(!empty($row['marca_teclado']) || !empty($row['modelo_teclado'])): ?>
                                            <span class="tech-spec" style="background-color: #e2e3e5;"><i class="fa-solid fa-keyboard me-1 text-secondary"></i>Teclado: <?= htmlspecialchars(trim($row['marca_teclado'] . ' ' . $row['modelo_teclado'])) ?></span><br>
                                        <?php endif; ?>
                                        <?php if(!empty($row['marca_mouse']) || !empty($row['modelo_mouse'])): ?>
                                            <span class="tech-spec" style="background-color: #e2e3e5;"><i class="fa-solid fa-computer-mouse me-1 text-secondary"></i>Mouse: <?= htmlspecialchars(trim($row['marca_mouse'] . ' ' . $row['modelo_mouse'])) ?></span><br>
                                        <?php endif; ?>
                                        <?php if(!empty($row['marca_monitor']) || !empty($row['modelo_monitor'])): ?>
                                            <span class="tech-spec" style="background-color: #e2e3e5;"><i class="fa-solid fa-desktop me-1 text-secondary"></i>Monitor: <?= htmlspecialchars(trim($row['marca_monitor'] . ' ' . $row['modelo_monitor'])) ?></span>
                                        <?php endif; ?>
                                
                                    <?php else: ?>
                                        <small class="text-muted italic">N/A</small>
                                    <?php endif; ?>
                                </td>

                                <!-- RESPONSABLE -->
                                <td>
                                    <div class="small fw-semibold text-dark">
                                        <i class="fa-solid fa-user-gear text-secondary me-1"></i>
                                        <?= htmlspecialchars($row['nombre_completo'] ?: 'Sin Asignar') ?>
                                    </div>
                                    <small class="text-muted text-uppercase" style="font-size: 10px;">
                                        <?= htmlspecialchars($row['area'] ?: 'Sin Área') ?>
                                    </small>
                                </td>

                                <!-- ESTATUS CON BADGES -->
                                <td class="text-center">
                                    <?php 
                                        $badge_class = match($row['estado']) {
                                            'Activo'     => 'bg-success-subtle text-success border border-success-subtle',
                                            'Baja'       => 'bg-danger-subtle text-danger border border-danger-subtle',
                                            'Prestamo'   => 'bg-primary-subtle text-primary border border-primary-subtle',
                                            'Reparacion' => 'bg-warning-subtle text-warning border border-warning-subtle',
                                            default      => 'bg-secondary-subtle text-secondary'
                                        };
                                    ?>
                                    <span class="badge badge-estatus <?= $badge_class ?>">
                                        <?= htmlspecialchars($row['estado']) ?>
                                    </span>
                                </td>

                                <!-- ACCIONES -->
                                <td class="text-center">
                                    <a href="editar_equipo.php?id=<?= $row['id'] ?>" class="btn-acciones" style="background-color: #f39c12;" title="Editar Equipo">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <a href="movimiento_equipo.php?id=<?= $row['id'] ?>" class="btn-acciones" style="background-color: #007bff;" title="Registrar Movimiento / Reasignar">
                                        <i class="fa-solid fa-right-left"></i>
                                    </a>
                                    <a href="historial_movimientos.php?id=<?= $row['id'] ?>" class="btn-acciones" style="background-color: #6f42c1;" title="Ver Historial de Movimientos">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                    No se encontraron equipos o herramientas con los filtros seleccionados.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$stmt->close();
?>
</body>
</html>
