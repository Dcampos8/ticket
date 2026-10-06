<?php
/*
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
*/
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// 2. Verificar Sesión y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('tickets');
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// -----------------------------------------------------------------
// Filtros (GET) - búsqueda de texto, área, tipo, estatus y fechas
// -----------------------------------------------------------------
$filtros = [
    'busqueda'    => trim($_GET['busqueda'] ?? ''),
    'area'        => trim($_GET['area'] ?? ''),
    'tipo'        => trim($_GET['tipo'] ?? ''),
    'estatus'     => trim($_GET['estatus'] ?? ''),
    'fecha_desde' => trim($_GET['fecha_desde'] ?? ''),
    'fecha_hasta' => trim($_GET['fecha_hasta'] ?? ''),
];
$hayFiltrosActivos = count(array_filter($filtros, fn($v) => $v !== '')) > 0;

// Construye la cadena de query string preservando los filtros activos.
// $overrides permite cambiar/añadir valores puntuales (ej. la página).
function construirQueryString(array $overrides = []): string {
    $params = array_merge($_GET, $overrides);
    foreach ($params as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        }
    }
    return http_build_query($params);
}

// Paginación
$rango = 3;
$registros_por_pagina = 10;
$pagina = isset($_GET['pagina']) ? max(1, (int)$_GET['pagina']) : 1;
$inicio = ($pagina - 1) * $registros_por_pagina;

// -----------------------------------------------------------------
// Condiciones SQL dinámicas según los filtros activos
// -----------------------------------------------------------------
$condiciones = [];
$parametros  = [];
$tiposParam  = '';

if ($filtros['busqueda'] !== '') {
    $like = '%' . $filtros['busqueda'] . '%';
    $condiciones[] = "(u.nombre_completo LIKE ? OR u.area LIKE ? OR t.descripcion LIKE ? OR t.comentario_admin LIKE ?)";
    array_push($parametros, $like, $like, $like, $like);
    $tiposParam .= 'ssss';
}
if ($filtros['area'] !== '') {
    $condiciones[] = "u.area = ?";
    $parametros[] = $filtros['area'];
    $tiposParam .= 's';
}
if ($filtros['tipo'] !== '') {
    $condiciones[] = "t.tipo_ticket = ?";
    $parametros[] = $filtros['tipo'];
    $tiposParam .= 's';
}
if ($filtros['estatus'] !== '') {
    $condiciones[] = "t.estatus = ?";
    $parametros[] = $filtros['estatus'];
    $tiposParam .= 's';
}
if ($filtros['fecha_desde'] !== '') {
    $condiciones[] = "t.fecha_creacion >= ?";
    $parametros[] = $filtros['fecha_desde'] . ' 00:00:00';
    $tiposParam .= 's';
}
if ($filtros['fecha_hasta'] !== '') {
    $condiciones[] = "t.fecha_creacion <= ?";
    $parametros[] = $filtros['fecha_hasta'] . ' 23:59:59';
    $tiposParam .= 's';
}

$whereSql = $condiciones ? ('WHERE ' . implode(' AND ', $condiciones)) : '';

// Total de registros (respetando filtros)
$totalSql = "SELECT COUNT(*) as total
             FROM tickets t
             LEFT JOIN usuarios u ON t.nombre = u.usuario
             $whereSql";
$stmtTotal = $conexion->prepare($totalSql);
if ($tiposParam !== '') {
    $stmtTotal->bind_param($tiposParam, ...$parametros);
}
$stmtTotal->execute();
$total_row = $stmtTotal->get_result()->fetch_assoc();
$total_registros = (int)$total_row['total'];
$total_paginas = (int)ceil($total_registros / $registros_por_pagina);
$stmtTotal->close();

// 3. Cargar el menú
include(ROOT_PATH . 'admin/menu.php');

// 4. Consulta de Datos (respetando filtros + paginación)
$sql = "SELECT t.*, u.nombre_completo, u.area
        FROM tickets t
        LEFT JOIN usuarios u ON t.nombre = u.usuario
        $whereSql
        ORDER BY t.fecha_creacion DESC
        LIMIT ?, ?";
$tiposData = $tiposParam . 'ii';
$parametrosData = array_merge($parametros, [$inicio, $registros_por_pagina]);
$stmt = $conexion->prepare($sql);
$stmt->bind_param($tiposData, ...$parametrosData);
$stmt->execute();
$resultado = $stmt->get_result();

// Opciones para los selects de filtro (Área / Tipo)
$areasDisponibles = [];
$resAreas = $conexion->query("SELECT DISTINCT area FROM usuarios WHERE area IS NOT NULL AND TRIM(area) <> '' ORDER BY area ASC");
if ($resAreas) {
    while ($row = $resAreas->fetch_assoc()) {
        $areasDisponibles[] = $row['area'];
    }
}
$tiposDisponibles = [];
$resTipos = $conexion->query("SELECT DISTINCT tipo_ticket FROM tickets WHERE tipo_ticket IS NOT NULL AND TRIM(tipo_ticket) <> '' ORDER BY tipo_ticket ASC");
if ($resTipos) {
    while ($row = $resTipos->fetch_assoc()) {
        $tiposDisponibles[] = $row['tipo_ticket'];
    }
}
$estatusDisponibles = ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'];

// Asigna una clase de color al badge de "Tipo" según palabras clave.
// Es solo visual: el valor guardado en BD (tipo_ticket) no se toca.
function claseTipoTicket($tipo) {
    $t = mb_strtolower(trim((string)$tipo));
    if ($t === '') return 'tipo-default';
    if (str_contains($t, 'factura')) return 'tipo-facturas';
    if (str_contains($t, 'soporte')) return 'tipo-soporte';
    if (str_contains($t, 'sistema')) return 'tipo-sistema';
    if (str_contains($t, 'hardware') || str_contains($t, 'equipo')) return 'tipo-hardware';
    if (str_contains($t, 'recursos') || str_contains($t, 'rh')) return 'tipo-rh';
    if (str_contains($t, 'general')) return 'tipo-general';
    return 'tipo-default';
}
?>
<!-- Estilos personalizados -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/listar_tickets.css?v=<?= time() ?>">
<style>
    /* Corrección de Modales y Navbar */
    .modal { z-index: 9999 !important; }
    .modal-backdrop { z-index: 9998 !important; }
    .modal-dialog { margin: auto; }
    /* Este "transparent" es solo para el visor de imágenes (su .modal-body ya
       trae fondo propio con bg-dark). Si se aplica a TODOS los modales, el de
       historial (y cualquier otro futuro) se ve sin fondo. */
    #imageModal .modal-content { background: transparent; border: none; box-shadow: none; }

    :root {
        --ink: #0b1220;
        --sub: #64748b;
        --line: #e2e8f0;
        --surface: #ffffff;
        --bg-1: #eef2ff;
        --bg-2: #ecfeff;
        --primary: #1e3a8a;
        --primary-dark: #172554;
        --primary-soft: #e8edf8;
        --accent: #c9252d;
        --accent-soft: #fbe9ea;
        --danger: #dc2626;
        --danger-soft: #fee2e2;
        --success: #059669;
        --success-soft: #d1fae5;
        --warning: #d97706;
        --warning-soft: #fef3c7;
        --neutral: #94a3b8;
        --radius-lg: 13px;
        --radius-md: 10px;
        --radius-sm: 7px;
    }

    /* Apariencia general */
    body {
        background: #f3f5f8;
        font-family: 'Segoe UI', Roboto, sans-serif;
        color: var(--ink);
    }

    .table-container-custom {
        padding: 28px clamp(12px, 3vw, 32px);
        min-height: calc(100vh - 85px);
        animation: fadeIn 0.5s ease-in-out;
        max-width: 1600px;
        margin: 0 auto;
    }

    /* Encabezado */
    .tickets-header {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        background: var(--primary-dark);
        border-radius: var(--radius-lg);
        padding: 22px 26px;
        margin-bottom: 20px;
        box-shadow: 0 14px 34px rgba(49, 46, 129, 0.22);
    }
    .page-title {
        font-weight: 800;
        color: #fff;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin: 0;
        font-size: 1.5rem;
    }
    .page-title i {
        background: rgba(255, 255, 255, 0.18);
        color: #fff !important;
        width: 44px;
        height: 44px;
        border-radius: 13px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.15rem;
    }
    .subtitle-count {
        color: rgba(255, 255, 255, 0.85);
        font-size: 0.85rem;
        font-weight: 600;
        margin-left: 2px;
        display: block;
    }

    /* Barra de filtros */
    .filtros-card {
        background: rgba(255, 255, 255, 0.97);
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        padding: 18px 20px;
        margin-bottom: 20px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.05);
    }
    .filtros-titulo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 700;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: var(--primary);
        margin-bottom: 12px;
    }
    .filtros-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 12px;
        align-items: end;
    }
    .filtros-grid .campo-busqueda {
        grid-column: span 2;
    }
    .filtros-grid label {
        display: block;
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: var(--sub);
        margin-bottom: 5px;
    }
    .filtros-grid .form-control,
    .filtros-grid .form-select {
        border-radius: var(--radius-sm);
        border: 1px solid var(--line);
        font-size: 0.85rem;
    }
    .filtros-grid .form-control:focus,
    .filtros-grid .form-select:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.14);
    }
    .filtros-acciones {
        display: flex;
        gap: 8px;
    }
    .btn-filtrar {
        background: var(--primary);
        border: none;
        color: #fff;
        font-weight: 700;
        font-size: 0.85rem;
        border-radius: var(--radius-sm);
        padding: 7px 16px;
        white-space: nowrap;
        transition: background 0.15s ease;
    }
    .btn-filtrar:hover { background: var(--primary-dark); color: #fff; }
    .btn-limpiar {
        background: #f1f5f9;
        border: 1px solid var(--line);
        color: var(--sub);
        font-weight: 700;
        font-size: 0.85rem;
        border-radius: var(--radius-sm);
        padding: 7px 14px;
        white-space: nowrap;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }
    .btn-limpiar:hover { background: #e2e8f0; color: var(--ink); }
    .filtros-activas-aviso {
        margin-top: 10px;
        font-size: 0.78rem;
        color: var(--accent);
        font-weight: 600;
    }

    .table-card {
        border: 1px solid var(--line);
        border-radius: var(--radius-lg);
        overflow: hidden;
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(10px);
        box-shadow: 0 10px 30px rgba(16, 24, 40, 0.06) !important;
    }

    /* Estilos de Tabla */
    .table { margin-bottom: 0; }
    .table thead th {
        background: var(--ink) !important;
        color: #ffffff;
        text-transform: uppercase;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.6px;
        border: none;
        padding: 13px 14px;
        white-space: nowrap;
        position: sticky;
        top: 0;
    }
    .table tbody td {
        padding: 13px 14px;
        vertical-align: middle;
        border-bottom: 1px solid var(--line);
    }
    .table tbody tr { transition: background-color 0.2s ease; }
    .table tbody tr:last-child td { border-bottom: none; }
    .table tbody tr:hover { background-color: #f5f6ff !important; }

    .id-pill {
        display: inline-block;
        min-width: 34px;
        text-align: center;
        padding: 3px 8px;
        border-radius: 8px;
        background: var(--primary-soft);
        color: var(--primary);
        font-weight: 700;
        font-size: 0.8rem;
    }
    .area-pill {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 20px;
        background: var(--accent-soft);
        color: #0f766e;
        font-size: 0.72rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
    }
    .nombre-usuario { font-weight: 600; color: var(--ink); }

    .tipo-badge {
        display: inline-block;
        border: 1px solid transparent;
        font-weight: 700;
        font-size: 0.7rem;
        letter-spacing: 0.2px;
        padding: 5px 12px;
        border-radius: 30px;
        white-space: nowrap;
        line-height: 1.3;
    }
    /* Colores por tipo de ticket (fallback = gris neutro) */
    .tipo-default   { background: #f1f5f9; color: #475467; }
    .tipo-soporte   { background: #e0f2fe; color: #0369a1; }
    .tipo-facturas  { background: #ffedd5; color: #c2410c; }
    .tipo-sistema   { background: #ede9fe; color: #6d28d9; }
    .tipo-hardware  { background: #fce7f3; color: #be185d; }
    .tipo-rh        { background: #dcfce7; color: #15803d; }
    .tipo-general   { background: #e0e7ff; color: #4338ca; }

    /* Inputs y Textareas */
    .form-control-sm, .form-select-sm {
        border-radius: 9px;
        border: 1px solid #dce1e7;
        transition: all 0.2s;
        font-size: 0.85rem;
    }
    .form-control-sm:focus, .form-select-sm:focus {
        background: #fff !important;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(67, 56, 202, 0.12);
    }

    /* Estatus */
    .status-badge {
        display: inline-block;
        width: 100%;
        text-align: center;
        padding: 6px 8px;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.72rem;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }
    .status-finalizado { background: var(--success-soft); color: var(--success); }
    .status-cancelado  { background: #f1f5f9; color: #475467; }

    /* Texto expandible (Ver más / Ver menos) */
    .texto-expandible { position: relative; }
    .texto-clamp {
        max-height: 3.6em;
        overflow: hidden;
        line-height: 1.2em;
        color: #475467;
        font-size: 0.82rem;
        white-space: pre-line;
        transition: max-height 0.25s ease;
    }
    .texto-clamp.expandido { max-height: 1000px; }
    .btn-ver-mas {
        display: inline-block;
        margin-top: 4px;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--primary);
        cursor: pointer;
        user-select: none;
    }
    .btn-ver-mas:hover { text-decoration: underline; }

    /* Adjuntos */
    .adjunto-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: var(--primary-soft);
        color: var(--primary) !important;
        transition: transform 0.15s ease;
    }
    .adjunto-link:hover { transform: scale(1.08); }
    .adjunto-link.admin { background: var(--success-soft); color: var(--success) !important; }
    .adjunto-upload-label { color: var(--sub); cursor: pointer; margin-left: 4px; }
    .adjunto-upload-label:hover { color: var(--primary); }

    /* Animaciones de Feedback */
    .saving-row { background-color: #fff9db !important; }
    .updated-flash { animation: flashGreen 1.5s; }
    @keyframes flashGreen {
        0% { background-color: transparent; }
        30% { background-color: #d4edda; }
        100% { background-color: transparent; }
    }
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .smaller { font-size: 0.75rem; color: var(--sub); }
    .fecha-mod {
        font-size: 0.68rem;
        color: var(--sub);
        margin-top: 3px;
        white-space: nowrap;
    }
    .historial-link {
        cursor: pointer;
        color: var(--sub);
        margin-left: 6px;
        margin-top: 2px;
        flex-shrink: 0;
    }
    .historial-link:hover { color: var(--primary); }
    .historial-item {
        border-bottom: 1px solid var(--line);
        padding-bottom: 10px;
        margin-bottom: 10px;
    }
    .historial-item:last-child { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }
    .historial-item .historial-admin { font-weight: 700; color: var(--ink); font-size: 0.85rem; }
    .historial-item .historial-fecha { font-size: 0.72rem; color: var(--sub); }
    .historial-item .historial-texto { font-size: 0.85rem; color: #344054; white-space: pre-line; margin-top: 2px; }

    /* Paginación */
    .pagination .page-link {
        border: none;
        color: var(--sub);
        font-weight: 600;
        border-radius: 8px !important;
        margin: 0 2px;
    }
    .pagination .page-item.active .page-link { background: var(--primary); color: #fff; }

    /* Responsive */
    @media (max-width: 991px) {
        .table-container-custom { padding: 18px 12px; }
        .tickets-header { padding: 18px; }
        .page-title { font-size: 1.2rem; }
        .filtros-grid .campo-busqueda { grid-column: 1 / -1; }
    }
    @media (max-width: 576px) {
        .tickets-header { flex-direction: column; align-items: flex-start; }
        .filtros-grid { grid-template-columns: 1fr 1fr; }
        .filtros-acciones { width: 100%; }
        .filtros-acciones .btn-filtrar,
        .filtros-acciones .btn-limpiar { flex: 1; text-align: center; }
    }
</style>
<div class="container-fluid px-4 mb-5" style="margin-top:0;">
    <div class="table-container-custom">
        <div class="tickets-header">
            <div>
                <h1 class="page-title">
                    <i class="fas fa-ticket-alt"></i> Listado de Tickets
                </h1>
                <span class="subtitle-count">
                    <?php if ($hayFiltrosActivos): ?>
                        Mostrando <?= (int)$total_registros ?> resultado(s) filtrado(s)
                    <?php else: ?>
                        <?= (int)$total_registros ?> en total
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <!-- Barra de filtros -->
        <div class="filtros-card">
            <div class="filtros-titulo"><i class="fas fa-sliders-h"></i> Filtrar tickets</div>
            <form method="GET" action="" id="formFiltros">
                <div class="filtros-grid">
                    <div class="campo-busqueda">
                        <label for="f_busqueda">Búsqueda</label>
                        <input type="text" id="f_busqueda" name="busqueda" class="form-control form-control-sm"
                            placeholder="Nombre, área, descripción o respuesta..."
                            value="<?= htmlspecialchars($filtros['busqueda']) ?>">
                    </div>
                    <div>
                        <label for="f_area">Área</label>
                        <select id="f_area" name="area" class="form-select form-select-sm auto-submit">
                            <option value="">Todas</option>
                            <?php foreach ($areasDisponibles as $a): ?>
                                <option value="<?= htmlspecialchars($a) ?>" <?= $filtros['area'] === $a ? 'selected' : '' ?>><?= htmlspecialchars($a) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="f_tipo">Tipo</label>
                        <select id="f_tipo" name="tipo" class="form-select form-select-sm auto-submit">
                            <option value="">Todos</option>
                            <?php foreach ($tiposDisponibles as $t): ?>
                                <option value="<?= htmlspecialchars($t) ?>" <?= $filtros['tipo'] === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="f_estatus">Estatus</label>
                        <select id="f_estatus" name="estatus" class="form-select form-select-sm auto-submit">
                            <option value="">Todos</option>
                            <?php foreach ($estatusDisponibles as $e): ?>
                                <option value="<?= htmlspecialchars($e) ?>" <?= $filtros['estatus'] === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="f_desde">Desde</label>
                        <input type="date" id="f_desde" name="fecha_desde" class="form-control form-control-sm auto-submit" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>">
                    </div>
                    <div>
                        <label for="f_hasta">Hasta</label>
                        <input type="date" id="f_hasta" name="fecha_hasta" class="form-control form-control-sm auto-submit" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>">
                    </div>
                    <div class="filtros-acciones">
                        <button type="submit" class="btn-filtrar"><i class="fas fa-search me-1"></i>Buscar</button>
                        <?php if ($hayFiltrosActivos): ?>
                            <a href="?" class="btn-limpiar"><i class="fas fa-times"></i>Limpiar</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
            <?php if ($hayFiltrosActivos): ?>
                <div class="filtros-activas-aviso"><i class="fas fa-check-circle me-1"></i>Filtros activos</div>
            <?php endif; ?>
        </div>

        <div class="table-card">
            <div class="table-responsive p-3">
                <table id="tablaTickets" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Área</th>
                            <th>Nombre</th>
                            <th>Comentario</th>
                            <th>Tipo</th>
                            <th>User Adj.</th>
                            <th>Admin Adj.</th>
                            <th>Estatus</th>
                            <th>Fecha</th>
                            <th>Respuesta Admin</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($f = $resultado->fetch_assoc()): ?>
                            <?php
                                $descSegura = (string)($f['descripcion'] ?? '');
                                $comSeguro = (string)($f['comentario_admin'] ?? '');
                                $textoHTML = nl2br(htmlspecialchars(trim($descSegura)));
                                $comentarioHTML = nl2br(htmlspecialchars(trim($comSeguro)));
                                $esFinalizado = in_array($f['estatus'], ['Finalizado', 'Cancelado']);
                                // Umbrales para decidir si mostramos "Ver más" (texto largo o con varias líneas/lista)
                                $descLineas = substr_count(trim($descSegura), "\n") + 1;
                                $descLarga = (mb_strlen(trim($descSegura)) > 120) || ($descLineas > 3);
                                $comLineas = substr_count(trim($comSeguro), "\n") + 1;
                                $comLargo = (mb_strlen(trim($comSeguro)) > 120) || ($comLineas > 3);
                                // Fecha de última actualización (solo si es distinta a la de creación)
                                $tieneModificacion = !empty($f['fecha_modificacion'])
                                    && strtotime($f['fecha_modificacion']) > strtotime($f['fecha_creacion']);
                            ?>
                            <tr data-id="<?= $f['id'] ?>">
                                <td><span class="id-pill">#<?= $f['id'] ?></span></td>
                                <td><span class="area-pill"><?= htmlspecialchars($f['area'] ?? 'N/A') ?></span></td>
                                <td><span class="nombre-usuario"><?= htmlspecialchars($f['nombre_completo'] ?? 'N/A') ?></span></td>

                                <td style="min-width: 200px;">
                                    <?php if ($esFinalizado): ?>
                                        <?php if ($descLarga): ?>
                                            <div class="texto-expandible">
                                                <div class="texto-clamp"><?= $textoHTML ?></div>
                                                <span class="btn-ver-mas" onclick="toggleTexto(this)">Ver más</span>
                                            </div>
                                        <?php else: ?>
                                            <div class="smaller"><?= $textoHTML ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <textarea class="form-control form-control-sm desc-text" rows="2" data-id="<?= $f['id'] ?>"><?= htmlspecialchars(trim($descSegura)) ?></textarea>
                                    <?php endif; ?>
                                </td>

                                <td><span class="tipo-badge <?= claseTipoTicket($f['tipo_ticket'] ?? '') ?>"><?= htmlspecialchars($f['tipo_ticket'] ?? 'N/A') ?></span></td>

                                <!-- Adjunto Usuario -->
                                <td class="text-center">
                                    <?php if (!empty($f['imagen'])): ?>
                                        <a href="javascript:void(0)" class="adjunto-link" onclick="verImagen('../../uploads/<?= htmlspecialchars($f['imagen']) ?>')" title="Ver adjunto del usuario">
                                            <i class="fas fa-image"></i>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted smaller">---</span>
                                    <?php endif; ?>
                                </td>

                                <!-- Adjunto Admin -->
                                <td class="text-center">
                                    <?php if (!empty($f['imagen_admin'])): ?>
                                        <a href="javascript:void(0)" class="adjunto-link admin" onclick="verImagen('../../uploads/<?= htmlspecialchars($f['imagen_admin']) ?>')" title="Ver adjunto del admin">
                                            <i class="fas fa-paperclip"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (($_SESSION['rol'] ?? '') === 'admin' && !$esFinalizado): ?>
                                        <form action="backend/subir_imagen_admin_ticket.php" method="POST" enctype="multipart/form-data" class="d-inline">
                                            <input type="hidden" name="ticket_id" value="<?= (int) $f['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                            <label for="img_adm_<?= $f['id'] ?>" class="adjunto-upload-label" title="Subir adjunto de admin">
                                                <i class="fas fa-upload"></i>
                                            </label>
                                            <input type="file" name="imagen_admin" id="img_adm_<?= $f['id'] ?>" hidden onchange="this.form.submit()">
                                        </form>
                                    <?php endif; ?>
                                </td>

                                <!-- Estatus -->
                                <td>
                                    <?php if ($esFinalizado): ?>
                                        <span class="status-badge <?= $f['estatus'] == 'Finalizado' ? 'status-finalizado' : 'status-cancelado' ?>"><?= $f['estatus'] ?></span>
                                    <?php else: ?>
                                        <select class="estatus form-select form-select-sm">
                                            <option value="Pendiente" <?= $f['estatus'] == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="En proceso" <?= $f['estatus'] == 'En proceso' ? 'selected' : '' ?>>En proceso</option>
                                            <option value="Finalizado" <?= $f['estatus'] == 'Finalizado' ? 'selected' : '' ?>>Finalizado</option>
                                            <option value="Cancelado" <?= $f['estatus'] == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                        </select>
                                    <?php endif; ?>
                                </td>
                                <td class="smaller">
                                    <?= date("d/m/y H:i", strtotime($f['fecha_creacion'])) ?>
                                    <?php if ($tieneModificacion): ?>
                                        <div class="fecha-mod" title="Última actualización">
                                            <i class="fas fa-clock"></i> <?= date("d/m/y H:i", strtotime($f['fecha_modificacion'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <!-- Respuesta Admin -->
                                <td style="min-width: 200px;">
                                    <div class="d-flex align-items-start">
                                        <?php if ($esFinalizado): ?>
                                            <?php if ($comSeguro === ''): ?>
                                                <span class="smaller">-</span>
                                            <?php elseif ($comLargo): ?>
                                                <div class="texto-expandible w-100">
                                                    <div class="texto-clamp"><?= $comentarioHTML ?></div>
                                                    <span class="btn-ver-mas" onclick="toggleTexto(this)">Ver más</span>
                                                </div>
                                            <?php else: ?>
                                                <div class="smaller"><?= $comentarioHTML ?></div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <textarea class="form-control form-control-sm comentario-text" rows="2" data-id="<?= $f['id'] ?>"><?= htmlspecialchars(trim($comSeguro)) ?></textarea>
                                            <span class="ms-1 text-success d-none check-icon">✅</span>
                                        <?php endif; ?>
                                        <span class="historial-link" title="Ver historial de comentarios" onclick="verHistorial(<?= $f['id'] ?>)">
                                            <i class="fas fa-history"></i>
                                        </span>
                                    </div>
                                </td>

                                <!-- Acciones -->
                                <td>
                                    <button onclick="eliminarTicket(<?= $f['id'] ?>)" class="btn btn-link text-danger p-0" title="Eliminar Ticket">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="11" class="text-center py-5 text-muted">
                                <?= $hayFiltrosActivos ? 'No hay tickets que coincidan con los filtros aplicados.' : 'No hay tickets registrados.' ?>
                            </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Paginación -->
        <div class="d-flex justify-content-end mt-3">
            <nav>
                <ul class="pagination">
                    <li class="page-item <?= ($pagina <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= construirQueryString(['pagina' => $pagina - 1]) ?>">Anterior</a>
                    </li>

                    <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                        <?php if ($i == 1 || $i == $total_paginas || ($i >= $pagina - $rango && $i <= $pagina + $rango)): ?>
                            <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= construirQueryString(['pagina' => $i]) ?>"><?= $i ?></a>
                            </li>
                        <?php elseif ($i == $pagina - $rango - 1 || $i == $pagina + $rango + 1): ?>
                            <li class="page-item disabled"><span class="page-link">...</span></li>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <li class="page-item <?= ($pagina >= $total_paginas) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= construirQueryString(['pagina' => $pagina + 1]) ?>">Siguiente</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>
    <!-- Modal Visor de Imagen -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content">
        <div class="modal-body text-center bg-dark rounded d-flex align-items-center justify-content-center position-relative">
            <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
            <img src="" id="imgPreview" style="max-width:100%; max-height:90vh; border-radius:5px; object-fit:contain;">
        </div>
        </div>
    </div>
    </div>
    <!-- Modal Historial de Comentarios -->
    <div class="modal fade" id="historialModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title"><i class="fas fa-history me-2"></i>Historial de comentarios</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="historialBody">
            <p class="text-muted text-center mb-0">Cargando...</p>
        </div>
        </div>
    </div>
    </div>
</div>
<!-- Scripts (Única inclusión limpia) -->
<script>
// Filtros: los selects y fechas se auto-envían al cambiar de valor
document.querySelectorAll('#formFiltros .auto-submit').forEach(function (el) {
    el.addEventListener('change', function () {
        document.getElementById('formFiltros').submit();
    });
});

// Modal Imagen
function verImagen(url) {
    $('#imgPreview').attr('src', url);
    let modal = new bootstrap.Modal(document.getElementById('imageModal'));
    modal.show();
}
// Ver más / Ver menos para textos largos en tickets finalizados
function toggleTexto(link) {
    let contenedor = link.previousElementSibling;
    let expandido = contenedor.classList.toggle('expandido');
    link.textContent = expandido ? 'Ver menos' : 'Ver más';
}
// Modal Historial de Comentarios
function escapeHtml(texto) {
    return $('<div>').text(texto).html();
}
function verHistorial(id) {
    let modalEl = document.getElementById('historialModal');
    let modal = new bootstrap.Modal(modalEl);
    $('#historialBody').html('<p class="text-muted text-center mb-0">Cargando...</p>');
    modal.show();
    $.ajax({
        url: 'backend/obtener_historial_comentarios.php',
        type: 'GET',
        data: { ticket_id: id },
        dataType: 'json',
        success: function(r) {
            if (r.status === 'ok' && r.historial.length > 0) {
                let html = '';
                r.historial.forEach(function(h) {
                    html += '<div class="historial-item">'
                        + '<div class="d-flex justify-content-between align-items-baseline">'
                        + '<span class="historial-admin">' + escapeHtml(h.admin_usuario) + '</span>'
                        + '<span class="historial-fecha">' + escapeHtml(h.fecha_fmt) + '</span>'
                        + '</div>'
                        + '<div class="historial-texto">' + escapeHtml(h.comentario) + '</div>'
                        + '</div>';
                });
                $('#historialBody').html(html);
            } else {
                $('#historialBody').html('<p class="text-muted text-center mb-0">Sin historial de comentarios todavía.</p>');
            }
        },
        error: function(xhr) {
            console.error("ERROR AJAX:", xhr.responseText);
            $('#historialBody').html('<p class="text-danger text-center mb-0">No se pudo cargar el historial.</p>');
        }
    });
}
$(document).ready(function() {
    // 1. ACTUALIZAR ESTATUS
    $(document).on('change', '.estatus', function() {
        let select = $(this);
        let fila = select.closest('tr');
        let idTicket = fila.data('id');
        let nuevoEstatus = select.val();
        fila.addClass('saving-row');
        $.ajax({
            url: 'backend/actualizar_ticket_inline.php',
            type: 'POST',
            data: { id: idTicket, estatus: nuevoEstatus },
            dataType: 'json',
            success: function(response) {
                fila.removeClass('saving-row');
                if (response.success || response.status === 'ok') {
                    fila.addClass('updated-flash');
                    setTimeout(() => fila.removeClass('updated-flash'), 1500);
                    if (nuevoEstatus === 'Finalizado' || nuevoEstatus === 'Cancelado') {
                        location.reload();
                    }
                } else {
                    alert("Error al actualizar estatus: " + (response.message || 'Desconocido'));
                }
            },
            error: function(xhr) {
                fila.removeClass('saving-row');
                console.error("ERROR AJAX:", xhr.responseText);
                alert("Error en la petición de estatus.");
            }
        });
    });
    // 2. ACTUALIZAR COMENTARIO ADMIN
    $(document).on('blur', '.comentario-text', function() {
        let textarea = $(this);
        let fila = textarea.closest('tr');
        let id = textarea.data('id');
        let texto = textarea.val().trim();
        let check = textarea.siblings('.check-icon');
        $.ajax({
            url: 'backend/actualizar_comentario.php',
            type: 'POST',
            data: { ticket_id: id, comentario_admin: texto },
            dataType: 'json',
            success: function(r) {
                if (r.status === 'ok' || r.success) {
                    check.removeClass('d-none').fadeIn();
                    fila.addClass('updated-flash');
                    setTimeout(() => {
                        check.fadeOut();
                        fila.removeClass('updated-flash');
                    }, 2000);
                } else {
                    alert("Error guardando comentario: " + (r.message || 'Error inesperado'));
                }
            },
            error: function(xhr) {
                console.error("ERROR AJAX:", xhr.responseText);
                alert("Error al guardar comentario.");
            }
        });
    });
    // 3. ACTUALIZAR DESCRIPCIÓN (Opcional)
    $(document).on('blur', '.desc-text', function() {
        let textarea = $(this);
        let id = textarea.data('id');
        let texto = textarea.val().trim();
        $.ajax({
            url: 'backend/actualizar_descripcion.php',
            type: 'POST',
            data: { ticket_id: id, descripcion: texto },
            dataType: 'json',
            success: function(r) {
                if (r.status !== 'ok' && !r.success) {
                    console.warn("No se actualizó la descripción:", r.message);
                }
            }
        });
    });
});
// 4. ELIMINAR TICKET
function eliminarTicket(id) {
    if (!confirm("¿Eliminar ticket #" + id + "?")) return;
    let fila = $('tr[data-id="' + id + '"]');
    $.ajax({
        url: 'backend/eliminar_ticket.php',
        type: 'POST',
        data: { ticket_id: id },
        dataType: 'json',
        success: function(r) {
            if (r.status === 'ok' || r.success) {
                fila.fadeOut(400, function() { $(this).remove(); });
            } else {
                alert("Error al eliminar: " + (r.message || 'Error desconocido'));
            }
        },
        error: function(xhr) {
            console.error("ERROR AJAX:", xhr.responseText);
            alert("Error al intentar eliminar.");
        }
    });
}
</script>
