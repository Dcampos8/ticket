<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';
require_once '../../../shared/security.php';
require_once '../../../shared/ticket_activity.php';
$conexion->set_charset('utf8mb4');
date_default_timezone_set('America/Mexico_City');

function responderCreacionAdmin(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderCreacionAdmin(405, ['status' => 'error', 'message' => 'Método no permitido.']);
}
if (empty($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
    responderCreacionAdmin(403, ['status' => 'error', 'message' => 'No autorizado.']);
}
if (!validarTokenCsrf()) {
    responderCreacionAdmin(403, ['status' => 'error', 'message' => 'La sesión expiró. Recarga la página e intenta de nuevo.']);
}

/** Valida y guarda imágenes o documentos permitidos con un nombre aleatorio. */
function subirArchivoSeguro(string $fileKey, string $prefix, bool $esImagen)
{
    if (!isset($_FILES[$fileKey]) || (int) ($_FILES[$fileKey]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $archivo = $_FILES[$fileKey];
    $limite = $esImagen ? 5 * 1024 * 1024 : 10 * 1024 * 1024;
    $tmp = (string) ($archivo['tmp_name'] ?? '');
    $size = $tmp !== '' ? @filesize($tmp) : false;
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || $tmp === '' || !is_uploaded_file($tmp)
        || $size === false || $size < 1 || $size > $limite
        || !class_exists('finfo')) {
        return false;
    }

    $mimeDetector = new finfo(FILEINFO_MIME_TYPE);
    $mime = $mimeDetector->file($tmp);
    if ($esImagen) {
        $info = @getimagesize($tmp);
        $extensiones = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if (!is_array($info) || !isset($extensiones[$mime])
            || ((int) $info[0] * (int) $info[1]) > 30000000) {
            return false;
        }
    } else {
        $extensiones = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        ];
        if (!isset($extensiones[$mime])) {
            return false;
        }
    }

    $nombre = $prefix . '_' . bin2hex(random_bytes(16)) . '.' . $extensiones[$mime];
    $destino = __DIR__ . '/../../../uploads/' . $nombre;
    return move_uploaded_file($tmp, $destino) ? $nombre : false;
}

$nombreUsuario = trim((string) ($_POST['nombre'] ?? ''));
$nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
$area = trim((string) ($_POST['area'] ?? ''));
$puesto = trim((string) ($_POST['puesto'] ?? ''));
$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$tipoTicket = trim((string) ($_POST['tipo_ticket'] ?? 'Soporte técnico'));
$estatus = trim((string) ($_POST['estatus'] ?? 'En proceso'));
$fechaCreacion = trim((string) ($_POST['fecha_creacion'] ?? date('Y-m-d H:i:s')));
$fechaPrimeraRespuesta = trim((string) ($_POST['fecha_primera_respuesta'] ?? ''));
$fechaResolucion = trim((string) ($_POST['fecha_resolucion'] ?? ''));
$fechaModificacion = trim((string) ($_POST['fecha_modificacion'] ?? ''));
$prioridad = trim((string) ($_POST['prioridad'] ?? 'Normal'));
$asignadoA = trim((string) ($_POST['asignado_a'] ?? ''));
$comentarioAdmin = trim((string) ($_POST['comentario_admin'] ?? ''));
$prioridadesPermitidas = ['Baja', 'Normal', 'Alta', 'Urgente'];
$normalizarFecha = static function (string $valor): ?string {
    if ($valor === '') return null;
    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i', $valor);
    $errores = DateTimeImmutable::getLastErrors();
    if (!$fecha || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0)) || $fecha->format('Y-m-d\\TH:i') !== $valor) return null;
    return $fecha->format('Y-m-d H:i:s');
};
$fechaCreacionNormalizada = $normalizarFecha($fechaCreacion);
$fechaPrimeraRespuestaNormalizada = $normalizarFecha($fechaPrimeraRespuesta);
$fechaResolucionNormalizada = $normalizarFecha($fechaResolucion);
$fechaModificacionNormalizada = $normalizarFecha($fechaModificacion) ?? date('Y-m-d H:i:s');
$estadosPermitidos = ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'];
if ($nombreUsuario === '' || $descripcion === ''
    || mb_strlen($nombreUsuario, 'UTF-8') > 100
    || mb_strlen($nombreCompleto, 'UTF-8') > 150
    || mb_strlen($area, 'UTF-8') > 150
    || mb_strlen($puesto, 'UTF-8') > 150
    || mb_strlen($descripcion, 'UTF-8') > 10000
    || mb_strlen($comentarioAdmin, 'UTF-8') > 10000
    || !in_array($estatus, $estadosPermitidos, true)
    || !in_array($prioridad, $prioridadesPermitidas, true)
    || $tipoTicket === '' || mb_strlen($tipoTicket, 'UTF-8') > 100
    || $fechaCreacionNormalizada === null
    || ($fechaPrimeraRespuesta !== '' && $fechaPrimeraRespuestaNormalizada === null)
    || ($fechaResolucion !== '' && $fechaResolucionNormalizada === null)
    || ($fechaModificacion !== '' && $normalizarFecha($fechaModificacion) === null)
    || mb_strlen($asignadoA, 'UTF-8') > 50) {
    responderCreacionAdmin(422, ['status' => 'error', 'message' => 'Revisa los datos del ticket.']);
}
$fechaCreacion = $fechaCreacionNormalizada;
if ($asignadoA !== '') {
    $stmtResponsable = $conexion->prepare("SELECT rol, modulos_permitidos FROM usuarios WHERE usuario = ? AND rol IN ('admin','superadmin') LIMIT 1");
    $stmtResponsable->bind_param('s', $asignadoA);
    $stmtResponsable->execute();
    $responsable = $stmtResponsable->get_result()->fetch_assoc();
    $stmtResponsable->close();
    if (!$responsable || ($responsable['rol'] !== 'superadmin' && !in_array('tickets', parsearModulos($responsable['modulos_permitidos'] ?? ''), true))) {
        responderCreacionAdmin(422, ['status' => 'error', 'message' => 'Selecciona un responsable válido del módulo de tickets.']);
    }
}

$archivosGuardados = [];
$imgUser = subirArchivoSeguro('imagen', 'ticket', true);
$imgAdmin = subirArchivoSeguro('imagen_admin', 'admin_ticket', true);
$docAdmin = subirArchivoSeguro('archivo_admin', 'doc_ticket', false);
foreach ([$imgUser, $imgAdmin, $docAdmin] as $archivoGuardado) {
    if ($archivoGuardado === false) {
        foreach ($archivosGuardados as $ruta) {
            @unlink(__DIR__ . '/../../../uploads/' . $ruta);
        }
        responderCreacionAdmin(422, ['status' => 'error', 'message' => 'Revisa los adjuntos: imágenes JPG/PNG/WebP de hasta 5 MB y documentos PDF/DOC/DOCX de hasta 10 MB.']);
    }
    if (is_string($archivoGuardado)) {
        $archivosGuardados[] = $archivoGuardado;
    }
}

$stmt = $conexion->prepare(
    "INSERT INTO tickets (nombre, nombre_completo, area, puesto, descripcion, tipo_ticket, estatus, prioridad, asignado_a, objetivo_respuesta_minutos, objetivo_resolucion_minutos, fecha_creacion, fecha_modificacion, fecha_primera_respuesta, fecha_resolucion, comentario_admin, imagen, imagen_admin, archivo_admin)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''),
        COALESCE((SELECT respuesta_minutos FROM ticket_sla_politicas WHERE prioridad = ?), 480),
        COALESCE((SELECT resolucion_minutos FROM ticket_sla_politicas WHERE prioridad = ?), 2880),
        ?, ?, ?, ?, ?, ?, ?, ?)"
);
if (!$stmt) {
    foreach ($archivosGuardados as $ruta) {
        @unlink(__DIR__ . '/../../../uploads/' . $ruta);
    }
    error_log('Crear ticket admin: no se pudo preparar el registro.');
    responderCreacionAdmin(500, ['status' => 'error', 'message' => 'No se pudo registrar el ticket.']);
}
$stmt->bind_param(
    'sssssssssssssssssss',
    $nombreUsuario,
    $nombreCompleto,
    $area,
    $puesto,
    $descripcion,
    $tipoTicket,
    $estatus,
    $prioridad,
    $asignadoA,
    $prioridad,
    $prioridad,
    $fechaCreacion,
    $fechaModificacionNormalizada,
    $fechaPrimeraRespuestaNormalizada,
    $fechaResolucionNormalizada,
    $comentarioAdmin,
    $imgUser,
    $imgAdmin,
    $docAdmin
);
if (!$stmt->execute()) {
    foreach ($archivosGuardados as $ruta) {
        @unlink(__DIR__ . '/../../../uploads/' . $ruta);
    }
    $stmt->close();
    error_log('Crear ticket admin: falló el registro en la base de datos.');
    responderCreacionAdmin(500, ['status' => 'error', 'message' => 'No se pudo registrar el ticket.']);
}

$ticketId = $stmt->insert_id;
$stmt->close();
registrarActividadTicket($conexion, $ticketId, 'creacion', 'Ticket creado desde el panel de administración.', false);
$conexion->close();
echo json_encode(['status' => 'ok', 'ticketId' => $ticketId], JSON_UNESCAPED_UNICODE);
