<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderApi(int $status, array $respuesta): void
{
    http_response_code($status);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderApi(405, ['status' => 'error', 'error' => 'Método no permitido.']);
}

if (empty($_SERVER['HTTPS']) || strtolower((string) $_SERVER['HTTPS']) === 'off') {
    responderApi(400, ['status' => 'error', 'error' => 'Este endpoint requiere HTTPS.']);
}

$contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($contentLength < 1 || $contentLength > 20000) {
    responderApi(413, ['status' => 'error', 'error' => 'El tamaño de la solicitud no es válido.']);
}

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../shared/integration_settings.php';
$secret = trim((string) obtenerConfiguracionIntegracion('INTRANET_TICKET_API_SECRET', ''));
if (strlen($secret) < 32) {
    error_log('API intranet tickets: falta configurar INTRANET_TICKET_API_SECRET (mínimo 32 caracteres).');
    responderApi(503, ['status' => 'error', 'error' => 'La integración con la intranet no está configurada en el servidor.']);
}

$timestamp = trim((string) ($_SERVER['HTTP_X_TICKET_TIMESTAMP'] ?? ''));
$requestId = trim((string) ($_SERVER['HTTP_X_TICKET_REQUEST_ID'] ?? ''));
$signature = trim((string) ($_SERVER['HTTP_X_TICKET_SIGNATURE'] ?? ''));
if (!ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 60
    || !preg_match('/^[a-f0-9]{32}$/i', $requestId)
    || !preg_match('/^[a-f0-9]{64}$/i', $signature)) {
    responderApi(401, ['status' => 'error', 'error' => 'La firma de la solicitud no es válida o expiró.']);
}

$rawBody = file_get_contents('php://input');
if (!is_string($rawBody) || $rawBody === '') {
    responderApi(400, ['status' => 'error', 'error' => 'La solicitud está vacía.']);
}

$expectedSignature = hash_hmac('sha256', $timestamp . "\n" . $requestId . "\n" . $rawBody, $secret);
if (!hash_equals($expectedSignature, $signature)) {
    responderApi(401, ['status' => 'error', 'error' => 'La firma de la solicitud no es válida o expiró.']);
}

// Reserva atómica del identificador para impedir que una solicitud firmada se reutilice.
$directorioRepeticiones = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'ticket-api-replay-' . substr(hash('sha256', __DIR__), 0, 16);
if (!is_dir($directorioRepeticiones) && !@mkdir($directorioRepeticiones, 0700, true) && !is_dir($directorioRepeticiones)) {
    error_log('API intranet tickets: no se pudo crear el directorio temporal de protección contra repetición.');
    responderApi(503, ['status' => 'error', 'error' => 'La integración no está disponible temporalmente.']);
}
if (random_int(1, 100) === 1) {
    foreach (glob($directorioRepeticiones . DIRECTORY_SEPARATOR . '*.request') ?: [] as $archivoSolicitud) {
        if (is_file($archivoSolicitud) && filemtime($archivoSolicitud) < time() - 300) {
            @unlink($archivoSolicitud);
        }
    }
}
$archivoRequest = $directorioRepeticiones . DIRECTORY_SEPARATOR . hash('sha256', $requestId) . '.request';
$reserva = @fopen($archivoRequest, 'x');
if ($reserva === false) {
    responderApi(409, ['status' => 'error', 'error' => 'Esta solicitud ya fue recibida.']);
}
fwrite($reserva, $timestamp);
fclose($reserva);

$datos = json_decode($rawBody, true);
if (!is_array($datos) || json_last_error() !== JSON_ERROR_NONE) {
    responderApi(400, ['status' => 'error', 'error' => 'El cuerpo debe ser JSON válido.']);
}

$usuario = trim((string) ($datos['usuario'] ?? ''));
$nombre = trim((string) ($datos['nombre'] ?? ''));
$area = trim((string) ($datos['area'] ?? ''));
$descripcion = trim((string) ($datos['descripcion'] ?? ''));
if ($usuario === '' || $nombre === '' || $area === '' || $descripcion === '') {
    responderApi(422, ['status' => 'error', 'error' => 'Faltan usuario, nombre, área o descripción.']);
}
if (mb_strlen($usuario, 'UTF-8') > 100 || mb_strlen($nombre, 'UTF-8') > 150 || mb_strlen($area, 'UTF-8') > 150 || mb_strlen($descripcion, 'UTF-8') > 10000) {
    responderApi(422, ['status' => 'error', 'error' => 'Uno o más campos exceden el tamaño permitido.']);
}

// El backend existente realiza la clasificación con IA, guarda el ticket y envía las notificaciones.
$_POST = [
    'usuario' => $usuario,
    'nombre' => $nombre,
    'area' => $area,
    'descripcion' => $descripcion,
];
$_FILES = [];
define('TICKET_CREATION_TRUSTED_API', true);

chdir(__DIR__ . '/../admin/tickets/backend');
require __DIR__ . '/../admin/tickets/backend/crear_ticket.php';
