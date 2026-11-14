<?php
session_start();
require_once __DIR__ . '/../../../shared/security.php';
require_once __DIR__ . '/../../../shared/integration_settings.php';
require_once __DIR__ . '/../../../shared/ticket_clasificacion.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../backend/PHPMailer/src/Exception.php';
require '../../../backend/PHPMailer/src/PHPMailer.php';
require '../../../backend/PHPMailer/src/SMTP.php';

date_default_timezone_set('America/Mexico_City');
$fecha_creacion = date("Y-m-d H:i:s");

// ===============================
// 🔹 AUTENTICACIÓN E IDENTIDAD
// ===============================
$esApiIntranet = defined('TICKET_CREATION_TRUSTED_API') && TICKET_CREATION_TRUSTED_API === true;
if (!$esApiIntranet) {
    header('Content-Type: application/json; charset=utf-8');
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        echo json_encode(['status' => 'error', 'error' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (empty($_SESSION['logueado']) || ($_SESSION['rol'] ?? '') !== 'usuario') {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'Inicia sesión como usuario para crear un ticket.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!validarTokenCsrf()) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'error' => 'La sesión del formulario expiró. Recarga la página e intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // La identidad del formulario siempre sale de la sesión, no de campos ocultos editables.
    $usuario = (string) ($_SESSION['usuario'] ?? '');
    $area = (string) ($_SESSION['area'] ?? '');
    $nombre = (string) ($_SESSION['nombre_completo'] ?? '');
} else {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $nombre = trim((string) ($_POST['nombre'] ?? ''));
}

$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
$nombre_imagen = null;
$imagenExtension = null;
$limiteImagen = 5 * 1024 * 1024;
if ($usuario === '' || $area === '' || $nombre === '' || $descripcion === ''
    || mb_strlen($usuario, 'UTF-8') > 100 || mb_strlen($area, 'UTF-8') > 150
    || mb_strlen($nombre, 'UTF-8') > 150 || mb_strlen($descripcion, 'UTF-8') > 10000) {
    http_response_code(422);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'Revisa que los datos estén completos y dentro de los límites permitidos.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_FILES['imagen']) && (int) ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $archivo = $_FILES['imagen'];
    $tamanioReal = isset($archivo['tmp_name']) ? @filesize($archivo['tmp_name']) : false;
    $infoImagen = isset($archivo['tmp_name']) && is_uploaded_file($archivo['tmp_name']) ? @getimagesize($archivo['tmp_name']) : false;
    $extensionesPermitidas = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mimeImagen = is_array($infoImagen) ? ($infoImagen['mime'] ?? '') : '';
    $anchoImagen = is_array($infoImagen) ? (int) ($infoImagen[0] ?? 0) : 0;
    $altoImagen = is_array($infoImagen) ? (int) ($infoImagen[1] ?? 0) : 0;
    if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
        || $tamanioReal === false || $tamanioReal < 1 || $tamanioReal > $limiteImagen
        || !isset($extensionesPermitidas[$mimeImagen])
        || $anchoImagen < 1 || $altoImagen < 1 || ($anchoImagen * $altoImagen) > 30000000) {
        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'error' => 'La imagen debe ser JPG, PNG o WebP y no superar 5 MB.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $imagenExtension = $extensionesPermitidas[$mimeImagen];
}

$clasificacion = clasificarSolicitud($descripcion);

if ($clasificacion === null) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'error',
        'error' => $GLOBALS['gemini_classification_issue'] ?? 'No se pudo clasificar la solicitud con IA. Intenta de nuevo en unos minutos.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$tipo_ticket = $clasificacion['tipo_ticket'];
$prioridad = $clasificacion['prioridad'];

require_once __DIR__ . '/../../../backend/conexion.php';

// DEBUG OPCIONAL
// error_log(print_r($_POST, true));
// error_log("TIPO_TICKET RECIBIDO: " . $tipo_ticket);

// ===============================
// 🖼️ SUBIR IMAGEN (SI EXISTE)
// ===============================
if ($imagenExtension !== null) {
    $archivo_tmp = $_FILES['imagen']['tmp_name'];
    $nombre_imagen = 'ticket_' . bin2hex(random_bytes(16)) . '.' . $imagenExtension;
    $ruta_destino = __DIR__ . '/../../../uploads/' . $nombre_imagen;
    if (!move_uploaded_file($archivo_tmp, $ruta_destino)) {
        error_log('Crear ticket: no se pudo guardar el archivo adjunto.');
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'error' => 'No se pudo guardar la imagen adjunta. Intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ===============================
// 💾 INSERTAR TICKET EN BD
// ===============================
$stmt = $conexion->prepare("
    INSERT INTO tickets
    (nombre, nombre_completo, area, tipo_ticket, prioridad, objetivo_respuesta_minutos, objetivo_resolucion_minutos, descripcion, estatus, fecha_creacion, imagen)
    VALUES (?, ?, ?, ?, ?,
        COALESCE((SELECT respuesta_minutos FROM ticket_sla_politicas WHERE prioridad = ?), 480),
        COALESCE((SELECT resolucion_minutos FROM ticket_sla_politicas WHERE prioridad = ?), 2880),
        ?, 'Pendiente', ?, ?)
");

if (!$stmt) {
    if ($nombre_imagen !== null) {
        @unlink(__DIR__ . '/../../../uploads/' . $nombre_imagen);
    }
    error_log('Crear ticket: no se pudo preparar el registro en la base de datos.');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'No se pudo registrar el ticket. Intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt->bind_param(
    "ssssssssss",
    $usuario,
    $nombre,
    $area,
    $tipo_ticket,
    $prioridad,
    $prioridad,
    $prioridad,
    $descripcion,
    $fecha_creacion,
    $nombre_imagen
);

$guardado = $stmt->execute();
if (!$guardado) {
    if ($nombre_imagen !== null) {
        @unlink(__DIR__ . '/../../../uploads/' . $nombre_imagen);
    }
    error_log('Crear ticket: falló el registro en la base de datos.');
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['status' => 'error', 'error' => 'No se pudo registrar el ticket. Intenta de nuevo.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$ticketId = $stmt->insert_id;
$escapeHtml = static fn(string $valor): string => htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$baseUrl = rtrim((string) env('APP_BASE_URL', 'https://ticket.transportesvaladez.com/'), '/') . '/';
$rutaBaseUrl = rtrim((string) parse_url($baseUrl, PHP_URL_PATH), '/');
$prefijoTicket = preg_match('~/ticket$~i', $rutaBaseUrl) ? '' : 'ticket/';
$urlImagen = $nombre_imagen !== null ? $baseUrl . $prefijoTicket . 'uploads/' . rawurlencode($nombre_imagen) : '';

// ===============================
// 📧 ENVÍO DE CORREO
// ===============================
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    require_once(__DIR__ . '/../../../config/env.php');
    $mail->Host = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_USER');
    $mail->Password = obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('noreply@mensajeriatv.site', 'Transportes Valadez');
    $destinatarioTickets = trim((string) env('MAIL_TICKETS_TO', '')) ?: 'sistemas@transportesvaladez.com';
    $mail->addAddress($destinatarioTickets);

    $mail->isHTML(true);
    $mail->Subject = "Ticket #{$ticketId} · {$tipo_ticket}";
    $urlTicket = $baseUrl . 'admin/tickets/chat_ticket.php?ticket_id=' . (int) $ticketId;
    $colorPrioridad = match (mb_strtolower(trim($prioridad), 'UTF-8')) {
        'urgente' => '#b42318',
        'alta' => '#c2410c',
        'baja' => '#475467',
        default => '#175cd3',
    };
    $descripcionCorreo = nl2br($escapeHtml($descripcion), false);
    $tipoCorreo = $escapeHtml($tipo_ticket);
    $prioridadCorreo = $escapeHtml($prioridad);
    $nombreCorreo = $escapeHtml($nombre);
    $usuarioCorreo = $escapeHtml($usuario);
    $areaCorreo = $escapeHtml($area);
    $fechaCorreo = $escapeHtml($fecha_creacion);
    $urlTicketCorreo = $escapeHtml($urlTicket);
    $bloqueAdjunto = $nombre_imagen !== null
        ? '<tr><td style="padding:0 28px 24px"><a href="' . $escapeHtml($urlImagen) . '" style="display:inline-block;padding:10px 15px;border:1px solid #d0d5dd;border-radius:8px;color:#344054;text-decoration:none;font-size:14px;font-weight:700">Ver imagen adjunta&nbsp; →</a></td></tr>'
        : '';

    $mail->Body = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f2f4f7;font-family:Arial,Helvetica,sans-serif;color:#101828">
  <div style="display:none;max-height:0;overflow:hidden;opacity:0">Nuevo ticket #{$ticketId}: {$tipoCorreo} · Prioridad {$prioridadCorreo}</div>
  <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f2f4f7;padding:32px 12px">
    <tr><td align="center">
      <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #eaecf0;border-radius:14px;overflow:hidden">
        <tr><td style="padding:22px 28px;background:#292b31;border-bottom:4px solid #ed1c24">
          <div style="font-size:11px;font-weight:700;letter-spacing:1.5px;color:#fda4a8;text-transform:uppercase">Transportes Valadez · Sistemas</div>
          <div style="padding-top:8px;color:#ffffff;font-size:23px;font-weight:700">Nuevo ticket recibido</div>
          <div style="padding-top:5px;color:#d0d5dd;font-size:14px">Folio #{$ticketId}</div>
        </td></tr>
        <tr><td style="padding:25px 28px 10px">
          <div style="font-size:12px;font-weight:700;letter-spacing:.8px;color:#667085;text-transform:uppercase">Solicitud</div>
          <div style="padding-top:8px;font-size:16px;line-height:1.5;color:#344054">{$descripcionCorreo}</div>
        </td></tr>
        <tr><td style="padding:12px 28px 24px">
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse">
            <tr><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#667085;font-size:13px;width:38%">Solicitante</td><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#344054;font-size:14px;font-weight:700">{$nombreCorreo}</td></tr>
            <tr><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#667085;font-size:13px">Usuario</td><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#344054;font-size:14px">{$usuarioCorreo}</td></tr>
            <tr><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#667085;font-size:13px">Área</td><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#344054;font-size:14px">{$areaCorreo}</td></tr>
            <tr><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#667085;font-size:13px">Tipo</td><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#344054;font-size:14px">{$tipoCorreo}</td></tr>
            <tr><td style="padding:11px 0;border-bottom:1px solid #eaecf0;color:#667085;font-size:13px">Prioridad sugerida</td><td style="padding:11px 0;border-bottom:1px solid #eaecf0"><span style="display:inline-block;padding:5px 10px;border-radius:99px;background:{$colorPrioridad};color:#ffffff;font-size:12px;font-weight:700">{$prioridadCorreo}</span></td></tr>
            <tr><td style="padding:11px 0;color:#667085;font-size:13px">Fecha de creación</td><td style="padding:11px 0;color:#344054;font-size:14px">{$fechaCorreo}</td></tr>
          </table>
        </td></tr>
        {$bloqueAdjunto}
        <tr><td align="center" style="padding:0 28px 28px"><a href="{$urlTicketCorreo}" style="display:inline-block;padding:13px 22px;border-radius:8px;background:#ed1c24;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700">Abrir y atender ticket&nbsp; →</a></td></tr>
        <tr><td style="padding:16px 28px;background:#f9fafb;border-top:1px solid #eaecf0;color:#98a2b3;font-size:11px;line-height:1.5;text-align:center">Notificación automática del sistema de tickets de Transportes Valadez.</td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    $mail->AltBody = "Nuevo ticket #{$ticketId} · {$tipo_ticket}\n\nSolicitante: {$nombre}\nUsuario: {$usuario}\nÁrea: {$area}\nPrioridad sugerida: {$prioridad}\nFecha: {$fecha_creacion}\n\nDescripción:\n{$descripcion}\n\nAbrir ticket: {$urlTicket}";

    $mail->send();
} catch (Exception $e) {
    error_log("Error correo: " . $mail->ErrorInfo);
}

// ===============================
// ✈️ ENVÍO A TELEGRAM
// ===============================
require_once(__DIR__ . '/../../../config/env.php');
$botToken = obtenerConfiguracionIntegracion('TELEGRAM_BOT_TOKEN');
$chatId   = obtenerConfiguracionIntegracion('TELEGRAM_CHAT_ID');

// Construir el mensaje con formato HTML
$mensajeTelegram = "📝 <b>NUEVO TICKET REPORTE #{$ticketId}</b>\n\n"
    . "👤 <b>Usuario:</b> {$escapeHtml($usuario)}\n"
    . "🏢 <b>Área:</b> {$escapeHtml($area)}\n"
    . "💼 <b>Nombre:</b> {$escapeHtml($nombre)}\n"
    . "📌 <b>Tipo:</b> {$escapeHtml($tipo_ticket)}\n"
    . "🚦 <b>Prioridad sugerida por IA:</b> {$escapeHtml($prioridad)}\n"
    . "📄 <b>Descripción:</b>\n" . $escapeHtml(mb_substr($descripcion, 0, 3000, 'UTF-8')) . "\n\n"
    . "📅 <b>Fecha:</b> " . $escapeHtml($fecha_creacion);

// Si subieron imagen, agregamos el enlace directo
if ($nombre_imagen) {
    $mensajeTelegram .= "\n\n📷 <a href='" . $escapeHtml($urlImagen) . "'>Ver Imagen Adjunta</a>";
}

$urlTelegram = "https://api.telegram.org/bot{$botToken}/sendMessage";

$postDataTelegram = [
    'chat_id'                  => $chatId,
    'text'                     => $mensajeTelegram,
    'parse_mode'               => 'HTML',
    'disable_web_page_preview' => false
];

$chTelegram = curl_init($urlTelegram);
curl_setopt($chTelegram, CURLOPT_POST, true);
curl_setopt($chTelegram, CURLOPT_RETURNTRANSFER, true);
curl_setopt($chTelegram, CURLOPT_POSTFIELDS, http_build_query($postDataTelegram));

$responseTelegram = curl_exec($chTelegram);

if (curl_errno($chTelegram)) {
    error_log("Error CURL Telegram: " . curl_error($chTelegram));
}

curl_close($chTelegram);

// ===============================
// 🔚 RESPUESTA AL FRONT
// ===============================
$stmt->close();
$conexion->close();

echo json_encode([
    'status'   => 'ok',
    'ticketId'=> $ticketId
]);
exit;
