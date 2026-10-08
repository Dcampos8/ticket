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
if (!esAdminOSuperior()) {
    http_response_code(403);
    exit('No autorizado');
}
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
    $condiciones[] = "(COALESCE(u.nombre_completo, t.nombre_completo) LIKE ? OR COALESCE(u.area, t.area) LIKE ? OR t.descripcion LIKE ? OR t.comentario_admin LIKE ?)";
    array_push($parametros, $like, $like, $like, $like);
    $tiposParam .= 'ssss';
}
if ($filtros['area'] !== '') {
    $condiciones[] = "COALESCE(u.area, t.area) = ?";
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
$sql = "SELECT t.*, u.nombre_completo AS usuario_nombre_completo, u.area AS usuario_area
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
$usuariosSoporte = [];
$resSoporte = $conexion->query("SELECT usuario, nombre_completo, rol, modulos_permitidos FROM usuarios WHERE rol IN ('admin','superadmin') ORDER BY nombre_completo");
if ($resSoporte) {
    while ($usuarioSoporte = $resSoporte->fetch_assoc()) {
        if ($usuarioSoporte['rol'] === 'superadmin' || in_array('tickets', parsearModulos($usuarioSoporte['modulos_permitidos'] ?? ''), true)) {
            $usuariosSoporte[] = $usuarioSoporte;
        }
    }
}

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
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__tickets__index.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__index.css') ?>">
<div class="container px-4 mb-5" style="margin-top:0;">
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
                <div class="small text-muted px-2 pb-2 d-lg-none"><i class="fas fa-arrows-left-right me-1"></i>Desliza horizontalmente para ver todas las columnas.</div>
                <table id="tablaTickets" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Área</th>
                            <th>Nombre</th>
                            <th>Comentario</th>
                            <th>Tipo</th>
                            <th>Responsable / prioridad</th>
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
                                <td><span class="area-pill"><?= htmlspecialchars($f['usuario_area'] ?: ($f['area'] ?? 'N/A')) ?></span></td>
                                <td><span class="nombre-usuario"><?= htmlspecialchars($f['usuario_nombre_completo'] ?: ($f['nombre_completo'] ?? 'N/A')) ?></span></td>

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

                                <td class="ticket-asignacion">
                                    <?php if (!$esFinalizado): ?>
                                        <select class="form-select form-select-sm ticket-responsable mb-1" aria-label="Responsable del ticket">
                                            <option value="">Sin asignar</option>
                                            <?php foreach ($usuariosSoporte as $usuarioSoporte): ?>
                                                <option value="<?= htmlspecialchars($usuarioSoporte['usuario'], ENT_QUOTES, 'UTF-8') ?>" <?= ($f['asignado_a'] ?? '') === $usuarioSoporte['usuario'] ? 'selected' : '' ?>><?= htmlspecialchars($usuarioSoporte['nombre_completo'] ?: $usuarioSoporte['usuario']) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <select class="form-select form-select-sm ticket-prioridad" aria-label="Prioridad del ticket">
                                            <?php foreach (['Baja','Normal','Alta','Urgente'] as $prioridad): ?>
                                                <option value="<?= $prioridad ?>" <?= ($f['prioridad'] ?? 'Normal') === $prioridad ? 'selected' : '' ?>><?= $prioridad ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php if (!empty($f['fecha_resolucion']) || in_array($f['estatus'], ['Finalizado','Cancelado'], true)): ?>
                                            <small class="text-muted">Resuelto: <?= !empty($f['fecha_resolucion']) ? date('d/m/y H:i', strtotime($f['fecha_resolucion'])) : 'sin registro previo' ?></small>
                                        <?php elseif (!empty($f['fecha_primera_respuesta'])): ?>
                                            <small class="text-muted">1a respuesta: <?= date('d/m/y H:i', strtotime($f['fecha_primera_respuesta'])) ?></small>
                                        <?php else: ?>
                                            <?php $limiteRespuesta = strtotime($f['fecha_creacion']) + ((int) ($f['objetivo_respuesta_minutos'] ?? 480) * 60); ?>
                                            <small class="<?= time() > $limiteRespuesta ? 'text-danger fw-semibold' : 'text-muted' ?>">
                                                <?= time() > $limiteRespuesta ? 'Respuesta vencida' : 'Responder antes de ' . date('d/m H:i', $limiteRespuesta) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span><?= htmlspecialchars($f['asignado_a'] ?? 'Sin asignar') ?></span><br>
                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($f['prioridad'] ?? 'Normal') ?></span>
                                    <?php endif; ?>
                                </td>

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
                            <tr><td colspan="12" class="text-center py-5 text-muted">
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
    function guardarAsignacion(select) {
        const fila = select.closest('tr');
        const responsable = fila.find('.ticket-responsable').val();
        const prioridad = fila.find('.ticket-prioridad').val();
        $.ajax({
            url: 'backend/actualizar_asignacion.php', type: 'POST', dataType: 'json',
            data: { ticket_id: fila.data('id'), asignado_a: responsable, prioridad: prioridad, csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> },
            error: function(xhr) { alert(xhr.responseJSON?.message || 'No se pudo guardar responsable y prioridad.'); }
        });
    }
    $(document).on('change', '.ticket-responsable, .ticket-prioridad', function() { guardarAsignacion($(this)); });
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
            data: { id: idTicket, estatus: nuevoEstatus, csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> },
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
            data: { ticket_id: id, comentario_admin: texto, csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> },
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
            data: { ticket_id: id, descripcion: texto, csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> },
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
        data: { ticket_id: id, csrf_token: <?= json_encode($_SESSION['csrf_token']) ?> },
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
