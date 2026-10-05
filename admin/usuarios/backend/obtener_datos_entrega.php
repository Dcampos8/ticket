<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json; charset=utf-8');

require '../../../backend/conexion.php';

// Obtener el employee_id desde GET
$employee_id = intval($_GET['employee_id'] ?? 0);

if ($employee_id <= 0) {
    echo json_encode(['error' => 'employee_id no válido']);
    exit;
}

// Consulta usando employee_id
$sql = "SELECT * FROM phone_deliveries WHERE employee_id = $employee_id";
$result = $conexion->query($sql);

if (!$result) {
    echo json_encode(['error' => 'Error en la consulta: ' . $conexion->error]);
    exit;
}

if ($result->num_rows === 0) {
    echo json_encode(['error' => 'No se encontraron datos del empleado']);
    exit;
}

$entrega = $result->fetch_assoc();

// Devolver los campos necesarios
echo json_encode([
    'employee_id'    => $entrega['employee_id'],      // hidden
    'employee_name'  => $entrega['employee_name'] ?? 'Sin nombre',
    'puesto'         => $entrega['puesto'] ?? 'No especificado',
    'num_linea'      => $entrega['num_linea'] ?? 'Sin número',
    'marca'          => $entrega['marca'] ?? 'No registrada',
    'modelo'         => $entrega['modelo'] ?? 'No registrado',
    'imei'           => $entrega['imei'] ?? 'No registrado',
    'google_account' => $entrega['google_account'] ?? 'No registrada',
    'estatus_linea'  => $entrega['estatus_linea'] ?? 'Sin estatus'
]);
