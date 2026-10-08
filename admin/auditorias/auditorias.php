<?php
// 1. Cargar configuración y constantes (BASE_URL)
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('auditorias');
// 5. Cargar Interfaz (menu.php ya incluye header.php)
include (ROOT_PATH . 'admin/menu.php');

// GENERAR AUDITORIAS
if(isset($_POST['generar'])){

    $cantidad = 5;
    $anio = date('Y');
    $mes = date('m');
    $mes_actual = date('Y-m');

    // verificar si ya existen auditorías este mes
    $verificar = $conexion->query("
        SELECT COUNT(*) as total 
        FROM auditorias_programadas 
        WHERE DATE_FORMAT(fecha_programada,'%Y-%m') = '$mes_actual'
    ");
    $row = $verificar->fetch_assoc();

    if($row['total'] == 0){

        $dias_mes = cal_days_in_month(CAL_GREGORIAN, $mes, $anio);
        $dias_usados = [];

        // 🔵 AHORA TOMA LOS EQUIPOS DESDE plan_mantenimiento
        $equipos = $conexion->query("
            SELECT DISTINCT area 
            FROM plan_mantenimiento
            WHERE area IS NOT NULL AND area != ''
            ORDER BY RAND()
            LIMIT $cantidad
        ");

        while($eq = $equipos->fetch_assoc()){

            do{
                $dia = rand(1, $dias_mes);
            }while(in_array($dia, $dias_usados));

            $dias_usados[] = $dia;

            $fecha_random = $anio . "-" . $mes . "-" . str_pad($dia, 2, "0", STR_PAD_LEFT);

            $conexion->query("
                INSERT INTO auditorias_programadas (area, fecha_programada, estado)
                VALUES ('".$eq['area']."', '$fecha_random', 'Pendiente')
            ");
        }

        echo "<script>alert('Auditorías generadas desde plan_mantenimiento');</script>";

    }else{
        echo "<script>alert('Ya existen auditorías este mes');</script>";
    }
}
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__auditorias__auditorias.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__auditorias__auditorias.css') ?>">

<div class="container-fluid mt-100">
    <div class="container-auditoria">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fa-solid fa-dice me-2"></i> Programa de Auditorías Aleatorias</h2>
            <form method="POST">
                <button type="submit" name="generar" class="btn-generar">
                    <i class="fa-solid fa-rotate me-2"></i> Generar Auditorías del Mes
                </button>
            </form>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th><i class="fa-solid fa-calendar-days me-1"></i> Fecha</th>
                        <th><i class="fa-solid fa-building me-1"></i> Área</th>
                        <th><i class="fa-solid fa-user-tie me-1"></i> Responsable</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>

<?php
$result = $conexion->query("
SELECT 
    ap.id,
    ap.fecha_programada,
    ap.estado,
    ap.area,
    (
        SELECT u.nombre_completo
        FROM plan_mantenimiento pm
        LEFT JOIN usuarios u ON pm.responsable = u.id
        WHERE pm.area = ap.area
        LIMIT 1
    ) AS nombre_responsable

FROM auditorias_programadas ap

WHERE ap.estado = 'Pendiente'

ORDER BY ap.fecha_programada DESC
");
while($row = $result->fetch_assoc()){
?>
<tr>
                        <td><b><?= date('d/m/Y', strtotime($row['fecha_programada'])) ?></b></td>
                        <td><span style="color:#1e3a8a; font-weight:600;"><?= htmlspecialchars($row['area'] ?? 'Sin asignar') ?></span></td>
                        <td><?= htmlspecialchars($row['nombre_responsable'] ?? 'Sin asignar') ?></td>
                        <td>
                            <span class="status-badge <?= strtolower($row['estado']) ?>">
                                <?= $row['estado'] ?>
                            </span>
                        </td>
                        <td>
                            <?php if($row['estado']=="Pendiente"){ ?>
                                <a href="auditar.php?id=<?= $row['id'] ?>" class="btn-revisar">
                                    <i class="fa-solid fa-magnifying-glass me-1"></i> Revisar
                                </a>
                            <?php } else { echo "—"; } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

<br><br>
<h2>Auditorías Realizadas</h2>

<div class="section-divider"></div>
        <h2><i class="fa-solid fa-circle-check me-2" style="color: #15803d;"></i> Auditorías Realizadas</h2>
        
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Programada</th>
                        <th>Área</th>
                        <th>Responsable</th>
                        <th>Revisado por</th>
                        <th>Fecha Revisión</th>
                        <th>Observaciones</th>
                    </tr>
                </thead>
                <tbody>
<?php
$realizadas = $conexion->query("
SELECT 
    ar.*,
    ap.fecha_programada,
    ap.area,
    (
        SELECT u.nombre_completo
        FROM plan_mantenimiento pm
        LEFT JOIN usuarios u ON pm.responsable = u.id
        WHERE pm.area = ap.area
        LIMIT 1
    ) AS nombre_responsable

FROM auditorias_resultados ar

INNER JOIN auditorias_programadas ap
    ON ar.id_auditoria = ap.id

WHERE ap.estado = 'Realizada'

ORDER BY ar.fecha_revision DESC
");
while($r = $realizadas->fetch_assoc()){
?>

<tr>
                        <td><?= date('d/m/Y', strtotime($r['fecha_programada'])) ?></td>
                        <td><span style="font-weight:600;"><?= htmlspecialchars($r['area']) ?></span></td>
                        <td><?= htmlspecialchars($r['nombre_responsable'] ?? 'Sin asignar') ?></td>
                        <td><i class="fa-solid fa-user-check me-1 text-success"></i> <?= htmlspecialchars($r['revisado_por']) ?></td>
                        <td><?= date('d/m/Y H:i', strtotime($r['fecha_revision'])) ?></td>
                        <td class="obs-text" title="<?= htmlspecialchars($r['observaciones']) ?>">
                            <?= htmlspecialchars($r['observaciones']) ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

    </div>
</div>
