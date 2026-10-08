<?php
session_start();
require '../../../backend/conexion.php';
require_once '../../../shared/security.php';

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "No autorizado"]);
    exit();
}
if (!validarTokenCsrf()) {
    http_response_code(403);
    echo json_encode(["success" => false, "error" => "La sesión expiró. Recarga la página e intenta de nuevo."]);
    exit();
}
header('Content-Type: application/json; charset=utf-8');

$id = $_POST['id'] ?? 0;
$usuario = $_SESSION['usuario'];

if(!$id){
    echo json_encode(["success" => false, "error" => "ID inválido"]);
    exit();
}

// ⚠️ Solo cancelar si:
// - El ticket es del usuario
// - El estatus es Pendiente
$stmt = $conexion->prepare("
    UPDATE tickets 
    SET estatus = 'Cancelado', fecha_modificacion = NOW()
    WHERE id = ? AND nombre = ? AND estatus = 'Pendiente'
");
$stmt->bind_param("is", $id, $usuario);
$stmt->execute();

if($stmt->affected_rows > 0){
    echo json_encode(["success" => true]);
}else{
    echo json_encode(["success" => false, "error" => "El ticket ya no se puede cancelar"]);
}
