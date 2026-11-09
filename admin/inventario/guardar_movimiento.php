<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * GUARDAR MOVIMIENTO DE EQUIPO
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= CONFIG ================= */
$base_path = '/home/u568802486/domains/transportesvaladez.com/public_html/ticket/';

if (file_exists($base_path . 'config.php')) {
    require_once $base_path . 'config.php';
} else {
    die("Error cargando configuración.");
}

/* ================= SEGURIDAD ================= */
if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

/* ================= VALIDAR POST ================= */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso inválido.");
}

/* ================= RECIBIR DATOS ================= */
$id_equipo         = intval($_POST['id_equipo'] ?? 0);
$tipo_movimiento   = trim($_POST['tipo_movimiento'] ?? '');
$responsable_nuevo = (!empty($_POST['responsable_nuevo']) && $_POST['responsable_nuevo'] > 0) ? intval($_POST['responsable_nuevo']) : NULL;
$area_nueva        = trim($_POST['area_nueva'] ?? '');
$observaciones     = trim($_POST['observaciones'] ?? '');

/* ================= USUARIO ACTUAL ================= */
$usuario_sesion = $_SESSION['usuario'] ?? null;

if (!$usuario_sesion) {
    die("No hay sesión de usuario activa.");
}

// Consultamos el ID numérico del usuario actual en la BD
$stmt_usr = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ? LIMIT 1");
$stmt_usr->bind_param("s", $usuario_sesion);
$stmt_usr->execute();
$res_usr = $stmt_usr->get_result()->fetch_assoc();

if (!$res_usr) {
    die("Error: No se encontró el registro del usuario en la base de datos.");
}

$usuario_registro = intval($res_usr['id']);

/* ================= VALIDACIONES ================= */
if ($id_equipo <= 0) {
    die("Equipo inválido.");
}

if (empty($tipo_movimiento)) {
    die("Debe seleccionar tipo de movimiento.");
}

/* ================= OBTENER DATOS ACTUALES ================= */
$stmt = $conexion->prepare("
    SELECT responsable, area, estado
    FROM inventario_equipos
    WHERE id = ?
");
$stmt->bind_param("i", $id_equipo);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();

if (!$equipo) {
    die("Equipo no encontrado.");
}

$responsable_anterior = !empty($equipo['responsable']) ? intval($equipo['responsable']) : NULL;
$area_anterior        = $equipo['area'] ?? '';

/* ================= INICIAR TRANSACCIÓN ================= */
$conexion->begin_transaction();

try {

    /* 1. GUARDAR HISTORIAL DE MOVIMIENTO */
    $stmt = $conexion->prepare("
        INSERT INTO inventario_movimientos
        (
            id_equipo,
            tipo_movimiento,
            responsable_anterior,
            responsable_nuevo,
            area_anterior,
            area_nueva,
            usuario_registro,
            fecha_movimiento,
            observaciones
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?, NOW(), ?
        )
    ");

    if (!$stmt) {
        throw new Exception("Error prepare historial: " . $conexion->error);
    }

    // "isiissis" -> id_equipo (i), tipo (s), resp_ant (i), resp_nuev (i), area_ant (s), area_nueva (s), usuario (i), obs (s)
    $stmt->bind_param(
        "isiissis",
        $id_equipo,
        $tipo_movimiento,
        $responsable_anterior,
        $responsable_nuevo,
        $area_anterior,
        $area_nueva,
        $usuario_registro,
        $observaciones
    );

    if (!$stmt->execute()) {
        throw new Exception("Error al guardar historial: " . $stmt->error);
    }

    /* 2. ACTUALIZAR ESTADO DEL EQUIPO EN INVENTARIO */
    switch ($tipo_movimiento) {

        case 'Asignacion':
        case 'Cambio Responsable':
            $area_final = !empty($area_nueva) ? $area_nueva : $area_anterior;

            $stmt_upd = $conexion->prepare("
                UPDATE inventario_equipos
                SET responsable = ?, area = ?, estado = 'Activo'
                WHERE id = ?
            ");
            $stmt_upd->bind_param("isi", $responsable_nuevo, $area_final, $id_equipo);
            break;

        case 'Cambio Area':
            $stmt_upd = $conexion->prepare("
                UPDATE inventario_equipos
                SET area = ?
                WHERE id = ?
            ");
            $stmt_upd->bind_param("si", $area_nueva, $id_equipo);
            break;

        case 'Prestamo':
            $stmt_upd = $conexion->prepare("
                UPDATE inventario_equipos
                SET estado = 'Prestamo', responsable = ?
                WHERE id = ?
            ");
            $stmt_upd->bind_param("ii", $responsable_nuevo, $id_equipo);
            break;

        case 'Reparacion':
            $stmt_upd = $conexion->prepare("
                UPDATE inventario_equipos
                SET estado = 'Reparacion'
                WHERE id = ?
            ");
            $stmt_upd->bind_param("i", $id_equipo);
            break;

        default:
            throw new Exception("Tipo de movimiento no válido.");
    }

    if (!$stmt_upd->execute()) {
        throw new Exception("Error actualizando inventario: " . $stmt_upd->error);
    }

    // Confirmar base de datos
    $conexion->commit();

    /* ================= REDIRECT ================= */
    header("Location: inventario.php?mov=ok");
    exit();

} catch (Exception $e) {
    $conexion->rollback();
    die("Error en la operación: " . $e->getMessage());
}
?>