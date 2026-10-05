<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require('../../backend/conexion.php');

if (!isset($_GET['id'])) {
    die('ID no proporcionado');
}

$id = (int)$_GET['id'];

$query = "
SELECT 
    mr.id_programacion,   -- 👈 AGREGA ESTO
    mr.fecha_real,
    mr.tecnico,
    mr.actividades,
    mr.refacciones,
    mr.observaciones,

    pm.frecuencia,
    pm.tipo,
    pm.area,

    eq.tipo_equipo,
    eq.marca,
    eq.modelo,
    eq.no_serie,

    u.nombre_completo AS responsable

FROM mantenimientos_realizados mr
LEFT JOIN programacion_mantenimiento p
    ON mr.id_programacion = p.id
LEFT JOIN plan_mantenimiento pm
    ON p.id_plan = pm.id
LEFT JOIN equipos eq
    ON pm.id_equipo = eq.id
LEFT JOIN usuarios u
    ON pm.responsable = u.id

WHERE mr.id_programacion = $id
LIMIT 1
";

$result = $conexion->query($query);

if (!$result || $result->num_rows === 0) {
    die('<div class="alert alert-warning">No se encontró información del mantenimiento</div>');
}

$row = $result->fetch_assoc();
?>

<link rel="stylesheet" href="../../css/hoja_servicio.css">


<div id="hojaServicio">

<h4 class="text-center mb-3">HOJA DE SERVICIO DE MANTENIMIENTO</h4>
<p class="text-end">
    <strong>Folio:</strong> <?= htmlspecialchars($row['id_programacion']) ?>
</p>
<hr>

<p><strong>Fecha:</strong> <?= htmlspecialchars($row['fecha_real']) ?></p>
<p><strong>Técnico:</strong> <span id="tecnicoServicio"><?= htmlspecialchars($row['tecnico']) ?></span></p>

<hr>

<h5>Datos del equipo</h5>

<table class="tabla-datos">
  <tr>
    <th>Tipo</th>
    <td><?= htmlspecialchars($row['tipo_equipo'] ?? 'No asignado') ?></td>
    <th>Área</th>
    <td><?= htmlspecialchars($row['area'] ?? '—') ?></td>
  </tr>
  <tr>
    <th>Marca</th>
    <td><?= htmlspecialchars($row['marca'] ?? '—') ?></td>
    <th>Modelo</th>
    <td><?= htmlspecialchars($row['modelo'] ?? '—') ?></td>
  </tr>
  <tr>
    <th>No. Serie</th>
    <td><?= htmlspecialchars($row['no_serie'] ?? '—') ?></td>
    <th>Reponsable</th>
    <td><?= htmlspecialchars($row['responsable'] ?? '—') ?></td>
  </tr>
</table>


<hr>

<h5>Actividades realizadas</h5>
<p><?= nl2br(htmlspecialchars($row['actividades'])) ?></p>

<h5>Refacciones utilizadas</h5>
<p><?= nl2br(htmlspecialchars($row['refacciones'])) ?></p>

<h5>Observaciones</h5>
<p><?= nl2br(htmlspecialchars($row['observaciones'])) ?></p>

<hr>
<br>
<div class="firma">
    <p class="linea"></p>
    <p class="texto">Nombre y firma del responsable</p>
</div>




</div>
