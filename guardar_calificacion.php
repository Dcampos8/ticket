<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$entrega_id   = isset($_POST['entrega_id']) ? intval($_POST['entrega_id']) : 0;
$colaborador  = isset($_POST['colaborador']) ? trim($_POST['colaborador']) : '';
$calificacion = isset($_POST['calificacion']) ? intval($_POST['calificacion']) : 0;
$comentarios  = isset($_POST['comentarios']) ? trim($_POST['comentarios']) : '';

if ($entrega_id <= 0 || empty($colaborador) || $calificacion < 1 || $calificacion > 5) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos de evaluación incompletos o inválidos.']);
    exit;
}

try {
    // Consulta preparada nativa para MySQLi
    $sql = "INSERT INTO checklist_calificaciones (entrega_id, colaborador_nom, calificacion, comentarios, fecha_evaluacion) 
            VALUES (?, ?, ?, ?, NOW())";
            
    $stmt = mysqli_prepare($conexion, $sql); // Cambia $conexion si tu variable en config.php se llama diferente (ej: $conn)
    
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "isis", $entrega_id, $colaborador, $calificacion, $comentarios);
        
        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true, 'message' => 'Evaluación guardada correctamente.']);
        } else {
            throw new Exception(mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);
    } else {
        throw new Exception(mysqli_error($conexion));
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno en el servidor: ' . $e->getMessage()]);
}