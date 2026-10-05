<?php
session_start();
require '../../../backend/conexion.php';

$estatus = $_GET['estatus'] ?? '';
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin    = $_GET['fecha_fin'] ?? '';

$stmt = $conexion->prepare("
    SELECT id,
           nombre,
           area,
           descripcion,
           fecha_creacion
    FROM tickets
    WHERE estatus = ?
    AND DATE(fecha_creacion) BETWEEN ? AND ?
    ORDER BY fecha_creacion DESC
");

$stmt->bind_param("sss", $estatus, $fecha_inicio, $fecha_fin);
$stmt->execute();

$res = $stmt->get_result();

if($res->num_rows == 0){
    echo "<p>No hay tickets con este estatus en ese rango.</p>";
    exit;
}

echo "<table class='table table-bordered'>";
echo "<thead>
<tr>
<th>ID</th>
<th>NOMBRE</th>
<th>ÁREA</th>
<th>DESCRIPCIÓN</th>
<th>FECHA</th>
</tr>
</thead><tbody>";

while($r = $res->fetch_assoc()){

    echo "<tr>
        <td>{$r['id']}</td>
        <td>".htmlspecialchars($r['nombre'])."</td>
        <td>".htmlspecialchars($r['area'])."</td>
        <td>".htmlspecialchars($r['descripcion'])."</td>
        <td>{$r['fecha_creacion']}</td>
    </tr>";
}

echo "</tbody></table>";
?>