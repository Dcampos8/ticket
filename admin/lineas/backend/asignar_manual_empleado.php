<?php
require '../../../backend/conexion.php';

$num_linea = $_POST['num_linea'];
$employee_id = $_POST['employee_id'];

$query = $conexion->query("SELECT employee_name FROM employees WHERE id = $employee_id");
$row = $query->fetch_assoc();
$employee_name = $row['employee_name'] ?? null;

$stmt = $conexion->prepare("UPDATE phone_lines SET manual_employee_id=?, manual_employee_name=? WHERE num_linea=?");
$stmt->bind_param("iss", $employee_id, $employee_name, $num_linea);

if($stmt->execute()){
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conexion->error]);
}
?>
