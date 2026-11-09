<?php
require('../../../backend/conexion.php');

$id          = $_POST['id'];
$area        = $_POST['area'];
$tipo        = $_POST['tipo'];
$frecuencia  = $_POST['frecuencia'];
$responsable = $_POST['responsable'];

$stmt = $conexion->prepare("
UPDATE plan_mantenimiento pm
JOIN programacion_mantenimiento p ON p.id_plan = pm.id
SET 
  pm.area = ?,
  pm.tipo = ?,
  pm.frecuencia = ?,
  pm.responsable = ?
WHERE p.id = ?
");

$stmt->bind_param(
  "ssssi",
  $area,
  $tipo,
  $frecuencia,
  $responsable,
  $id
);

$stmt->execute();

header("Location: ../programacion_mantenimientos.php");
