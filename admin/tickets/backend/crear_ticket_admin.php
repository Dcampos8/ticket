<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once '../../../backend/conexion.php';
require_once '../../../shared/permisos.php';
require_once '../../../shared/security.php';
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
$comentarioAdmin = trim((string) ($_POST['comentario_admin'] ?? ''));
$estadosPermitidos = ['Pendiente', 'En proceso', 'Finalizado', 'Cancelado'];
$tiposPermitidos = [
    'Soporte', 'Soporte técnico', 'Hardware', 'Software y sistemas', 'Red e internet',
    'Accesos y cuentas', 'Correo electrónico', 'Telefonía', 'Impresoras',
    'Ajuste facturas', 'Camaras', 'Capacitacion', 'Diseño', 'Otra Actividad',
];

if ($nombreUsuario === '' || $descripcion === ''
    || mb_strlen($nombreUsuario, 'UTF-8') > 100
    || mb_strlen($nombreCompleto, 'UTF-8') > 150
    || mb_strlen($area, 'UTF-8') > 150
    || mb_strlen($puesto, 'UTF-8') > 150
    || mb_strlen($descripcion, 'UTF-8') > 10000
    || mb_strlen($comentarioAdmin, 'UTF-8') > 10000
    || !in_array($estatus, $estadosPermitidos, true)
    || !in_array($tipoTicket, $tiposPermitidos, true)
    || !DateTime::createFromFormat('Y-m-d\TH:i', $fechaCreacion)) {
    responderCreacionAdmin(422, ['status' => 'error', 'message' => 'Revisa los datos del ticket.']);
}
$fechaCreacion = str_replace('T', ' ', $fechaCreacion) . ':00';

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
    'INSERT INTO tickets (nombre, nombre_completo, area, puesto, descripcion, tipo_ticket, estatus, fecha_creacion, comentario_admin, imagen, imagen_admin, archivo_admin)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
if (!$stmt) {
    foreach ($archivosGuardados as $ruta) {
        @unlink(__DIR__ . '/../../../uploads/' . $ruta);
    }
    error_log('Crear ticket admin: no se pudo preparar el registro.');
    responderCreacionAdmin(500, ['status' => 'error', 'message' => 'No se pudo registrar el ticket.']);
}
$stmt->bind_param(
    'ssssssssssss',
    $nombreUsuario,
    $nombreCompleto,
    $area,
    $puesto,
    $descripcion,
    $tipoTicket,
    $estatus,
    $fechaCreacion,
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
$conexion->close();
echo json_encode(['status' => 'ok', 'ticketId' => $ticketId], JSON_UNESCAPED_UNICODE);
