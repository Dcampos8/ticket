<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('reportes');
require_once ROOT_PATH . 'backend/conexion.php';
$validarFecha = static function ($valor, $predeterminada): string {
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return $predeterminada;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : $predeterminada;
};
$inicio = $validarFecha($_GET['fecha_inicio'] ?? '', date('Y-m-01'));
$fin = $validarFecha($_GET['fecha_fin'] ?? '', date('Y-m-d'));
// Armar nombre del archivo
$nombreArchivo = "ACTIVIDADES SISTEMAS ($inicio al $fin).xls";

// Encabezados para Excel
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$nombreArchivo\"");

// Forzar UTF-8 para Excel
echo "\xEF\xBB\xBF";

$stmt = $conexion->prepare('SELECT * FROM tickets WHERE fecha_creacion BETWEEN ? AND ? ORDER BY fecha_creacion DESC');
$stmt->bind_param('ss', $inicio, $fin);
$stmt->execute();
$resultado = $stmt->get_result();

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
