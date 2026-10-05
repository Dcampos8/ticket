<?php
session_start();
header('Content-Type: application/json');
include('../../../backend/conexion.php');

$conexion->set_charset("utf8mb4");

function subirArchivo($fileKey, $prefix) {
    if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES[$fileKey]['name'], PATHINFO_EXTENSION);
        $nombreArchivo = $prefix . '_' . uniqid() . '.' . $ext;
        $rutaDestino = '../../../uploads/' . $nombreArchivo;

        if (move_uploaded_file($_FILES[$fileKey]['tmp_name'], $rutaDestino)) {
            return $nombreArchivo;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre_usuario   = $_POST['nombre'] ?? '';
    $nombre_completo  = $_POST['nombre_completo'] ?? '';
    $area             = $_POST['area'] ?? '';
    $puesto           = $_POST['puesto'] ?? ''; // 🔥 IMPORTANTE
    $descripcion      = $_POST['descripcion'] ?? '';

    if (empty($nombre_usuario) || empty($descripcion)) {
        echo json_encode(["status"=>"error","message"=>"Faltan datos"]);
        exit;
    }

    $tipo_ticket      = $_POST['tipo_ticket'] ?? 'Soporte';
    $estatus          = $_POST['estatus'] ?? 'En proceso';
    $fecha_creacion   = $_POST['fecha_creacion'] ?? date('Y-m-d H:i:s');
    $comentario_admin = $_POST['comentario_admin'] ?? null;

    $img_user  = subirArchivo('imagen', 'ticket');
    $img_admin = subirArchivo('imagen_admin', 'admin');
    $doc_admin = subirArchivo('archivo_admin', 'doc');

    $sql = "INSERT INTO tickets (
        nombre, nombre_completo, area, puesto,
        descripcion, tipo_ticket, estatus, fecha_creacion,
        comentario_admin, imagen, imagen_admin, archivo_admin
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conexion->prepare($sql);

    $stmt->bind_param("ssssssssssss",
        $nombre_usuario,
        $nombre_completo,
        $area,
        $puesto,
        $descripcion,
        $tipo_ticket,
        $estatus,
        $fecha_creacion,
        $comentario_admin,
        $img_user,
        $img_admin,
        $doc_admin
    );

    if ($stmt->execute()) {
        echo json_encode([
            "status" => "ok",
            "ticketId" => $conexion->insert_id // 🔥 YA COINCIDE
        ]);
    } else {
        echo json_encode([
            "status" => "error",
            "message" => $stmt->error
        ]);
    }

    $stmt->close();
}