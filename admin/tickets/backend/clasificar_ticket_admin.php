<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../backend/conexion.php';
require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/security.php';
require_once __DIR__ . '/../../../shared/ticket_clasificacion.php';

function responderClasificacionAdmin(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderClasificacionAdmin(405, ['status' => 'error', 'message' => 'Método no permitido.']);
}
if (empty($_SESSION['logueado']) || !esAdminOSuperior() || !tieneModulo('tickets')) {
    responderClasificacionAdmin(403, ['status' => 'error', 'message' => 'No autorizado.']);
}
if (!validarTokenCsrf()) {
    responderClasificacionAdmin(403, ['status' => 'error', 'message' => 'La sesión expiró. Recarga la página e intenta de nuevo.']);
}

$descripcion = trim((string) ($_POST['descripcion'] ?? ''));
if (mb_strlen($descripcion, 'UTF-8') < 12 || mb_strlen($descripcion, 'UTF-8') > 10000) {
    responderClasificacionAdmin(422, ['status' => 'error', 'message' => 'Escribe una descripción más detallada para sugerir el tipo de ticket.']);
}

$clasificacion = clasificarSolicitud($descripcion);
if ($clasificacion === null) {
    responderClasificacionAdmin(503, [
        'status' => 'error',
        'message' => $GLOBALS['gemini_classification_issue'] ?? 'No se pudo sugerir el tipo de ticket en este momento.',
    ]);
}

responderClasificacionAdmin(200, ['status' => 'ok', 'tipo_ticket' => $clasificacion['tipo_ticket']]);
