<?php
session_start();
require('../../backend/conexion.php');

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['logueado'])) {
    http_response_code(403);
    echo json_encode([]);
    exit;
}

$id_usuario = (int) $_SESSION['id'];

$stmt = mysqli_prepare($conexion, "
    SELECT id, usuario, nombre_completo, rol, area
    FROM usuarios
    WHERE id != ?
    ORDER BY nombre_completo ASC
");
mysqli_stmt_bind_param($stmt, "i", $id_usuario);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$usuarios = [];
while ($row = mysqli_fetch_assoc($res)) {
    $usuarios[] = [
        'id' => (int) $row['id'],
        'usuario' => $row['usuario'],
        'nombre' => $row['nombre_completo'] ?: $row['usuario'],
        'rol' => $row['rol'],
        'area' => $row['area'],
    ];
}

echo json_encode($usuarios);
