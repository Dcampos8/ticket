<?php
declare(strict_types=1);

// Ejecutar exclusivamente desde cron/CLI. Envía los tickets de los 7 días completos anteriores.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../shared/integration_settings.php';
require_once __DIR__ . '/../../backend/conexion.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$destinatarios = trim((string) obtenerConfiguracionIntegracion('REPORTES_DIARIOS_TO', ''));
if ($destinatarios === '') {
    fwrite(STDERR, "Resumen diario omitido: configura REPORTES_DIARIOS_TO en Conexiones e integraciones.\n");
    exit(2);
}
$correos = array_values(array_filter(array_map('trim', explode(',', $destinatarios)), static fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
if (!$correos) {
    fwrite(STDERR, "Resumen diario omitido: no hay destinatarios válidos.\n");
    exit(2);
}
$desde = new DateTimeImmutable('today -7 days');
$hasta = new DateTimeImmutable('today');
$desdeSql = $desde->format('Y-m-d 00:00:00');
$hastaSql = $hasta->format('Y-m-d 00:00:00');

global $conexion;
$stmt = $conexion->prepare("SELECT id, nombre, area, descripcion, estatus, fecha_creacion
    FROM tickets WHERE fecha_creacion >= ? AND fecha_creacion < ? ORDER BY fecha_creacion DESC");
if (!$stmt) {
    fwrite(STDERR, "No se pudo preparar la consulta del resumen diario.\n");
    exit(1);
}
$stmt->bind_param('ss', $desdeSql, $hastaSql);
$stmt->execute();
$tickets = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$filas = '';
foreach ($tickets as $ticket) {
    $filas .= '<tr><td>' . (int) $ticket['id'] . '</td><td>' . $escape($ticket['fecha_creacion']) . '</td><td>' . $escape($ticket['nombre']) . '</td><td>' . $escape($ticket['area']) . '</td><td>' . $escape($ticket['estatus']) . '</td><td>' . nl2br($escape($ticket['descripcion'])) . '</td></tr>';
}
if ($filas === '') $filas = '<tr><td colspan="6">No se registraron tickets en este periodo.</td></tr>';
$html = '<!doctype html><html lang="es"><meta charset="utf-8"><body style="font-family:Arial,sans-serif;color:#263244">'
    . '<h2>Resumen semanal de tickets</h2><p>Tickets creados del ' . $desde->format('d/m/Y') . ' al ' . $hasta->modify('-1 day')->format('d/m/Y') . ' (' . count($tickets) . ' en total).</p>'
    . '<table style="border-collapse:collapse;width:100%"><thead><tr style="background:#edf2f7"><th style="padding:8px;border:1px solid #ccd5e0">ID</th><th style="padding:8px;border:1px solid #ccd5e0">Creación</th><th style="padding:8px;border:1px solid #ccd5e0">Usuario</th><th style="padding:8px;border:1px solid #ccd5e0">Área</th><th style="padding:8px;border:1px solid #ccd5e0">Estatus</th><th style="padding:8px;border:1px solid #ccd5e0">Descripción</th></tr></thead><tbody>' . $filas . '</tbody></table></body></html>';

$smtpHost = trim((string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_HOST', 'smtp.hostinger.com')) ?: 'smtp.hostinger.com';
$smtpUser = trim((string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_USER', ''));
$smtpPass = (string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_PASS', '');
if ($smtpUser === '' || $smtpPass === '') {
    fwrite(STDERR, "Resumen diario omitido: configura usuario y contraseña SMTP de mensajería.\n");
    exit(2);
}

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $smtpHost;
    $mail->SMTPAuth = true;
    $mail->Username = $smtpUser;
    $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = 465;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtpUser, 'Sistema de Tickets');
    foreach ($correos as $correo) $mail->addAddress($correo);
    $mail->isHTML(true);
    $mail->Subject = 'Resumen semanal de tickets (' . $desde->format('d/m/Y') . ' al ' . $hasta->modify('-1 day')->format('d/m/Y') . ')';
    $mail->Body = $html;
    $mail->AltBody = 'Resumen semanal: ' . count($tickets) . ' tickets creados del ' . $desde->format('d/m/Y') . ' al ' . $hasta->modify('-1 day')->format('d/m/Y') . '.';
    $mail->send();
    fwrite(STDOUT, 'Resumen diario enviado a ' . count($correos) . ' destinatario(s); tickets: ' . count($tickets) . ".\n");
} catch (Throwable $e) {
    error_log('Error al enviar resumen diario de tickets: ' . $e->getMessage());
    fwrite(STDERR, "Falló el envío del resumen diario. Revisa los registros del servidor.\n");
    exit(1);
}
