<?php
// Ruta: ticket/admin/usuarios/backend/eliminar_usuario.php

// 1. Config global y sesión (igual que index.php)
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'backend/conexion.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Mismo sistema de permisos que usa el listado (no un check manual de $_SESSION['rol'])
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');

// 3. Validar dato recibido
if (!isset($_POST['usuario_id'])) {
    die("ID no recibido");
}

$id = intval($_POST['usuario_id']);

// Evita borrar una cuenta superadmin mediante una petición directa.
if (!esSuperAdmin()) {
    $check = $conexion->prepare("SELECT rol FROM usuarios WHERE id = ?");
    $check->bind_param("i", $id);
    $check->execute();
    $rolObjetivo = $check->get_result()->fetch_assoc()['rol'] ?? null;
    $check->close();
    if (strtolower(trim((string) $rolObjetivo)) === 'superadmin') {
        http_response_code(403);
        exit('No tienes permiso para eliminar esta cuenta.');
    }
}

// 4. Eliminar
$stmt = $conexion->prepare("DELETE FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    // Desde backend/, ../ ya te deja en usuarios/ (donde vive index.php)
    header("Location: ../index.php?eliminado=1");
    exit();
} else {
    echo "Error al eliminar: " . $stmt->error;
}

$stmt->close();
$conexion->close();
?>