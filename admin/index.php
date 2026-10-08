<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
session_start();

include_once '../config.php';

require_once(ROOT_PATH . 'shared/permisos.php');
requerirAdmin();

include 'menu.php';
require '../backend/conexion.php';
$conexion->set_charset("utf8");

/* ================= FILTRO POR FECHA ================= */

$validarFecha = static function ($valor, $predeterminada): string {
    if (!is_string($valor) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) return $predeterminada;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
    return ($fecha && $fecha->format('Y-m-d') === $valor) ? $valor : $predeterminada;
};
$fecha_inicio = $validarFecha($_GET['fecha_inicio'] ?? '', date('Y-m-01'));
$fecha_fin = $validarFecha($_GET['fecha_fin'] ?? '', date('Y-m-d'));
if ($fecha_inicio > $fecha_fin) {
    [$fecha_inicio, $fecha_fin] = [$fecha_fin, $fecha_inicio];
}
$fechaInicioObj = new DateTimeImmutable($fecha_inicio);
$fechaFinObj = new DateTimeImmutable($fecha_fin);
$diasPeriodo = $fechaInicioObj->diff($fechaFinObj)->days + 1;
$rangoLimitado = $diasPeriodo > 366;
if ($rangoLimitado) {
    $fechaInicioObj = $fechaFinObj->modify('-365 days');
    $fecha_inicio = $fechaInicioObj->format('Y-m-d');
    $diasPeriodo = 366;
}
$finPeriodoAnterior = $fechaInicioObj->modify('-1 day');
$inicioPeriodoAnterior = $finPeriodoAnterior->modify('-' . ($diasPeriodo - 1) . ' days');
$inicioAnteriorSql = $inicioPeriodoAnterior->format('Y-m-d');
$finAnteriorSql = $finPeriodoAnterior->format('Y-m-d');

/* ================= KPIs ================= */

$ticketsMes = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$ticketsCerrados = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE estatus='Finalizado'
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$ticketsAbiertos = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE estatus IN ('Pendiente','En proceso')
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$ticketsCancelados = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE estatus='Cancelado'
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$ticketsResueltosPeriodo = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE fecha_resolucion IS NOT NULL
AND DATE(fecha_resolucion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$ticketsVencidos = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE estatus IN ('Pendiente','En proceso')
AND NOW() > DATE_ADD(fecha_creacion, INTERVAL objetivo_resolucion_minutos MINUTE)
")->fetch_assoc()['total'] ?? 0;
$backlogActual = $conexion->query("SELECT COUNT(*) total FROM tickets WHERE estatus IN ('Pendiente','En proceso')")->fetch_assoc()['total'] ?? 0;

$antiguedadTickets = $conexion->query("
SELECT
    SUM(DATEDIFF(CURDATE(), DATE(fecha_creacion)) <= 1) AS hasta_un_dia,
    SUM(DATEDIFF(CURDATE(), DATE(fecha_creacion)) BETWEEN 2 AND 3) AS dos_a_tres_dias,
    SUM(DATEDIFF(CURDATE(), DATE(fecha_creacion)) BETWEEN 4 AND 7) AS cuatro_a_siete_dias,
    SUM(DATEDIFF(CURDATE(), DATE(fecha_creacion)) > 7) AS mas_de_siete_dias
FROM tickets
WHERE estatus IN ('Pendiente','En proceso')
")->fetch_assoc() ?: ['hasta_un_dia' => 0, 'dos_a_tres_dias' => 0, 'cuatro_a_siete_dias' => 0, 'mas_de_siete_dias' => 0];

$ticketsAtendidos = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE fecha_primera_respuesta IS NOT NULL
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

/* ================= PROMEDIO ATENCION ================= */

$tiemposResolucion = $conexion->query("
SELECT TIMESTAMPDIFF(MINUTE,fecha_creacion,fecha_resolucion) minutos
FROM tickets
WHERE estatus='Finalizado'
AND fecha_resolucion IS NOT NULL
AND DATE(fecha_resolucion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
");
$muestrasResolucion = [];
if ($tiemposResolucion) {
    while ($muestra = $tiemposResolucion->fetch_assoc()) $muestrasResolucion[] = max(0, (int) $muestra['minutos']);
}
sort($muestrasResolucion, SORT_NUMERIC);
$calcularPercentil = static function (array $valores, float $percentil): float {
    $n = count($valores);
    if ($n === 0) return 0;
    $indice = (int) ceil($percentil * $n) - 1;
    return (float) $valores[max(0, min($n - 1, $indice))];
};
$promedioMin = count($muestrasResolucion) > 0 ? array_sum($muestrasResolucion) / count($muestrasResolucion) : 0;

$promedioHoras = round($promedioMin/60,2);
$medianaHoras = round($calcularPercentil($muestrasResolucion, 0.50) / 60, 2);
$percentil90Horas = round($calcularPercentil($muestrasResolucion, 0.90) / 60, 2);

/* ================= LINEAS ================= */

$lineasTotales = $conexion->query("SELECT COUNT(*) total FROM phone_lines")->fetch_assoc()['total'] ?? 0;

$lineasActivas = $conexion->query("SELECT COUNT(*) total FROM phone_lines WHERE activo=1")->fetch_assoc()['total'] ?? 0;

/* ================= PORCENTAJES ================= */

$porcentajeCierre = $ticketsMes>0 ? round(($ticketsCerrados/$ticketsMes)*100,2) : 0;

$porcentajeAtendidos = $ticketsMes>0 ? round(($ticketsAtendidos/$ticketsMes)*100,2) : 0;

$porcentajeLineasActivas = $lineasTotales>0 ? round(($lineasActivas/$lineasTotales)*100,2) : 0;

/* ================= GRAFICAS ================= */

$ticketsEstado = $conexion->query("
SELECT estatus, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY estatus
");

$ticketsPorDia = $conexion->query("
SELECT DATE(fecha_creacion) fecha, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY DATE(fecha_creacion)
ORDER BY DATE(fecha_creacion)
");

$ticketsTipo = $conexion->query("
SELECT tipo_ticket, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY tipo_ticket
");

$rankingUsuarios = $conexion->query("
SELECT nombre_completo, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY nombre_completo
ORDER BY total DESC
LIMIT 5
");

$rankingAreas = $conexion->query("
SELECT area, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY area
ORDER BY total DESC
LIMIT 5
");

$cargaResponsables = $conexion->query("
SELECT COALESCE(NULLIF(u.nombre_completo, ''), NULLIF(t.asignado_a, ''), 'Sin asignar') responsable,
       COUNT(*) abiertos,
       SUM(NOW() > DATE_ADD(t.fecha_creacion, INTERVAL t.objetivo_resolucion_minutos MINUTE)) vencidos
FROM tickets t
LEFT JOIN usuarios u ON u.usuario = t.asignado_a
WHERE t.estatus IN ('Pendiente','En proceso')
GROUP BY t.asignado_a, u.nombre_completo
ORDER BY abiertos DESC, responsable ASC
LIMIT 8
");

$mapaTicketsPorDia = [];
if ($ticketsPorDia) {
    while ($registroDia = $ticketsPorDia->fetch_assoc()) $mapaTicketsPorDia[$registroDia['fecha']] = (int) $registroDia['total'];
}
$labelsDias = [];
$valoresDias = [];
for ($dia = $fechaInicioObj; $dia <= $fechaFinObj; $dia = $dia->modify('+1 day')) {
    $fechaDia = $dia->format('Y-m-d');
    $labelsDias[] = $dia->format('d/m/Y');
    $valoresDias[] = $mapaTicketsPorDia[$fechaDia] ?? 0;
}

$ticketsEstadoDatos = $ticketsEstado ? $ticketsEstado->fetch_all(MYSQLI_ASSOC) : [];
$ticketsTipoDatos = $ticketsTipo ? $ticketsTipo->fetch_all(MYSQLI_ASSOC) : [];
$coloresEstatus = array_map(static function ($estatus) {
    return [
        'Pendiente' => '#f59e0b',
        'En proceso' => '#3b82f6',
        'Finalizado' => '#22c55e',
        'Cancelado' => '#ef4444',
    ][$estatus] ?? '#64748b';
}, array_column($ticketsEstadoDatos, 'estatus'));

/* ============ PERIODO ANTERIOR DE LA MISMA DURACION ============ */

$ticketsPeriodoAnterior = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$inicioAnteriorSql' AND '$finAnteriorSql'
")->fetch_assoc()['total'] ?? 0;

/* ================= CRECIMIENTO ================= */

$crecimiento = 0;

$crecimiento = $ticketsPeriodoAnterior > 0
    ? round((($ticketsMes - $ticketsPeriodoAnterior) / $ticketsPeriodoAnterior) * 100, 2)
    : ($ticketsMes > 0 ? null : 0);

/* ================= CUMPLIMIENTO SLA ================= */

$slaRespuestaStats = $conexion->query("
SELECT
    SUM(fecha_primera_respuesta IS NOT NULL OR (estatus IN ('Pendiente','En proceso') AND NOW() > DATE_ADD(fecha_creacion, INTERVAL objetivo_respuesta_minutos MINUTE))) evaluables,
    SUM(fecha_primera_respuesta IS NOT NULL AND TIMESTAMPDIFF(MINUTE,fecha_creacion,fecha_primera_respuesta) <= objetivo_respuesta_minutos) dentro
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
");
$slaRespuestaStats = $slaRespuestaStats ? $slaRespuestaStats->fetch_assoc() : ['evaluables' => 0, 'dentro' => 0];

$slaResolucionStats = $conexion->query("
SELECT
    SUM(fecha_resolucion IS NOT NULL OR (estatus IN ('Pendiente','En proceso') AND NOW() > DATE_ADD(fecha_creacion, INTERVAL objetivo_resolucion_minutos MINUTE))) evaluables,
    SUM(fecha_resolucion IS NOT NULL AND TIMESTAMPDIFF(MINUTE,fecha_creacion,fecha_resolucion) <= objetivo_resolucion_minutos) dentro
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
");
$slaResolucionStats = $slaResolucionStats ? $slaResolucionStats->fetch_assoc() : ['evaluables' => 0, 'dentro' => 0];
$slaRespuestaPorcentaje = (int) $slaRespuestaStats['evaluables'] > 0
    ? round(((int) $slaRespuestaStats['dentro'] / (int) $slaRespuestaStats['evaluables']) * 100, 2) : 0;
$slaResolucionPorcentaje = (int) $slaResolucionStats['evaluables'] > 0
    ? round(((int) $slaResolucionStats['dentro'] / (int) $slaResolucionStats['evaluables']) * 100, 2) : 0;

?>
<style>
    body{
        margin-top: 0;
    }
    div#modalTicketsEstatus {
    margin-top: 66;
}
#modalTicketsEstatus .modal-dialog{
    max-width: 95%;
}

#modalTicketsEstatus .modal-content{
    height: 85vh;
}

#modalTicketsEstatus .modal-body{
    overflow-y: auto;
    max-height: calc(85vh - 70px);
}

</style>
<?php
$dashboardCssPath = ROOT_PATH . 'css/dashboard.css';
$dashboardCssVersion = is_file($dashboardCssPath) ? (string) filemtime($dashboardCssPath) : (string) time();
?>
<link rel="stylesheet" href="../css/dashboard.css?v=<?= htmlspecialchars($dashboardCssVersion, ENT_QUOTES, 'UTF-8') ?>">

<div id="mainContent">

<h2 class="mb-4">📊 Dashboard KPI Sistemas</h2>

<form method="GET" class="row mb-4">

<div class="col-md-3">
<label>Fecha Inicio</label>
<input type="date" name="fecha_inicio" class="form-control" value="<?=$fecha_inicio?>">
</div>

<div class="col-md-3">
<label>Fecha Fin</label>
<input type="date" name="fecha_fin" class="form-control" value="<?=$fecha_fin?>">
</div>

<div class="col-md-3 d-flex align-items-end">
<button class="btn btn-primary">Filtrar</button>
</div>

<div class="col-md-3 d-flex align-items-end">
<a href="reportes/exportar_excel_kpi.php?fecha_inicio=<?=$fecha_inicio?>&fecha_fin=<?=$fecha_fin?>" class="btn btn-success">
Exportar Excel
</a>
</div>

</form>
<?php if ($rangoLimitado): ?><div class="alert alert-info py-2">El dashboard limita las consultas y la tendencia a un máximo de 366 días. El inicio del rango se ajustó para mantenerlo dentro de ese límite.</div><?php endif; ?>
<div class="row g-4 mb-4">
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-blue">📊</div><div class="kpi-info"><span>Tickets periodo anterior (<?= $diasPeriodo ?> días)</span><strong><?= (int) $ticketsPeriodoAnterior ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-blue">📊</div><div class="kpi-info"><span>Tickets del periodo</span><strong><?= (int) $ticketsMes ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-orange">📈</div><div class="kpi-info"><span>Cambio vs. los <?= $diasPeriodo ?> días previos</span><strong class="<?= $crecimiento === null ? 'text-primary' : (($crecimiento >= 0) ? 'text-success':'text-danger') ?>"><?= $crecimiento === null ? 'Nuevo' : ((($crecimiento >= 0) ? '▲' : '▼') . ' ' . $crecimiento . '%') ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-green">✅</div><div class="kpi-info"><span>Finalizados de los creados</span><strong><?= $porcentajeCierre ?>%</strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-yellow">⏳</div><div class="kpi-info"><span>Abiertos creados en el rango</span><strong><?= (int) $ticketsAbiertos ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-red">✖</div><div class="kpi-info"><span>Cancelados creados en el rango</span><strong><?= (int) $ticketsCancelados ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-green">✅</div><div class="kpi-info"><span>Resueltos registrados en el periodo</span><strong title="Solo cuenta tickets con fecha de resolución registrada"><?= (int) $ticketsResueltosPeriodo > 0 ? (int) $ticketsResueltosPeriodo : 'Sin datos' ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-red">⚠️</div><div class="kpi-info"><span>Abiertos vencidos (SLA)</span><strong><?= (int) $ticketsVencidos ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-yellow">📂</div><div class="kpi-info"><span>Backlog actual</span><strong><?= (int) $backlogActual ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-purple">⏱</div><div class="kpi-info"><span>Promedio de resolución</span><strong><?= $muestrasResolucion ? $promedioHoras . ' hrs' : 'Sin datos' ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-purple">◉</div><div class="kpi-info"><span>Mediana de resolución</span><strong><?= $muestrasResolucion ? $medianaHoras . ' hrs' : 'Sin datos' ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-orange kpi-percentile">P90</div><div class="kpi-info"><span>90% se resuelve en</span><strong><?= $muestrasResolucion ? $percentil90Horas . ' hrs' : 'Sin datos' ?></strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-green">⚡</div><div class="kpi-info"><span>SLA primera respuesta</span><strong><?= $slaRespuestaPorcentaje ?>%</strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-orange">🛠</div><div class="kpi-info"><span>SLA de resolución</span><strong><?= $slaResolucionPorcentaje ?>%</strong></div></div></div>
    <div class="col-12 col-md-6 col-xl-3"><div class="kpi"><div class="kpi-icon kpi-blue">📱</div><div class="kpi-info"><span>Líneas activas</span><strong><?= $porcentajeLineasActivas ?>%</strong></div></div></div>
</div>
<p class="small text-muted mb-4">Los SLA se calculan en tiempo corrido. Los tickets cerrados antes de registrar su fecha de resolución no se incluyen en el cumplimiento ni en los tiempos de resolución.</p>

<div class="row g-4 mb-4">

<div class="col-lg-4">
<div class="card dashboard-panel">
<h6>Tickets por Estatus</h6>
<div class="chart-wrap">
<canvas id="gTickets"></canvas>
</div>
</div>
</div>

<div class="col-lg-4">
<div class="card dashboard-panel">
<h6>Tickets por Tipo</h6>
<div class="chart-wrap">
<canvas id="gTipos"></canvas>
</div>
</div>
</div>

<div class="col-lg-4">
<div class="card dashboard-panel">
<h6>Tickets creados por fecha</h6>
<div class="chart-wrap">
<canvas id="gDias"></canvas>
</div>
</div>
</div>

</div>

<div class="row g-4">

<div class="col-lg-4">
<div class="card dashboard-panel">

<h6>🏆 Top Usuarios</h6>

<ul class="rank-list">

<?php while($r=$rankingUsuarios->fetch_assoc()): ?>

<li>
<span><?=htmlspecialchars($r['nombre_completo'])?></span>
<strong><?=$r['total']?></strong>
</li>

<?php endwhile; ?>

</ul>

</div>
</div>

<div class="col-lg-4">
<div class="card dashboard-panel">

<h6>🏢 Top Áreas</h6>

<ul class="rank-list">

<?php while($r=$rankingAreas->fetch_assoc()): ?>

<li>
<span><?=htmlspecialchars($r['area'])?></span>
<strong><?=$r['total']?></strong>
</li>

<?php endwhile; ?>

</ul>

</div>
</div>

<div class="col-lg-4">
<div class="card dashboard-panel">
<h6>📚 Antigüedad de tickets abiertos</h6>
<ul class="rank-list">
<?php foreach ([['Hasta 1 día','hasta_un_dia'],['2 a 3 días','dos_a_tres_dias'],['4 a 7 días','cuatro_a_siete_dias'],['Más de 7 días','mas_de_siete_dias']] as [$etiqueta,$clave]): ?>
<li><span><?= $etiqueta ?></span><strong><?= (int) ($antiguedadTickets[$clave] ?? 0) ?></strong></li>
<?php endforeach; ?>
</ul>
</div>
</div>

<div class="col-lg-4">
<div class="card dashboard-panel">
<h6>🧑‍💻 Carga actual por responsable</h6>
<ul class="rank-list">
<?php if (!$cargaResponsables || $cargaResponsables->num_rows === 0): ?><li><span>No hay tickets abiertos</span><strong>0</strong></li><?php endif; ?>
<?php if ($cargaResponsables): while ($r = $cargaResponsables->fetch_assoc()): ?>
<li><span><?= htmlspecialchars($r['responsable'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?><small class="d-block text-muted"><?= (int) $r['vencidos'] ?> vencidos</small></span><strong><?= (int) $r['abiertos'] ?></strong></li>
<?php endwhile; endif; ?>
</ul>
</div>
</div>

</div>

</div>
<!-- MODAL TICKETS -->
<div class="modal fade" id="modalTicketsEstatus" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">

      <div class="modal-header">
        <h5 class="modal-title">
            Tickets: <span id="tituloEstatus"></span>
        </h5>

        <button
            type="button"
            class="btn-close"
            data-bs-dismiss="modal">
        </button>
      </div>

      <div class="modal-body">
        <div id="contenidoTickets">
            Cargando...
        </div>
      </div>

    </div>
  </div>
</div>

<script>
    /* ================= MODAL TICKETS POR ESTATUS ================= */

function cargarTicketsPorEstatus(estatus){

    // poner título del modal
    document.getElementById("tituloEstatus").innerText = estatus;

    // mensaje mientras carga
    document.getElementById("contenidoTickets").innerHTML =
        "Cargando tickets...";

    // obtener fechas del filtro actual
    let fechaInicio = document.querySelector(
        'input[name="fecha_inicio"]'
    ).value;

    let fechaFin = document.querySelector(
        'input[name="fecha_fin"]'
    ).value;

    // hacer petición AJAX con estatus + fechas
    fetch(
        "tickets/backend/ajax_tickets_estatus.php?estatus=" +
        encodeURIComponent(estatus) +
        "&fecha_inicio=" +
        encodeURIComponent(fechaInicio) +
        "&fecha_fin=" +
        encodeURIComponent(fechaFin)
    )

    .then(response => response.text())

    .then(html => {

        // meter tabla en el modal
        document.getElementById(
            "contenidoTickets"
        ).innerHTML = html;

        // abrir modal
        let modal = new bootstrap.Modal(
            document.getElementById(
                "modalTicketsEstatus"
            )
        );

        modal.show();

    })

    .catch(error => {

        console.error(error);

        document.getElementById(
            "contenidoTickets"
        ).innerHTML =
            "Error al cargar tickets.";

    });

}

/* ================= GRAFICA ESTATUS ================= */

const chartTickets = new Chart(document.getElementById("gTickets"),{

    type:'doughnut',

    data:{
        labels: <?= json_encode(array_column($ticketsEstadoDatos, 'estatus'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,

        datasets:[{
            data: <?= json_encode(array_map('intval', array_column($ticketsEstadoDatos, 'total'))) ?>,

            backgroundColor: <?= json_encode($coloresEstatus) ?>,

            hoverOffset:15
        }]
    },

    options:{
        maintainAspectRatio:false,

        plugins:{
            legend:{
                position:'bottom'
            }
        },

        onClick:function(evt,elements){

            if(elements.length > 0){

                let index = elements[0].index;
                let estatus = this.data.labels[index];

                cargarTicketsPorEstatus(estatus);

            }

        }
    }

});

/* ================= GRAFICA TIPOS ================= */

new Chart(document.getElementById("gTipos"),{

type:'bar',

data:{

labels: <?= json_encode(array_column($ticketsTipoDatos, 'tipo_ticket'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,

datasets:[{

label:'Tickets',

data: <?= json_encode(array_map('intval', array_column($ticketsTipoDatos, 'total'))) ?>,

backgroundColor:'#3b82f6'

}]

},

options:{
maintainAspectRatio:false
}

});


/* ================= GRAFICA DIAS ================= */

new Chart(document.getElementById("gDias"),{

type:'line',

data:{

labels: <?= json_encode($labelsDias, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,

datasets:[{

label:'Tickets',

data: <?= json_encode($valoresDias) ?>,

borderColor:'#8b5cf6',
backgroundColor:'rgba(139,92,246,0.2)',
fill:true,
tension:0.35,
pointRadius:5,
pointBackgroundColor:'#8b5cf6'

}]

},

options:{
maintainAspectRatio:false
}

});

</script>
