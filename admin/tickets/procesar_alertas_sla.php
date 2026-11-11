<?php
declare(strict_types=1);

// Ejecutar desde cron/CLI. Envía un resumen de SLA vencidos y próximos a vencer, una vez por ticket y tipo al día.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
date_default_timezone_set('America/Mexico_City');
require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/../../shared/integration_settings.php';
require_once __DIR__ . '/../../backend/conexion.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../../backend/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;

$destinatarios = array_values(array_filter(array_map('trim', explode(',', (string) obtenerConfiguracionIntegracion('SLA_ALERTAS_TO', ''))), static fn($email) => filter_var($email, FILTER_VALIDATE_EMAIL)));
if (!$destinatarios) { fwrite(STDOUT, "Alertas SLA omitidas: configura SLA_ALERTAS_TO en Conexiones e integraciones.\n"); exit(0); }
$smtpHost = trim((string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_HOST', 'smtp.hostinger.com')) ?: 'smtp.hostinger.com';
$smtpUser = trim((string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_USER', ''));
$smtpPass = (string) obtenerConfiguracionIntegracion('MAIL_MENSAJERIA_PASS', '');
if ($smtpUser === '' || $smtpPass === '') { fwrite(STDERR, "Configura el SMTP de mensajería antes de procesar alertas SLA.\n"); exit(2); }

$hoy = date('Y-m-d');
$ahora = new DateTimeImmutable('now');
$abiertos = $conexion->query("SELECT id, nombre_completo, nombre, area, tipo_ticket, prioridad, asignado_a, descripcion, fecha_creacion, fecha_primera_respuesta, objetivo_respuesta_minutos, objetivo_resolucion_minutos FROM tickets WHERE estatus IN ('Pendiente','En proceso') ORDER BY fecha_creacion ASC");
if (!$abiertos) { fwrite(STDERR, "No se pudieron consultar tickets activos.\n"); exit(1); }
$stmtAvisos = $conexion->prepare('SELECT 1 FROM ticket_alertas_sla_enviadas WHERE ticket_id = ? AND tipo_alerta = ? AND fecha_alerta = ? LIMIT 1');
if (!$stmtAvisos) { fwrite(STDERR, "Aplica la migración 20261110_alertas_sla.sql antes de activar las alertas.\n"); exit(2); }
$pendientes = [];
while ($ticket = $abiertos->fetch_assoc()) {
    $creado = new DateTimeImmutable((string) $ticket['fecha_creacion']);
    $tipos = [];
    if (empty($ticket['fecha_primera_respuesta'])) {
        $limite = $creado->modify('+' . (int) $ticket['objetivo_respuesta_minutos'] . ' minutes');
        if ($limite <= $ahora) $tipos[] = ['respuesta_vencida', 'Primera respuesta vencida', $limite];
        elseif ($limite <= $ahora->modify('+60 minutes')) $tipos[] = ['respuesta_proxima', 'Primera respuesta vence pronto', $limite];
    }
    $limiteResolucion = $creado->modify('+' . (int) $ticket['objetivo_resolucion_minutos'] . ' minutes');
    if ($limiteResolucion <= $ahora) $tipos[] = ['resolucion_vencida', 'Resolución vencida', $limiteResolucion];
    elseif ($limiteResolucion <= $ahora->modify('+240 minutes')) $tipos[] = ['resolucion_proxima', 'Resolución vence en menos de 4 horas', $limiteResolucion];
    foreach ($tipos as [$tipo, $etiqueta, $limite]) {
        $id = (int) $ticket['id'];
        $stmtAvisos->bind_param('iss', $id, $tipo, $hoy);
        $stmtAvisos->execute();
        if (!$stmtAvisos->get_result()->fetch_row()) $pendientes[] = ['ticket' => $ticket, 'tipo' => $tipo, 'etiqueta' => $etiqueta, 'limite' => $limite];
    }
}
$stmtAvisos->close();
if (!$pendientes) { fwrite(STDOUT, "No hay alertas SLA nuevas.\n"); exit(0); }

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$rows = '';
foreach ($pendientes as $item) {
    $t = $item['ticket'];
    $rows .= '<tr><td>#' . (int) $t['id'] . '</td><td>' . $escape($item['etiqueta']) . '</td><td>' . $escape($t['prioridad']) . '</td><td>' . $escape($t['nombre_completo'] ?: $t['nombre']) . '</td><td>' . $escape($t['asignado_a'] ?: 'Sin asignar') . '</td><td>' . $escape($item['limite']->format('d/m/Y H:i')) . '</td><td>' . $escape(mb_strimwidth((string) $t['descripcion'], 0, 180, '…', 'UTF-8')) . '</td></tr>';
}
$html = '<!doctype html><html lang="es"><meta charset="utf-8"><body style="font-family:Arial,sans-serif;color:#292a2e"><h2 style="color:#b42318">Alertas de SLA · Service Desk</h2><p>Tickets que requieren seguimiento (alertas nuevas del ' . $escape(date('d/m/Y H:i')) . ').</p><table style="border-collapse:collapse;width:100%"><thead><tr style="background:#f2f4f7">';
foreach (['Ticket', 'Alerta', 'Prioridad', 'Solicitante', 'Responsable', 'Fecha objetivo', 'Descripción'] as $heading) $html .= '<th style="padding:8px;border:1px solid #d0d5dd;text-align:left">' . $heading . '</th>';
$html .= '</tr></thead><tbody>' . $rows . '</tbody></table><p>Una misma alerta no se repetirá para el ticket durante el día.</p></body></html>';

try {
    $mail = new PHPMailer(true);
    $mail->isSMTP(); $mail->Host = $smtpHost; $mail->SMTPAuth = true; $mail->Username = $smtpUser; $mail->Password = $smtpPass;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; $mail->Port = 465; $mail->CharSet = 'UTF-8';
    $mail->setFrom($smtpUser, 'Service Desk · Alertas SLA');
    foreach ($destinatarios as $correo) $mail->addAddress($correo);
    $mail->isHTML(true); $mail->Subject = 'Service Desk: ' . count($pendientes) . ' alertas SLA requieren atención'; $mail->Body = $html;
    $mail->AltBody = 'Hay ' . count($pendientes) . ' alertas SLA nuevas en tickets abiertos.';
    $mail->send();
    $stmtLog = $conexion->prepare('INSERT IGNORE INTO ticket_alertas_sla_enviadas (ticket_id,tipo_alerta,fecha_alerta) VALUES (?,?,?)');
    foreach ($pendientes as $item) {
        $id = (int) $item['ticket']['id']; $tipo = (string) $item['tipo'];
        $stmtLog->bind_param('iss', $id, $tipo, $hoy); $stmtLog->execute();
    }
    $stmtLog->close();
    fwrite(STDOUT, 'Alertas SLA enviadas: ' . count($pendientes) . "\n");
} catch (Throwable $e) {
    error_log('Falló el envío de alertas SLA: ' . $e->getMessage());
    fwrite(STDERR, "No se pudieron enviar alertas SLA; revisa los registros del servidor.\n"); exit(1);
}
