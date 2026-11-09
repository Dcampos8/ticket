<?php
require('../../../backend/conexion.php');

$conexion->set_charset("utf8");

/* ================= CONSULTA ================= */

$query = "
SELECT 
    p.id,
    p.fecha_programada,

    -- RESPONSABLE DEL PLAN
    u.nombre_completo AS encargado,

    -- DATOS DEL PLAN
    pm.area,

    -- DATOS DEL EQUIPO
    e.marca,
    e.modelo

FROM programacion_mantenimiento p

JOIN plan_mantenimiento pm 
    ON p.id_plan = pm.id

JOIN inventario_equipos e
    ON pm.id_equipo = e.id

LEFT JOIN usuarios u 
    ON pm.responsable = u.id

LEFT JOIN mantenimientos_realizados mr
    ON mr.id_programacion = p.id

WHERE mr.id_programacion IS NULL
";

$result = $conexion->query($query);

if(!$result){
    die("Error SQL: ".$conexion->error);
}

/* ================= EVENTOS ================= */

$eventos = [];

while ($row = $result->fetch_assoc()) {

    $encargado = $row['encargado'] ?? 'Sin responsable';

    $eventos[] = [
        'id'    => $row['id'],
        'title' => $encargado.' - '.$row['area'],
        'start' => $row['fecha_programada'],
        'color' => '#0d6efd'
    ];
}

/* ================= RESPUESTA JSON ================= */

header('Content-Type: application/json');

echo json_encode($eventos);