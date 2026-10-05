<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 1. Configuración global y sesión
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Seguridad
if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 3. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 4. Forzar la descarga del archivo Excel mediante Headers
$filename = "Calendario_Mantenimientos_" . date('Ymd_His') . ".xls";
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: private", false);

// Ajustar caracteres especiales en español (UTF-8 BOM)
echo "\xEF\xBB\xBF"; 

// 5. Consulta Avanzada con JOINs para traer Nombres Completos en lugar de IDs
$query = "SELECT 
            pm.id, 
            pm.fecha_programada, 
            pm.estatus, 
            pm.comentarios_usuario,
            plan.area AS plan_area,
            u_resp.nombre_completo AS nombre_responsable,
            u_teco.nombre_completo AS nombre_tecnico
          FROM programacion_mantenimiento pm
          INNER JOIN plan_mantenimiento plan ON pm.id_plan = plan.id
          LEFT JOIN usuarios u_resp ON plan.responsable = u_resp.id
          LEFT JOIN usuarios u_teco ON pm.tecnico = u_teco.id
          ORDER BY pm.fecha_programada ASC";

$resultado = $conexion->query($query);
?>

<table border="1">
    <thead>
        <tr style="background-color: #1e3a8a; color: #ffffff; font-weight: bold; text-align: center;">
            <th style="padding: 10px;">ID Prog</th>
            <th style="padding: 10px;">Área</th>
            <th style="padding: 10px;">Responsable de Planeación</th>
            <th style="padding: 10px;">Técnico Asignado</th>
            <th style="padding: 10px;">Fecha Programada</th>
            <th style="padding: 10px;">Estatus</th>
            <th style="padding: 10px;">Comentarios Usuario</th>
        </tr>
    </thead>
    <tbody>
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while ($row = $resultado->fetch_assoc()): ?>
                <tr>
                    <td style="text-align: center;"><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['plan_area'] ?? 'No especificada') ?></td>
                    <td><?= htmlspecialchars($row['nombre_responsable'] ?? 'Sin responsable asignado') ?></td>
                    <td><?= htmlspecialchars($row['nombre_tecnico'] ?? 'No asignado') ?></td>
                    <td style="text-align: center;">
                        <?= !empty($row['fecha_programada']) ? date('d/m/Y', strtotime($row['fecha_programada'])) : 'Sin fecha' ?>
                    </td>
                    <td style="text-align: center; font-weight: bold; <?php
                        if($row['estatus'] == 'Pendiente') echo 'color: #b45309;'; // Naranja
                        elseif($row['estatus'] == 'Cancelado') echo 'color: #b91c1c;'; // Rojo
                        else echo 'color: #15803d;'; // Reprogramado / Completado en Verde
                    ?>">
                        <?= htmlspecialchars($row['estatus']) ?>
                    </td>
                    <td><?= htmlspecialchars($row['comentarios_usuario'] ?? '') ?></td>
                </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" style="text-align: center; padding: 10px;">No hay mantenimientos programados.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>

<?php
if (isset($conexion)) { $conexion->close(); }
exit();
?>