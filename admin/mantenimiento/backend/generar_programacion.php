<?php
session_start();
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../../../login.php");
    exit();
}

require('../../../backend/conexion.php');

/* ================= DATOS ================= */
$id_plan = intval($_POST['id_plan'] ?? 0);
$fechaInicio = $_POST['fecha_inicio'] ?? '';

if ($id_plan === 0 || empty($fechaInicio)) {
    die("Datos incompletos");
}

/* ================= PLAN ================= */
$plan = $conexion->query(
    "SELECT * FROM plan_mantenimiento WHERE id = $id_plan"
)->fetch_assoc();

if (!$plan) {
    die("Plan no encontrado");
}

$frecuencia = $plan['frecuencia'];
$tecnico    = $plan['responsable'];
$anio       = $plan['anio'];

/* ================= INTERVALOS ================= */
$intervalos = [
    'Mensual'     => 1,
    'Trimestral'  => 3,
    'Semestral'   => 6,
    'Anual'       => 12
];

$meses = $intervalos[$frecuencia] ?? 1;

/* ================= GENERAR FECHAS ================= */
$fecha = new DateTime($fechaInicio);

while ((int)$fecha->format('Y') <= $anio) {
    
    // --- LÓGICA PARA SALTAR DOMINGOS ---
    // Si la fecha cae en Domingo (7), sumamos 1 día para que sea Lunes (1)
    if ($fecha->format('N') == 7) {
        $fecha->modify('+1 day');
    }
    // Si quieres que sea estrictamente LUNES A VIERNES (saltar también Sábado):
    /*
    if ($fecha->format('N') == 6) { $fecha->modify('+2 days'); } // Sábado a Lunes
    if ($fecha->format('N') == 7) { $fecha->modify('+1 day'); }  // Domingo a Lunes
    */

    if ((int)$fecha->format('Y') == $anio) {

        $fechaStr = $fecha->format('Y-m-d');

        // VALIDAR SI YA EXISTE
        $existe = $conexion->query("
            SELECT id FROM programacion_mantenimiento 
            WHERE id_plan = $id_plan 
            AND fecha_programada = '$fechaStr'
        ");

        if ($existe->num_rows == 0) {
            $stmt = $conexion->prepare("
                INSERT INTO programacion_mantenimiento 
                (id_plan, fecha_programada, tecnico) 
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("isi", $id_plan, $fechaStr, $tecnico);
            $stmt->execute();
        }
    }

    // SUMAR MESES SEGÚN FRECUENCIA
    $fecha->modify("+$meses months");
}

header("Location: ../programacion_mantenimientos.php?ok=1");
exit();