<?php
session_start();
require '../../../backend/conexion.php';

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    echo json_encode(["success" => false]);
    exit();
}

$id = $_POST['id'] ?? null;
$num = $_POST['num_linea'] ?? null;
$estatus = $_POST['estatus_linea'] ?? null;

if(!$id || !$num || !$estatus){
    echo json_encode(["success" => false]);
    exit();
}

$stmt = $conexion->prepare("
    UPDATE phone_lines
    SET num_linea = ?, estatus_linea = ?
    WHERE id = ?
");

$stmt->bind_param("ssi", $num, $estatus, $id);

if($stmt->execute()){
    echo json_encode(["success" => true]);
}else{
    echo json_encode(["success" => false]);
}
