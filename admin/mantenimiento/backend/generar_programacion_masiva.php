<?php
require('../../../backend/conexion.php');

$fechaInicioGlobal = $_POST['fecha_inicio_global'] ?? '';
if (empty($fechaInicioGlobal)) { die("Seleccione una fecha de inicio"); }

// 1. Obtener todos los planes activos
$planesResult = $conexion->query("SELECT * FROM plan_mantenimiento WHERE activo = 1");
$totalPlanes = $planesResult->num_rows;

$intervalos = ['Mensual' => 1, 'Trimestral' => 3, 'Semestral' => 6, 'Anual' => 12];

// 2. Definir días laborables (Lunes a Viernes)
// Vamos a repartir los equipos sumando un día por cada equipo procesado
$contadorEquipo = 0;

while ($plan = $planesResult->fetch_assoc()) {
    $id_plan = $plan['id'];
    $mesesFrecuencia = $intervalos[$plan['frecuencia']] ?? 1;
    $tecnico = $plan['responsable'];
    $anio_plan = $plan['anio'];

    // La fecha base para ESTE equipo específico se desplaza según el contador
    $fechaEquipo = new DateTime($fechaInicioGlobal);
    
    // Saltamos días según el número de equipo para "repartirlos"
    // Si quieres que se repartan 2 equipos por día, usarías: floor($contadorEquipo / 2)
    $diasAñadir = $contadorEquipo; 
    $fechaEquipo->modify("+$diasAñadir days");

    // Validar que la fecha de inicio de este equipo no caiga en fin de semana
    while (in_array($fechaEquipo->format('N'), [6, 7])) {
        $fechaEquipo->modify('+1 day');
    }

    // Guardamos esta fecha como la "semilla" para sus ciclos futuros
    $fechaCiclo = clone $fechaEquipo;

    while ((int)$fechaCiclo->format('Y') <= $anio_plan) {
        
        // Siempre validar que el ciclo futuro no caiga en fin de semana
        if ($fechaCiclo->format('N') == 6) { $fechaCiclo->modify('+2 days'); }
        if ($fechaCiclo->format('N') == 7) { $fechaCiclo->modify('+1 day'); }

        if ((int)$fechaCiclo->format('Y') == $anio_plan) {
            $fechaStr = $fechaCiclo->format('Y-m-d');

            $existe = $conexion->query("SELECT id FROM programacion_mantenimiento WHERE id_plan = $id_plan AND fecha_programada = '$fechaStr'");

            if ($existe->num_rows == 0) {
                $stmt = $conexion->prepare("INSERT INTO programacion_mantenimiento (id_plan, fecha_programada, tecnico) VALUES (?, ?, ?)");
                $stmt->bind_param("isi", $id_plan, $fechaStr, $tecnico);
                $stmt->execute();
            }
        }
        
        // Sumar la frecuencia (ej. 1 mes) manteniendo el desfase de día
        $fechaCiclo->modify("+$mesesFrecuencia months");
    }

    $contadorEquipo++; // El siguiente equipo se programará el siguiente día disponible
}

header("Location: ../plan_mantenimiento.php?msg=reparto_listo");
exit();