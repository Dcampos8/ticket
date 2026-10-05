<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('inventario');
?><?php
require('../../backend/conexion.php');
include('../header.php');
include('../menu.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de equipo inválido");
}

$id = intval($_GET['id']);

$result = $conexion->query("
SELECT *
FROM inventario_movimientos
WHERE id_equipo = $id
ORDER BY fecha_movimiento DESC
");
?>

<h3>Historial del Equipo</h3>

<div class="table-responsive mb-3">
<table class="table">

<tr>
<th>Fecha</th>
<th>Movimiento</th>
<th>Área</th>
<th>Observaciones</th>
</tr>

<?php while($r = $result->fetch_assoc()) { ?>

<tr>

<td><?= $r['fecha_movimiento'] ?></td>

<td><?= $r['tipo_movimiento'] ?></td>

<td><?= $r['area_nueva'] ?></td>

<td><?= $r['observaciones'] ?></td>

</tr>

<?php } ?>

</table>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
