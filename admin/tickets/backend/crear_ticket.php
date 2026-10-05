<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../../../backend/PHPMailer/src/Exception.php';
require '../../../backend/PHPMailer/src/PHPMailer.php';
require '../../../backend/PHPMailer/src/SMTP.php';

date_default_timezone_set('America/Mexico_City');
$fecha_creacion = date("Y-m-d H:i:s");

include('../../../backend/conexion.php');

// ===============================
// 🔹 DATOS DEL FORMULARIO
// ===============================
$usuario      = $_POST['usuario'] ?? '';
$area         = $_POST['area'] ?? '';
$nombre       = $_POST['nombre'] ?? '';
$descripcion  = $_POST['descripcion'] ?? '';
$tipo_ticket  = $_POST['tipo_ticket'] ?? '';
$nombre_imagen = null;

// DEBUG OPCIONAL
// error_log(print_r($_POST, true));
// error_log("TIPO_TICKET RECIBIDO: " . $tipo_ticket);

// ===============================
// 🖼️ SUBIR IMAGEN (SI EXISTE)
// ===============================
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
    $archivo_tmp = $_FILES['imagen']['tmp_name'];
    $nombre_archivo = basename($_FILES['imagen']['name']);
    $extension = pathinfo($nombre_archivo, PATHINFO_EXTENSION);
    $nombre_imagen = uniqid('ticket_') . '.' . $extension;
    $ruta_destino = '../../../uploads/' . $nombre_imagen;
    if (!move_uploaded_file($archivo_tmp, $ruta_destino)) {
        error_log("Error al mover archivo subido a: " . $ruta_destino);
        $nombre_imagen = null;
    }
}

// ===============================
// 💾 INSERTAR TICKET EN BD
// ===============================
$stmt = $conexion->prepare("
    INSERT INTO tickets
    (nombre, nombre_completo, area, tipo_ticket, descripcion, estatus, fecha_creacion, imagen)
    VALUES (?, ?, ?, ?, ?, 'Pendiente', ?, ?)
");

$stmt->bind_param(
    "sssssss",
    $usuario,
    $nombre,
    $area,
    $tipo_ticket,
    $descripcion,
    $fecha_creacion,
    $nombre_imagen
);

$stmt->execute();
$ticketId = $stmt->insert_id;

// ===============================
// 📧 ENVÍO DE CORREO
// ===============================
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->CharSet = 'UTF-8';
    require_once(__DIR__ . '/../../../config/env.php');
    $mail->Host = env('MAIL_MENSAJERIA_HOST');
    $mail->SMTPAuth = true;
    $mail->Username = env('MAIL_MENSAJERIA_USER');
    $mail->Password = env('MAIL_MENSAJERIA_PASS');
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;

    $mail->setFrom('noreply@mensajeriatv.site', 'Transportes Valadez');
    $mail->addAddress('sistemas@transportesvaladez.com');

    $mail->isHTML(true);
    $mail->Subject = "Nuevo Ticket - $tipo_ticket";

    $mail->Body = "
    <h3 style='background:#B72128;color:#fff;padding:15px'>📝 Nuevo Ticket</h3>
    <p><b>Usuario:</b> $usuario</p>
    <p><b>Área:</b> $area</p>
    <p><b>Nombre:</b> $nombre</p>
    <p><b>Tipo:</b> $tipo_ticket</p>
    <p><b>Descripción:</b> $descripcion</p>
    <p><b>Fecha:</b> $fecha_creacion</p>
    " . ($nombre_imagen
        ? "<p><a href='https://transportesvaladez.com/uploads/$nombre_imagen' target='_blank'>📷 Ver imagen</a></p>"
        : ""
    );

    $mail->send();
} catch (Exception $e) {
    error_log("Error correo: " . $mail->ErrorInfo);
}

// ===============================
// ✈️ ENVÍO A TELEGRAM
// ===============================
require_once(__DIR__ . '/../../../config/env.php');
$botToken = env('TELEGRAM_BOT_TOKEN');
$chatId   = env('TELEGRAM_CHAT_ID');

// Construir el mensaje con formato HTML
$mensajeTelegram = "📝 <b>NUEVO TICKET REPORTE #{$ticketId}</b>\n\n"
    . "👤 <b>Usuario:</b> $usuario\n"
    . "🏢 <b>Área:</b> $area\n"
    . "💼 <b>Nombre:</b> $nombre\n"
    . "📌 <b>Tipo:</b> $tipo_ticket\n"
    . "📄 <b>Descripción:</b>\n$descripcion\n\n"
    . "📅 <b>Fecha:</b> $fecha_creacion";

// Si subieron imagen, agregamos el enlace directo
if ($nombre_imagen) {
    $mensajeTelegram .= "\n\n📷 <a href='https://transportesvaladez.com/uploads/$nombre_imagen'>Ver Imagen Adjunta</a>";
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