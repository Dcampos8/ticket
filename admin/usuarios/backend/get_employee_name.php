<?php
require '../../../backend/conexion.php';
header('Content-Type: application/json');

$employee_id = $_GET['employee_id'] ?? '';

if (!empty($employee_id)) {
    $stmt = $conexion->prepare("SELECT employee_name, puesto FROM employees WHERE employee_id=? LIMIT 1");
    $stmt->bind_param("s", $employee_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        echo json_encode([
            'name' => $row['employee_name'],
            'puesto' => $row['puesto']
        ]);
    } else {
        echo json_encode(['name' => null, 'puesto' => null]);
    }
    $stmt->close();
} else {
    echo json_encode(['name' => null, 'puesto' => null]);
}
