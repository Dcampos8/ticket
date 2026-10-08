<?php
date_default_timezone_set('America/Mexico_City');
// 1. Cargar la configuración global (Ajusta la ruta si config.php está en otro nivel)
require_once(__DIR__ . '/../../config.php');

// 2. Conexión a la base de datos (Usando ROOT_PATH definido en config.php)
require_once(ROOT_PATH . 'backend/conexion.php');

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
$sql = "SELECT t.id, t.nombre, t.estatus, t.descripcion, t.fecha_creacion, u.area, t.fecha_modificacion
        FROM tickets t LEFT JOIN usuarios u ON t.nombre = u.usuario" . ($aplicarFiltroFechas ? " WHERE DATE(t.fecha_creacion) BETWEEN ? AND ?" : '') . " ORDER BY t.fecha_creacion DESC";
$stmtReporte = $conexion->prepare($sql);
if ($aplicarFiltroFechas) $stmtReporte->bind_param('ss', $fecha_inicio, $fecha_fin);
$stmtReporte->execute();
$resultado = $stmtReporte->get_result();?>

<!-- DataTables + estilos -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__reportes__ver_reportes.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__reportes__ver_reportes.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="content-area">
  <h2>📄 Reportes Generales</h2>

  <form method="GET" class="filter-form" style="display: inline-flex;">
    <label class="m-2">Desde:</label>
    <input type="date" class="form-control m-3" name="fecha_inicio" value="<?= htmlspecialchars($fecha_inicio) ?>">
    <label class="m-2">Hasta:</label>
    <input type="date" class="form-control m-3" name="fecha_fin" value="<?= htmlspecialchars($fecha_fin) ?>">
    <button type="submit" class="btn btn-primary btn-sm m-3">Filtrar</button>
    <a href="ver_reportes.php" class="btn btn-outline-secondary btn-sm m-3">Limpiar</a>
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
          <th>Fecha Finalizado</th>
          <th>Tiempo Transcurrido</th>
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
              <td class="fecha"><?= date('d/m/Y H:i', strtotime($r['fecha_modificacion'])) ?></td>
              <?php
                $creacion = new DateTime($r['fecha_creacion']);
                $modificacion = new DateTime($r['fecha_modificacion']);
                $diferencia = $creacion->diff($modificacion);
            
                $horas = $diferencia->days * 24 + $diferencia->h;
                $minutos = $diferencia->i;
                $tiempo = "{$horas}h {$minutos}m";
              ?>
            <td><?= $tiempo ?></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="5">No hay reportes en ese rango de fechas.</td></tr>
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
      pageLength: 10,
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
