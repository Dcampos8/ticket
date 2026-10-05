<?php
header('Content-Type: application/json; charset=utf-8');
require '../../../backend/conexion.php';

$num_linea = $_POST['num_linea'] ?? '';
$estatus = $_POST['estatus'] ?? '';

if (empty($num_linea) || empty($estatus)) {
    echo json_encode([
        'success' => false,
        'error' => 'Faltan datos requeridos (num_linea o estatus).'
    ]);
    exit;
}

// Limpiar valor
$num_linea = trim(str_replace(['[', ']'], '', $num_linea));

// 🔹 Forzar la actualización aunque MySQL crea que no cambió
$sql = "
    UPDATE phone_lines
    SET 
        estatus_linea = CONCAT('', ?),
        activo = IF(? = 'Activa', 1, 0)
    WHERE TRIM(REPLACE(REPLACE(num_linea, '[', ''), ']', '')) = ?
";
$stmt = $conexion->prepare($sql);
$stmt->bind_param("sss", $estatus, $estatus, $num_linea);
$stmt->execute();

// Comprobar resultado
$response = [
    'success' => $stmt->affected_rows > 0,
    'num_linea' => $num_linea,
    'estatus' => $estatus,
    'affected_rows' => $stmt->affected_rows
];

// Si no actualizó, mostrar detalle
if ($stmt->affected_rows === 0) {
    $check = $conexion->prepare("SELECT estatus_linea FROM phone_lines WHERE TRIM(REPLACE(REPLACE(num_linea, '[', ''), ']', '')) = ?");
    $check->bind_param("s", $num_linea);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $response['error'] = 'No se actualizó, pero la línea existe.';
        $response['valor_actual'] = $row['estatus_linea'];
    } else {
        $response['error'] = 'No se encontró la línea.';
    }

    $check->close();
}

$stmt->close();
$conexion->close();

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
