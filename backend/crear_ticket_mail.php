<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// 🔹 CONEXIÓN BD
require('conexion.php');

$conexion->set_charset("utf8mb4"); // 🔥 ESTA LÍNEA ARREGLA TODO
$conexion->query("SET NAMES utf8mb4"); // 🔥 BLINDA TODO
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/plain');

echo "=== DEBUG POST ===\n";
var_dump($_POST);

echo "\n=== DEBUG FILES ===\n";
var_dump($_FILES);

exit;

// 🔹 DATOS DEL FORMULARIO
$nombre = $_POST['nombre'] ?? '';
$area = $_POST['area'] ?? '';
$puesto = $_POST['puesto'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$tipo_ticket = $_POST['tipo_ticket'] ?? '';

error_log(print_r($_POST, true));

// 🔹 INSERTAR EN BASE DE DATOS
$stmt = $conexion->prepare("
INSERT INTO tickets (nombre,area,descripcion,tipo_ticket,estatus,fecha_creacion)
VALUES (?, ?, ?, ?, 'Pendiente', NOW())
");

$stmt->bind_param(
    "ssss",
    $nombre,
    $area,
    $descripcion,
    $tipo_ticket
);

$stmt->execute();
$stmt->close();
$conexion->close();

error_log("TIPO REAL DEL TICKET: " . $tipo_ticket);

// =========================
// 📧 ENVÍO DE CORREO
// =========================
$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host = 'smtp.transportesvaladez.com';
    $mail->SMTPAuth = true;
    require_once(__DIR__ . '/../config/env.php');
    $mail->Username = env('MAIL_SISTEMAS_USER');
    $mail->Password = env('MAIL_SISTEMAS_PASS');
    $mail->SMTPSecure = 'ssl';
    $mail->Port = 465;

    $mail->setFrom('sistemas@transportesvaladez.com', 'Sistema de Tickets');
    $mail->addAddress('sistemas@transportesvaladez.com');

    $mail->isHTML(true);
    $mail->Subject = "Nuevo Ticket - $tipo_ticket";

    $mail->Body = "
    <b>Nuevo ticket registrado:</b><br><br>
    <b>Nombre:</b> {$nombre}<br>
    <b>Área:</b> {$area}<br>
    <b>Puesto:</b> {$puesto}<br>
    <b>Tipo:</b> {$tipo_ticket}<br>
    <b>Descripción:</b> {$descripcion}
    ";

    $mail->send();
} catch (Exception $e) {
    error_log("Error al enviar correo: {$mail->ErrorInfo}");
}

// =========================
// 💬 ENVÍO DE WHATSAPP (Twilio con cURL)
// =========================
$sid = 'ACc0d67a6865aff4a6a462c7a9ff73e876';   // Tu SID real
$token = '1a2ff4aa595def94426b3777e8403ed9';  // Tu Auth Token real

$mensaje = "📢 Nuevo Ticket - $tipo_ticket:\n"
    ."👤 Nombre: $nombre\n"
    ."🏢 Área: $area\n"
    ."💼 Puesto: $puesto\n"
    ."📝 Descripción: $descripcion";

$url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";

$data = [
    'From' => 'whatsapp:+14155238886', // número sandbox Twilio
    'To' => 'whatsapp:+5214491565186', // tu número verificado en sandbox
    'Body' => $mensaje
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$sid:$token");
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

$response = curl_exec($ch);
if (curl_errno($ch)) {
    error_log("Error cURL Twilio: " . curl_error($ch));
} else {
    error_log("WhatsApp enviado correctamente: " . $response);
}
curl_close($ch);
file_put_contents('twilio_log.txt', $response);

?>
