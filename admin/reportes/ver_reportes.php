<?php
date_default_timezone_set('America/Mexico_City');
// 1. Cargar la configuración global (Ajusta la ruta si config.php está en otro nivel)
require_once(__DIR__ . '/../../config.php');

// 2. Conexión a la base de datos (Usando ROOT_PATH definido en config.php)
require_once(ROOT_PATH . 'backend/conexion.php');
require_once(ROOT_PATH . 'shared/ticket_horarios.php');

// 3. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3b. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('reportes');

// 4. Cargar el menú (Usando ROOT_PATH para asegurar que lo encuentre)
include(ROOT_PATH . 'admin/menu.php');


// Valida fechas como ISO antes de usarlas en consultas.
$validarFecha = static function ($valor): bool {
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return false;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return $fecha && $fecha->format('Y-m-d') === $valor;
};
$fecha_inicio = $validarFecha($_GET['fecha_inicio'] ?? '') ? $_GET['fecha_inicio'] : '';
$fecha_fin = $validarFecha($_GET['fecha_fin'] ?? '') ? $_GET['fecha_fin'] : '';
$aplicarFiltroFechas = $fecha_inicio !== '' && $fecha_fin !== '';
$sql = "SELECT t.id, t.nombre, t.estatus, t.descripcion, t.fecha_creacion, u.area,
               CASE WHEN t.estatus IN ('Finalizado','Cerrado') THEN COALESCE(t.fecha_resolucion, t.fecha_modificacion) ELSE NULL END AS fecha_cierre,
               t.fecha_modificacion
        FROM tickets t LEFT JOIN usuarios u ON t.nombre = u.usuario" . ($aplicarFiltroFechas ? " WHERE t.fecha_creacion >= ? AND t.fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)" : '') . " ORDER BY t.fecha_creacion DESC";
$stmtReporte = $conexion->prepare($sql);
if ($aplicarFiltroFechas) $stmtReporte->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmtReporte->execute();
$resultado = $stmtReporte->get_result();
$sqlResumen = "SELECT COUNT(*) AS total,
    SUM(estatus IN ('Finalizado','Cerrado')) AS cerrados,
    SUM(estatus IN ('Pendiente','En proceso')) AS abiertos
    FROM tickets" . ($aplicarFiltroFechas ? " WHERE fecha_creacion >= ? AND fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)" : '');
$stmtResumen = $conexion->prepare($sqlResumen);
if ($aplicarFiltroFechas) $stmtResumen->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmtResumen->execute();
$resumen = $stmtResumen->get_result()->fetch_assoc() ?: [];
$totalResumen = (int) ($resumen['total'] ?? 0);
$cerradosResumen = (int) ($resumen['cerrados'] ?? 0);
$abiertosResumen = (int) ($resumen['abiertos'] ?? 0);
$horariosTicket = cargarHorariosTicket($conexion);
$sqlMetricasSla = "SELECT fecha_creacion, COALESCE(fecha_resolucion, fecha_modificacion) AS fecha_cierre, asignado_a, objetivo_resolucion_minutos FROM tickets WHERE estatus IN ('Finalizado','Cerrado')" . ($aplicarFiltroFechas ? ' AND fecha_creacion >= ? AND fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)' : '');
$stmtMetricasSla = $conexion->prepare($sqlMetricasSla);
if ($aplicarFiltroFechas) $stmtMetricasSla->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmtMetricasSla->execute();
$minutosTotalesSla = 0;
$ticketsMedidosSla = 0;
$ticketsDentroSla = 0;
foreach ($stmtMetricasSla->get_result() as $ticketMetrica) {
    if (empty($ticketMetrica['fecha_cierre'])) continue;
    $horarioTicket = horarioParaTicket($horariosTicket, !empty($ticketMetrica['asignado_a']) ? (string) $ticketMetrica['asignado_a'] : null);
    $minutosResolucion = minutosLaborablesEntre(new DateTimeImmutable($ticketMetrica['fecha_creacion']), new DateTimeImmutable($ticketMetrica['fecha_cierre']), $horarioTicket);
    $minutosTotalesSla += $minutosResolucion;
    $ticketsMedidosSla++;
    if ($minutosResolucion <= (int) $ticketMetrica['objetivo_resolucion_minutos']) $ticketsDentroSla++;
}
$stmtMetricasSla->close();
$cumplimientoSla = $cerradosResumen > 0 ? round(($ticketsDentroSla / $cerradosResumen) * 100) : 0;
$minutosPromedio = $ticketsMedidosSla > 0 ? (int) round($minutosTotalesSla / $ticketsMedidosSla) : 0;
$tiempoPromedio = $minutosPromedio >= 60 ? floor($minutosPromedio / 60) . ' h ' . ($minutosPromedio % 60) . ' min' : $minutosPromedio . ' min';
$consultarAgrupacion = static function (string $campo) use ($conexion, $aplicarFiltroFechas, $fecha_inicio, $fecha_fin): array {
    $sql = "SELECT {$campo} AS etiqueta, COUNT(*) AS total FROM tickets" . ($aplicarFiltroFechas ? ' WHERE fecha_creacion >= ? AND fecha_creacion < DATE_ADD(?, INTERVAL 1 DAY)' : '') . " GROUP BY {$campo} ORDER BY total DESC, etiqueta ASC LIMIT 6";
    $stmt = $conexion->prepare($sql);
    if ($aplicarFiltroFechas) $stmt->bind_param('ss', $fecha_inicio, $fecha_fin);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
};
$resumenEstados = $consultarAgrupacion('estatus');
$resumenTipos = $consultarAgrupacion('tipo_ticket');
?>

<!-- DataTables + estilos -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__reportes__ver_reportes.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__reportes__ver_reportes.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="content-area reportes-page">
  <header class="reports-heading"><div><span>ANÁLISIS DE OPERACIÓN</span><h2><i class="fa-solid fa-chart-column"></i> Reportes de tickets</h2><p>Actividad, tiempos de atención y cumplimiento del Service Desk.</p></div><span class="reports-period"><?= $aplicarFiltroFechas ? htmlspecialchars(date('d/m/Y', strtotime($fecha_inicio)) . ' — ' . date('d/m/Y', strtotime($fecha_fin))) : 'Todo el periodo' ?></span></header>

  <section class="report-kpis" aria-label="Indicadores del reporte">
    <article><span class="kpi-icon kpi-total"><i class="fa-solid fa-ticket"></i></span><div><small>Tickets creados</small><strong><?= $totalResumen ?></strong></div></article>
    <article><span class="kpi-icon kpi-open"><i class="fa-solid fa-inbox"></i></span><div><small>Abiertos actualmente</small><strong><?= $abiertosResumen ?></strong></div></article>
    <article><span class="kpi-icon kpi-time"><i class="fa-solid fa-stopwatch"></i></span><div><small>Tiempo promedio de resolución en horario</small><strong><?= htmlspecialchars($tiempoPromedio) ?></strong></div></article>
    <article><span class="kpi-icon kpi-sla"><i class="fa-solid fa-bullseye"></i></span><div><small>Resoluciones dentro del SLA</small><strong><?= $cumplimientoSla ?>%</strong><em><?= $cerradosResumen ?> tickets cerrados</em></div></article>
  </section>

  <section class="report-breakdowns" aria-label="Distribución de tickets">
    <?php foreach ([['Estados', $resumenEstados, 'estatus'], ['Tipos de solicitud', $resumenTipos, 'tipo']] as [$titulo, $items, $grupo]): ?>
    <article class="report-breakdown"><h3><?= htmlspecialchars($titulo) ?></h3>
      <?php if (!$items): ?><p class="text-muted mb-0">No hay datos para mostrar.</p><?php endif; ?>
      <?php foreach ($items as $item): $cantidad = (int) $item['total']; $porcentaje = $totalResumen > 0 ? min(100, round($cantidad * 100 / $totalResumen)) : 0; ?>
      <div class="report-bar-row"><div><span><?= htmlspecialchars($item['etiqueta'] ?: 'Sin clasificar') ?></span><strong><?= $cantidad ?></strong></div><div class="report-bar"><i class="bar-<?= $grupo ?>" style="--bar-width:<?= $porcentaje ?>%"></i></div></div>
      <?php endforeach; ?>
    </article>
    <?php endforeach; ?>
  </section>

  <form method="GET" class="filter-form reports-filter">
    <div><label for="fecha_inicio">Desde</label><input id="fecha_inicio" type="date" class="form-control" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>"></div>
    <div><label for="fecha_fin">Hasta</label><input id="fecha_fin" type="date" class="form-control" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>"></div>
    <button type="submit" class="btn btn-danger"><i class="fa-solid fa-filter me-2"></i>Aplicar fechas</button>
    <a href="ver_reportes.php" class="btn btn-outline-secondary">Limpiar</a>
  </form>

  <div class="table-responsive">
    <table id="tablaReportes" class="table table-striped table-bordered align-middle text-center">
      <thead>
        <tr>
          <th>#</th>
          <th>Usuario</th>
          <th>Área</th>
          <th>Descripción</th>
          <th>Estatus</th>
          <th>Fecha Creación</th>
          <th>Fecha de cierre</th>
          <th>Tiempo de atención</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($resultado->num_rows > 0): ?>
          <?php while ($r = $resultado->fetch_assoc()): ?>
            <tr>
              <td><?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['nombre']) ?></td>
              <td><?= htmlspecialchars($r['area'] ?? 'N/A') ?></td>
              <td class="descripcion"><?= nl2br(htmlspecialchars($r['descripcion'])) ?></td>
              <td><?= htmlspecialchars($r['estatus'] ?? 'N/A') ?></td>
              <td class="fecha"><?= date('d/m/Y H:i', strtotime($r['fecha_creacion'])) ?></td>
              <td class="fecha"><?= $r['fecha_cierre'] ? date('d/m/Y H:i', strtotime($r['fecha_cierre'])) : '—' ?></td>
              <?php
                $creacion = new DateTime($r['fecha_creacion']);
                $finAtencion = $r['fecha_cierre'] ? new DateTime($r['fecha_cierre']) : new DateTime();
                $diferencia = $creacion->diff($finAtencion);
            
                $horas = $diferencia->days * 24 + $diferencia->h;
                $minutos = $diferencia->i;
                $tiempo = "{$horas}h {$minutos}m";
              ?>
              <td><?= $r['fecha_cierre'] ? $tiempo : $tiempo . ' (en curso)' ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="8">No hay reportes en ese rango de fechas.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<!-- Scripts de DataTables y exportación -->

<script>
  $(document).ready(function () {
    $('#tablaReportes').DataTable({
      language: {
        url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
      },
      pageLength: 25,
      responsive: true,
      dom: 'Bfrtip', // Mostrar botones arriba
      buttons: [
          {
            extend: 'excelHtml5',
            text: '📁 Exportar a Excel',
            className: 'btn btn-success btn-m',
            filename: function() {
              const params = new URLSearchParams(window.location.search);
              const inicio = params.get('fecha_inicio') || 'inicio';
              const fin = params.get('fecha_fin') || 'fin';
              return `ACTIVIDADES SISTEMAS ${inicio} al ${fin}`;
            }
          }
        ]

    });
  });
</script>
