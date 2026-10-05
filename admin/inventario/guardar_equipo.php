<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * GUARDAR NUEVO EQUIPO
 */

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
$tipo_dispositivo  = trim($_POST['tipo_dispositivo'] ?? '');
$tipo_equipo       = trim($_POST['tipo_equipo'] ?? '');
$marca             = trim($_POST['marca'] ?? '');
$modelo            = trim($_POST['modelo'] ?? '');
$no_serie          = trim($_POST['no_serie'] ?? '');
$procesador        = trim($_POST['procesador'] ?? '');
$ram               = trim($_POST['ram'] ?? '');
$disco_duro        = trim($_POST['disco_duro'] ?? '');

/* --- NUEVOS CAMPOS DE PERIFÉRICOS --- */
$marca_teclado     = trim($_POST['marca_teclado'] ?? '');
$modelo_teclado    = trim($_POST['modelo_teclado'] ?? '');
$marca_mouse       = trim($_POST['marca_mouse'] ?? '');
$modelo_mouse      = trim($_POST['modelo_mouse'] ?? '');
$marca_monitor     = trim($_POST['marca_monitor'] ?? '');
$modelo_monitor    = trim($_POST['modelo_monitor'] ?? '');
/* ------------------------------------- */

$area              = trim($_POST['area'] ?? '');
$responsable       = !empty($_POST['responsable']) ? intval($_POST['responsable']) : NULL;
$numero_resguardo  = trim($_POST['numero_resguardo'] ?? '');
$estado            = trim($_POST['estado'] ?? 'Activo');
$motivo_baja       = trim($_POST['motivo_baja'] ?? '');
$motivo_reparacion = trim($_POST['motivo_reparacion'] ?? '');

/* ================= VALIDACIONES ================= */
if ($tipo_dispositivo == '') die("Falta tipo de dispositivo");
if ($marca == '') die("Falta marca");
if ($modelo == '') die("Falta modelo");

/* ================= INSERT ================= */
$sql = "
INSERT INTO inventario_equipos
(
    tipo_dispositivo,
    tipo_equipo,
    marca,
    modelo,
    no_serie,
    procesador,
    ram,
    disco_duro,
    marca_teclado,
    modelo_teclado,
    marca_mouse,
    modelo_mouse,
    marca_monitor,
    modelo_monitor,
    area,
    responsable,
    numero_resguardo,
    estado,
    motivo_baja,
    motivo_reparacion
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
)
";

$stmt = $conexion->prepare($sql);

if (!$stmt) {
    die("Error prepare: " . $conexion->error);
}

/* 
  Explicación de los tipos (20 parámetros en total):
  s = string (tipo_dispositivo)
  s = string (tipo_equipo)
  s = string (marca)
  s = string (modelo)
  s = string (no_serie)
  s = string (procesador)
  s = string (ram)
  s = string (disco_duro)
  s = string (marca_teclado)
  s = string (modelo_teclado)
  s = string (marca_mouse)
  s = string (modelo_mouse)
  s = string (marca_monitor)
  s = string (modelo_monitor)
  s = string (area)
  i = integer (responsable - puede ser null)
  s = string (numero_resguardo)
  s = string (estado)
  s = string (motivo_baja)
  s = string (motivo_reparacion)
*/
$stmt->bind_param(
    "ssssssssssssssssisss",
    $tipo_dispositivo,
    $tipo_equipo,
    $marca,
    $modelo,
    $no_serie,
    $procesador,
    $ram,
    $disco_duro,
    $marca_teclado,
    $modelo_teclado,
    $marca_mouse,
    $modelo_mouse,
    $marca_monitor,
    $modelo_monitor,
    $area,
    $responsable,
    $numero_resguardo,
    $estado,
    $motivo_baja,
    $motivo_reparacion
);

if (!$stmt->execute()) {
    die("Error al guardar: " . $stmt->error);
}

/* ================= REDIRECT ================= */
header("Location: inventario.php?ok=1");
exit();
?>