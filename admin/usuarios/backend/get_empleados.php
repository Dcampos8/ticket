<?php
require '../../../backend/conexion.php';

header('Content-Type: application/json');

$query = "SELECT id, employee_id, employee_name, puesto FROM employees ORDER BY employee_name ASC";
$result = $conexion->query($query);

$empleados = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $empleados[] = $row;
    }
}

echo json_encode($empleados);
?>
