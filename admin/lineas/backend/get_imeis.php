<?php
header('Content-Type: application/json');
require '../../../backend/conexion.php';

$employee_id   = $_GET['employee_id'] ?? '';
$employee_name = $_GET['employee_name'] ?? '';

$sql = "SELECT imei, brand, model FROM phone_stock WHERE status='assigned'";
$params = [];
$types = "";

// Solo usar ID si existe
if ($employee_id !== '') {
    $sql .= " AND assigned_employee_id = ?";
    $params[] = $employee_id;
    $types .= "s";
} elseif ($employee_name !== '') {
    // Si no hay ID, usar nombre
    $sql .= " AND assigned_employee_name LIKE ?";
    $params[] = "%$employee_name%";
    $types .= "s";
}

$stmt = $conexion->prepare($sql);
if (!$stmt) {
    echo json_encode([]);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$imeis = [];
while ($row = $result->fetch_assoc()) {
    $imeis[] = [
        'imei'  => $row['imei'],
        'brand' => $row['brand'],
        'model' => $row['model']
    ];
}

$stmt->close();

// 🔧 Seguridad: siempre devolver JSON válido
if (empty($imeis)) {
    echo json_encode([]);
    exit;
}

echo json_encode($imeis);
?>
