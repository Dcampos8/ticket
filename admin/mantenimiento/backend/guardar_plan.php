<?php
require('../../../backend/conexion.php');

$stmt = $conexion->prepare("
  INSERT INTO plan_mantenimiento
  (id_equipo, area, tipo, frecuencia, responsable, anio, activo)
  VALUES (?,?,?,?,?,?,1)
");

$stmt->bind_param(
  "isssii",
  $_POST['id_equipo'],
  $_POST['area'],
  $_POST['tipo'],
  $_POST['frecuencia'],
  $_POST['responsable'],
  $_POST['anio']
);

$stmt->execute();
header("Location: ../plan_mantenimiento.php");
