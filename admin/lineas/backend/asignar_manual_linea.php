<?php
ob_start();
require '../../../backend/conexion.php';
ob_clean();

header('Content-Type: application/json');


$response = ["success" => false];

$num = $_POST['num_linea'] ?? '';

if (!$num) {
    $response['error'] = "Línea inválida";
    echo json_encode($response);
    exit;
}

$stmt = null;

/* ============================
   ASIGNACIÓN MANUAL / RESGUARDO
   ============================ */
if (isset($_POST['manual_name'])) {

    $manualName = trim($_POST['manual_name']);
    $operadorAnterior = trim($_POST['operador_anterior'] ?? '');

    $stmt = $conexion->prepare("
        UPDATE phone_lines SET
            manual_employee_name = ?,
            manual_employee_id = NULL,
            operador_anterior = ?,
            en_resguardo = 1
        WHERE num_linea = ?
    ");

    if (!$stmt) {
        echo json_encode(["success"=>false,"error"=>$conexion->error]);
        exit;
    }

    $stmt->bind_param("sss", $manualName, $operadorAnterior, $num);
}

/* ============================
   REASIGNACIÓN A EMPLEADO
   ============================ */
else {

    $empId = $_POST['employee_id'] ?? '';

    if (!$empId) {
        echo json_encode(["success"=>false,"error"=>"Empleado inválido"]);
        exit;
    }

    /* OBTENER NOMBRE DEL EMPLEADO */
    $q = $conexion->prepare("
        SELECT employee_name 
        FROM employees
        WHERE employee_id = ?
        LIMIT 1
    ");

    if (!$q) {
        echo json_encode(["success"=>false,"error"=>$conexion->error]);
        exit;
    }

    $q->bind_param("s", $empId);
    $q->execute();
    $q->bind_result($nombreEmpleado);
    $q->fetch();
    $q->close();

    if (!$nombreEmpleado) {
        echo json_encode(["success"=>false,"error"=>"Empleado no encontrado"]);
        exit;
    }

    $stmt = $conexion->prepare("
        UPDATE phone_lines SET
            manual_employee_id = ?,
            manual_employee_name = ?,
            operador_anterior = NULL,
            en_resguardo = 0
        WHERE num_linea = ?
    ");

    if (!$stmt) {
        echo json_encode(["success"=>false,"error"=>$conexion->error]);
        exit;
    }

    $stmt->bind_param("sss", $empId, $nombreEmpleado, $num);
}

/* ============================
   EJECUTAR
   ============================ */
if ($stmt && $stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode([
        "success" => false,
        "error" => $stmt ? $stmt->error : "Statement no inicializado"
    ]);
}
