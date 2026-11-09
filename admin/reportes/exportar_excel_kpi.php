<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

ob_start();
ob_clean();
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
require_once ROOT_PATH . 'backend/conexion.php';

$validarFecha = static function ($valor, $predeterminada): string {
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return $predeterminada;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : $predeterminada;
};
$fecha_inicio = $validarFecha($_GET['fecha_inicio'] ?? '', date('Y-m-d', strtotime('monday this week')));
$fecha_fin = $validarFecha($_GET['fecha_fin'] ?? '', date('Y-m-d'));
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
echo "\xEF\xBB\xBF"; // BOM para Excel

$nombreArchivo = "Reporte_KPI_{$fecha_inicio}_a_{$fecha_fin}.xls";
header("Content-Disposition: attachment; filename=$nombreArchivo");

header("Pragma: no-cache");
header("Expires: 0");





/* ================= KPIs ================= */

$ticketsMes = $conexion->query("SELECT COUNT(*) total FROM tickets WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['total'] ?? 0;

$ticketsCerrados = $conexion->query("SELECT COUNT(*) total FROM tickets WHERE estatus='Finalizado' AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['total'] ?? 0;

$promedioMin = $conexion->query("SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE,fecha_creacion,fecha_modificacion)),2) promedio FROM tickets WHERE estatus='Finalizado' AND fecha_modificacion IS NOT NULL AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['promedio'] ?? 0;

$promedioHoras = round($promedioMin / 60, 2);

$sla = $conexion->query("SELECT COUNT(*) total FROM tickets WHERE estatus='Finalizado' AND TIMESTAMPDIFF(HOUR,fecha_creacion,fecha_modificacion) <= 24 AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'")->fetch_assoc()['total'] ?? 0;

$slaPorcentaje = ($ticketsCerrados > 0) ? round(($sla / $ticketsCerrados) * 100, 2) : 0;

/* ================= RANKINGS ================= */

$rankingUsuarios = $conexion->query("SELECT nombre_completo, COUNT(*) total FROM tickets WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin' GROUP BY nombre_completo ORDER BY total DESC LIMIT 5");

$rankingAreas = $conexion->query("SELECT area, COUNT(*) total FROM tickets WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin' GROUP BY area ORDER BY total DESC LIMIT 5");

/* ================= EXCEL ================= */

echo "<table border='1'>";

echo "<tr><th colspan='7' style='background:#333;color:#fff;'>REPORTE GENERAL KPI SISTEMAS ($fecha_inicio al $fecha_fin)</th></tr>";

echo "<tr style='background:#ddd;'>
        <th>Tickets</th>
        <th>Cerrados</th>
        <th>% Eficiencia</th>
        <th>Prom. Atención</th>
        <th>SLA 24h</th>
        <th colspan='2'></th>
      </tr>";

echo "<tr>
        <td>$ticketsMes</td>
        <td>$ticketsCerrados</td>
        <td>".($ticketsMes>0?round(($ticketsCerrados/$ticketsMes)*100,2):0)."%</td>
        <td>$promedioHoras hrs</td>
        <td>$slaPorcentaje%</td>
        <td colspan='2'></td>
      </tr>";

echo "<tr><td colspan='7'></td></tr>";

echo "<tr>
        <th colspan='3' style='background:#3b82f6;color:white;'>TOP 5 USUARIOS</th>
        <th></th>
        <th colspan='3' style='background:#22c55e;color:white;'>TOP 5 ÁREAS</th>
      </tr>";

for($i=0; $i<5; $i++){
    $u = $rankingUsuarios->fetch_assoc();
    $a = $rankingAreas->fetch_assoc();

    echo "<tr>
            <td colspan='2'>".($u['nombre_completo'] ?? "")."</td>
            <td>".($u['total'] ?? "")."</td>
            <td></td>
            <td colspan='2'>".($a['area'] ?? "")."</td>
            <td>".($a['total'] ?? "")."</td>
          </tr>";
}

echo "<tr><td colspan='7'></td></tr>";

echo "<tr style='background:#eee;'>
        <th>ID</th>
        <th>Fecha</th>
        <th>Usuario</th>
        <th>Descripción</th>
        <th>Estatus</th>
        <th>Área</th>
        <th>Tipo</th>
      </tr>";

/* ================= DETALLE ================= */

$resultado = $conexion->query("
    SELECT id, fecha_creacion, nombre_completo, descripcion, estatus, area, tipo_ticket 
    FROM tickets 
    WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
");

while($row = $resultado->fetch_assoc()){
    echo "<tr>
            <td>".$row['id']."</td>
            <td>".$row['fecha_creacion']."</td>
            <td>".$row['nombre_completo']."</td>
            <td>".$row['descripcion']."</td>
            <td>".$row['estatus']."</td>
            <td>".$row['area']."</td>
            <td>".$row['tipo_ticket']."</td>
          </tr>";
}

echo "</table>";

ob_end_flush();
?>