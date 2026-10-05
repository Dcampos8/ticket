<?php
session_start();
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: ../../../login.php");
    exit();
}

require('../../../backend/conexion.php');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('ID inválido');
}

$id_plan = intval($_GET['id']);

// VALIDAR QUE NO HAYA REALIZADOS
$val = $conexion->query(
  "SELECT COUNT(*) total
   FROM mantenimientos_realizados mr
   JOIN programacion_mantenimiento pm
     ON mr.id_programacion = pm.id
   WHERE pm.id_plan = $id_plan"
)->fetch_assoc();

if ($val['total'] > 0) {
    die('No se puede eliminar: ya hay mantenimientos realizados');
}

// ELIMINAR PROGRAMACIÓN
$conexion->query(
  "DELETE FROM programacion_mantenimiento WHERE id_plan = $id_plan"
);

header("Location: ../plan_mantenimiento.php");
