<?php
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('calificaciones');

require_once ROOT_PATH . 'admin/menu.php';

// Marcamos como vistas todas las que estén pendientes al entrar a esta pantalla
mysqli_query($conexion, "UPDATE encuestas_satisfaccion SET vista_admin = 1 WHERE vista_admin = 0");

// Filtro opcional por tipo
$filtro = $_GET['tipo'] ?? 'todos';
$where = '';
if ($filtro === 'ticket' || $filtro === 'mantenimiento') {
    $where = "WHERE e.tipo = '" . mysqli_real_escape_string($conexion, $filtro) . "'";
}

$sql = "
    SELECT e.*,
           t.descripcion AS ticket_descripcion
    FROM encuestas_satisfaccion e
    LEFT JOIN tickets t ON e.tipo = 'ticket' AND t.id = e.referencia_id
    $where
    ORDER BY e.fecha_evaluacion DESC
";
$res = mysqli_query($conexion, $sql);

$stats = mysqli_fetch_assoc(mysqli_query($conexion, "
    SELECT
        COUNT(*) AS total,
        ROUND(AVG(calificacion), 2) AS promedio,
        SUM(resuelto = 'No') AS no_resueltos
    FROM encuestas_satisfaccion
"));
?>

<style>
.estrellas-view i { color: #f5b301; }
.estrellas-view i.vacia { color: #e2e8f0; }
.card-stat { border-radius: 14px; }
#calif{margin-top:0 !important;}
</style>

<div class="container-fluid px-4 py-4" id="calif" name="calif">
    <h3 class="fw-bold mb-4"><i class="fa-solid fa-star text-warning me-2"></i>Calificaciones de Satisfacción</h3>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card card-stat shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">Total de respuestas</div>
                    <div class="fs-2 fw-bold"><?= (int) $stats['total'] ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat shadow-sm border-0 bg-warning">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">Calificación promedio</div>
                    <div class="fs-2 fw-bold"><?= $stats['promedio'] ?? '—' ?> / 5</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card card-stat shadow-sm border-0 bg-danger text-white">
                <div class="card-body">
                    <div class="small text-uppercase opacity-75">Marcadas "No resuelto"</div>
                    <div class="fs-2 fw-bold"><?= (int) ($stats['no_resueltos'] ?? 0) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="btn-group mb-3">
        <a href="?tipo=todos" class="btn btn-outline-secondary <?= $filtro === 'todos' ? 'active' : '' ?>">Todos</a>
        <a href="?tipo=ticket" class="btn btn-outline-secondary <?= $filtro === 'ticket' ? 'active' : '' ?>">Tickets</a>
        <a href="?tipo=mantenimiento" class="btn btn-outline-secondary <?= $filtro === 'mantenimiento' ? 'active' : '' ?>">Mantenimientos</a>
    </div>

    <div class="table-responsive bg-white rounded shadow-sm">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Referencia</th>
                    <th>Usuario</th>
                    <th>Calificación</th>
                    <th>¿Resuelto?</th>
                    <th>Comentarios</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($res) === 0): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">Aún no hay calificaciones registradas.</td></tr>
                <?php else: ?>
                    <?php while ($row = mysqli_fetch_assoc($res)): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($row['fecha_evaluacion'])) ?></td>
                            <td>
                                <?php if ($row['tipo'] === 'ticket'): ?>
                                    <span class="badge bg-primary">Ticket</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Mantenimiento</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                #<?= (int) $row['referencia_id'] ?>
                                <?php if ($row['tipo'] === 'ticket' && $row['ticket_descripcion']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars(mb_strimwidth($row['ticket_descripcion'], 0, 60, '...')) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($row['usuario'] ?? ('id ' . $row['id_usuario'])) ?></td>
                            <td class="estrellas-view">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fa-solid fa-star <?= $i <= $row['calificacion'] ? '' : 'vacia' ?>"></i>
                                <?php endfor; ?>
                            </td>
                            <td>
                                <?php if ($row['resuelto'] === 'Si'): ?>
                                    <span class="badge bg-success">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $row['comentarios'] ? htmlspecialchars($row['comentarios']) : '<span class="text-muted">—</span>' ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
