<?php
// Obtener fechas desde la URL
$inicio = isset($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : 'inicio';
$fin = isset($_GET['fecha_fin']) ? $_GET['fecha_fin'] : 'fin';

// Armar nombre del archivo
$nombreArchivo = "ACTIVIDADES SISTEMAS ($inicio al $fin).xls";

// Encabezados para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

// Forzar UTF-8 para Excel
echo "\xEF\xBB\xBF";

include('../../backend/conexion.php');

// Si estás usando filtro por fecha, puedes incluirlo aquí
$query = "SELECT * FROM tickets WHERE fecha_creacion BETWEEN '$inicio' AND '$fin' ORDER BY fecha_creacion DESC";
$resultado = $conexion->query($query);

// Comenzar tabla
echo "<table border='1'>";
echo "<tr>
        <th>ID</th>
        <th>Usuario</th>
        <th>Área</th>
        <th>Puesto</th>
        <th>Descripción</th>
        <th>Estatus</th>
        <th>Fecha</th>
      </tr>";

// Rellenar datos
while ($fila = $resultado->fetch_assoc()) {
    echo "<tr>
            <td>{$fila['id']}</td>
            <td>{$fila['nombre']}</td>
            <td>{$fila['area']}</td>
            <td>{$fila['puesto']}</td>
            <td>{$fila['descripcion']}</td>
            <td>{$fila['estatus']}</td>
            <td>{$fila['fecha_creacion']}</td>
          </tr>";
}

echo "</table>";
$conexion->close();
?>
