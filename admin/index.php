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

$ticketsAtendidos = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE fecha_modificacion IS NOT NULL
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

/* ================= PROMEDIO ATENCION ================= */

$promedioMin = $conexion->query("
SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE,fecha_creacion,fecha_modificacion)),2) promedio
FROM tickets
WHERE estatus='Finalizado'
AND fecha_modificacion IS NOT NULL
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['promedio'] ?? 0;

$promedioHoras = round($promedioMin/60,2);

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
SELECT DAY(fecha_creacion) dia, COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
GROUP BY DAY(fecha_creacion)
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

/* ================= MES ANTERIOR ================= */

$inicioMesAnterior = date('Y-m-01', strtotime('first day of last month'));
$finMesAnterior    = date('Y-m-t', strtotime('last day of last month'));

$ticketsMesAnterior = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE DATE(fecha_creacion) BETWEEN '$inicioMesAnterior' AND '$finMesAnterior'
")->fetch_assoc()['total'] ?? 0;

/* ================= CRECIMIENTO ================= */

$crecimiento = 0;

if($ticketsMesAnterior > 0){
    $crecimiento = round((($ticketsMes - $ticketsMesAnterior) / $ticketsMesAnterior) * 100, 2);
}

/* ================= SLA 24 HORAS ================= */

$sla = $conexion->query("
SELECT COUNT(*) total
FROM tickets
WHERE estatus='Finalizado'
AND TIMESTAMPDIFF(HOUR,fecha_creacion,fecha_modificacion) <= 24
AND DATE(fecha_creacion) BETWEEN '$fecha_inicio' AND '$fecha_fin'
")->fetch_assoc()['total'] ?? 0;

$slaPorcentaje = $ticketsCerrados > 0 ? round(($sla/$ticketsCerrados)*100,2) : 0;

?>
<style>
    body{
        margin-top: 100px;
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
<link rel="stylesheet" href="../css/dashboard.css">

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
<div class="row g-4 mb-4">
    
<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-blue">📊</div>
<div class="kpi-info">
<span>Tickets del Mes pasado</span>
<strong><?=$ticketsMesAnterior?></strong>
</div>
</div>
</div>


<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-blue">📊</div>
<div class="kpi-info">
<span>Tickets del mes</span>
<strong><?=$ticketsMes?></strong>
</div>
</div>
</div>


<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-green">✅</div>
<div class="kpi-info">
<span>% Finalizados</span>
<strong><?=$porcentajeCierre?>%</strong>
</div>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-yellow">⏳</div>
<div class="kpi-info">
<span>Tickets Abiertos</span>
<strong><?=$ticketsAbiertos?></strong>
</div>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-red">✖</div>
<div class="kpi-info">
<span>Tickets Cancelados</span>
<strong><?=$ticketsCancelados?></strong>
</div>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-purple">⏱</div>
<div class="kpi-info">
<span>Promedio Atención</span>
<strong><?=$promedioHoras?> hrs</strong>
</div>
</div>
</div>

<div class="col-md-3">
<div class="kpi">

<div class="kpi-icon kpi-orange">📈</div>

<div class="kpi-info">
<span>Crecimiento vs Mes Pasado</span>
<strong class="<?= ($crecimiento > 0) ? 'text-success':'text-danger' ?>">
    <?= ($crecimiento > 0) ? '▲' : '▼' ?> <?=$crecimiento?>%
</strong>
</div>

</div>
</div>


<div class="col-md-3">
<div class="kpi">

<div class="kpi-icon kpi-green">⚡</div>

<div class="kpi-info">
<span>SLA 24h</span>
<strong><?=$slaPorcentaje?>%</strong>
</div>

</div>
</div>


<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-orange">🛠</div>
<div class="kpi-info">
<span>% Atención Soporte</span>
<strong><?=$porcentajeAtendidos?>%</strong>
</div>
</div>
</div>

<div class="col-md-3">
<div class="kpi">
<div class="kpi-icon kpi-blue">📱</div>
<div class="kpi-info">
<span>% Líneas Activas</span>
<strong><?=$porcentajeLineasActivas?>%</strong>
</div>
</div>
</div>

</div>

<div class="row g-4 mb-4">

<div class="col-lg-4">
<div class="card">
<h6>Tickets por Estatus</h6>
<div class="chart-wrap">
<canvas id="gTickets"></canvas>
</div>
</div>
</div>

<div class="col-lg-4">
<div class="card">
<h6>Tickets por Tipo</h6>
<div class="chart-wrap">
<canvas id="gTipos"></canvas>
</div>
</div>
</div>

<div class="col-lg-4">
<div class="card">
<h6>Tickets por Día</h6>
<div class="chart-wrap">
<canvas id="gDias"></canvas>
</div>
</div>
</div>

</div>

<div class="row g-4">

<div class="col-lg-4">
<div class="card">

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
<div class="card">

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        labels:[
            <?php
            $ticketsEstado->data_seek(0);
            while($r=$ticketsEstado->fetch_assoc()){
                echo '"'.$r['estatus'].'",';
            }
            ?>
        ],

        datasets:[{
            data:[
                <?php
                $ticketsEstado->data_seek(0);
                while($r=$ticketsEstado->fetch_assoc()){
                    echo $r['total'].',';
                }
                ?>
            ],

            backgroundColor:[
                '#22c55e',
                '#f59e0b',
                '#ef4444',
                '#3b82f6',
                '#8b5cf6'
            ],

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

labels:[
<?php
$ticketsTipo->data_seek(0);
while($r=$ticketsTipo->fetch_assoc()){
echo '"'.$r['tipo_ticket'].'",';
}
?>
],

datasets:[{

label:'Tickets',

data:[
<?php
$ticketsTipo->data_seek(0);
while($r=$ticketsTipo->fetch_assoc()){
echo $r['total'].',';
}
?>
],

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

labels:[
<?php
$ticketsPorDia->data_seek(0);
while($r=$ticketsPorDia->fetch_assoc()){
echo $r['dia'].',';
}
?>
],

datasets:[{

label:'Tickets',

data:[
<?php
$ticketsPorDia->data_seek(0);
while($r=$ticketsPorDia->fetch_assoc()){
echo $r['total'].',';
}
?>
],

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