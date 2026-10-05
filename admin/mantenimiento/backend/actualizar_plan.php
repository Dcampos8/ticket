<?php
require('../../../backend/conexion.php');

$id = (int)$_POST['id'];

$conexion->query("
UPDATE plan_mantenimiento SET
  id_equipo   = {$_POST['id_equipo']},
  area        = '{$_POST['area']}',
  tipo        = '{$_POST['tipo']}',
  frecuencia  = '{$_POST['frecuencia']}',
  responsable = {$_POST['responsable']},
  anio        = {$_POST['anio']}
WHERE id = $id
");

header("Location: ../plan_mantenimiento.php");
