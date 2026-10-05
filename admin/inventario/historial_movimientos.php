<?php

$base_path = '/home/u568802486/domains/transportesvaladez.com/public_html/ticket/';

require_once $base_path . 'config.php';

if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

include(ROOT_PATH . 'admin/header.php');
include(ROOT_PATH . 'admin/menu.php');

/* VALIDAR ID */

if(!isset($_GET['id']) || !is_numeric($_GET['id'])){
    die("Equipo inválido");
}

$id = intval($_GET['id']);

/* DATOS EQUIPO */

$stmt = $conexion->prepare("
SELECT *
FROM inventario_equipos
WHERE id=?
");

$stmt->bind_param("i",$id);
$stmt->execute();

$equipo = $stmt->get_result()->fetch_assoc();

if(!$equipo){
    die("Equipo no encontrado");
}

/* HISTORIAL */

$stmt = $conexion->prepare("
SELECT
m.*,
u1.nombre_completo AS usuario_registro_nombre,
u2.nombre_completo AS responsable_nuevo_nombre,
u3.nombre_completo AS responsable_anterior_nombre
FROM inventario_movimientos m
LEFT JOIN usuarios u1
ON m.usuario_registro = u1.id
LEFT JOIN usuarios u2
ON m.responsable_nuevo = u2.id
LEFT JOIN usuarios u3
ON m.responsable_anterior = u3.id
WHERE m.id_equipo = ?
ORDER BY m.fecha_movimiento DESC
");

$stmt->bind_param("i",$id);
$stmt->execute();

$historial = $stmt->get_result();

?>

<div class="container mt-5">

<h3 class="mb-4">
Historial de movimientos
</h3>

<div class="card shadow-sm mb-4">
<div class="card-body">

<strong>Equipo:</strong>
<?= htmlspecialchars($equipo['marca']) ?>
<?= htmlspecialchars($equipo['modelo']) ?>

<br>

<strong>Serie:</strong>
<?= htmlspecialchars($equipo['no_serie']) ?>

<br>

<strong>Resguardo:</strong>
<?= htmlspecialchars($equipo['numero_resguardo']) ?>

</div>
</div>

<div class="card shadow-sm">

<div class="table-responsive">

<table class="table table-striped mb-0">

<thead class="table-dark">
<tr>
<th>Fecha</th>
<th>Movimiento</th>
<th>Responsable anterior</th>
<th>Responsable nuevo</th>
<th>Área</th>
<th>Observaciones</th>
<th>Registró</th>
</tr>
</thead>

<tbody>

<?php if($historial->num_rows > 0): ?>

<?php while($mov = $historial->fetch_assoc()): ?>

<tr>

<td>
<?= $mov['fecha_movimiento'] ?>
</td>

<td>
<?= htmlspecialchars($mov['tipo_movimiento']) ?>
</td>

<td>
<?= htmlspecialchars($mov['responsable_anterior_nombre'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($mov['responsable_nuevo_nombre'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($mov['area_nueva'] ?? '-') ?>
</td>

<td>
<?= htmlspecialchars($mov['observaciones']) ?>
</td>

<td>
<?= htmlspecialchars($mov['usuario_registro_nombre']) ?>
</td>

</tr>

<?php endwhile; ?>

<?php else: ?>

<tr>
<td colspan="6" class="text-center text-muted py-4">
Sin movimientos registrados
</td>
</tr>

<?php endif; ?>

</tbody>

</table>
</div>
</div>

<div class="mt-3">
<a href="inventario.php" class="btn btn-secondary">
Volver
</a>
</div>

</div>

</body>
</html>