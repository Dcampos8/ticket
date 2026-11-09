<?php
/* ================= MOSTRAR ERRORES (SOLO DESARROLLO) ================= */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* ================= SESIÓN ================= */
session_start();
require('../../../backend/conexion.php');

/* ================= VALIDAR SESIÓN ================= */
if (!isset($_SESSION['logueado'])) {
    die('Acceso no autorizado');
}

if (!isset($_SESSION['usuario'])) {
    die('Usuario no identificado');
}

/* ================= RECIBIR DATOS ================= */
$id_programacion       = isset($_POST['id_programacion']) ? intval($_POST['id_programacion']) : 0;
$id_responsable_notif  = isset($_POST['id_responsable_notif']) ? intval($_POST['id_responsable_notif']) : 0;
$fecha_real            = $_POST['fecha_real'] ?? null;
$actividades           = $_POST['actividades'] ?? '';
$refacciones           = $_POST['refacciones'] ?? '';
$observaciones         = $_POST['observaciones'] ?? '';

$tecnico = $_SESSION['usuario']; // ej. "jlopez"

/* ================= VALIDACIONES BÁSICAS ================= */
if ($id_programacion <= 0 || empty($fecha_real)) {
    die('Datos incompletos');
}

/* ================= INSERT MANTENIMIENTO ================= */
$stmt = $conexion->prepare(
    "INSERT INTO mantenimientos_realizados
    (id_programacion, fecha_real, tecnico, actividades, refacciones, observaciones)
    VALUES (?,?,?,?,?,?)"
);

if (!$stmt) {
    die("Error prepare mantenimiento: " . $conexion->error);
}

$stmt->bind_param(
    "isssss",
    $id_programacion,
    $fecha_real,
    $tecnico,
    $actividades,
    $refacciones,
    $observaciones
);

if (!$stmt->execute()) {
    die("Error execute mantenimiento: " . $stmt->error);
}

/* ================= UPDATE ESTATUS PROGRAMACIÓN ================= */
$stmtUpd = $conexion->prepare(
    "UPDATE programacion_mantenimiento
     SET estatus = 'Realizado'
     WHERE id = ?"
);

if (!$stmtUpd) {
    die("Error prepare update: " . $conexion->error);
}

$stmtUpd->bind_param("i", $id_programacion);

if (!$stmtUpd->execute()) {
    die("Error update estatus: " . $stmtUpd->error);
}

/* ================= DISPARAR NOTIFICACIÓN PARA EVALUACIÓN ================= */
// Si tenemos un responsable válido, creamos la notificación
if ($id_responsable_notif > 0) {
    $mensaje_notif = "El mantenimiento #$id_programacion ha sido realizado. Por favor, califica el servicio.";
    
    $stmtNotif = $conexion->prepare(
        "INSERT INTO notificaciones (id_usuario, id_mantenimiento, mensaje) 
         VALUES (?, ?, ?)"
    );
    
    if ($stmtNotif) {
        $stmtNotif->bind_param("iis", $id_responsable_notif, $id_programacion, $mensaje_notif);
        $stmtNotif->execute();
    }
}

/* ================= GENERAR TICKET CORRECTIVO ================= */
if (isset($_POST['generar_ticket']) && !empty($observaciones) && !empty($actividades)) {

    /* AJUSTADO A TABLA tickets (SIN titulo) */
    $stmtTicket = $conexion->prepare(
        "INSERT INTO tickets
        (descripcion, comentario_admin, estatus, fecha_creacion)
        VALUES (?, 'Finalizado', NOW())"
    );

    if (!$stmtTicket) {
        die("Error prepare ticket: " . $conexion->error);
    }

    $stmtTicket->bind_param("s", $observaciones);

    if (!$stmtTicket->execute()) {
        die("Error execute ticket: " . $stmtTicket->error);
    }

    /* MARCAR QUE SE GENERÓ TICKET */
    $stmtFlag = $conexion->prepare(
        "UPDATE mantenimientos_realizados
         SET ticket_generado = 1
         WHERE id_programacion = ?"
    );

    if ($stmtFlag) {
        $stmtFlag->bind_param("i", $id_programacion);
        $stmtFlag->execute();
    }
}

/* ================= REDIRECCIÓN ================= */
// Usamos un script JS para cerrar el modal y recargar el calendario si es necesario
echo "<script>
    alert('Mantenimiento guardado correctamente. Se ha enviado una notificación al responsable para su evaluación.');
    window.parent.location.reload(); 
</script>";
exit;