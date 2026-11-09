<?php
require '../../../backend/conexion.php';

$result = $conexion->query("
    SELECT employee_id, employee_name 
    FROM employees 
    ORDER BY CAST(employee_id AS UNSIGNED) ASC
");


$empleados = [];

while ($e = $result->fetch_assoc()) {
    $empleados[] = $e;
}

echo json_encode($empleados);
