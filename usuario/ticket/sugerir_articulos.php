<?php
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
header('Content-Type: application/json; charset=utf-8');
if (empty($_SESSION['logueado']) || ($_SESSION['rol'] ?? '') !== 'usuario') { http_response_code(403); echo json_encode(['articulos' => []]); exit; }
require_once ROOT_PATH . 'backend/conexion.php';
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q, 'UTF-8') > 120) $q = mb_substr($q, 0, 120, 'UTF-8');
if (mb_strlen($q, 'UTF-8') < 4) { echo json_encode(['articulos' => []], JSON_UNESCAPED_UNICODE); exit; }
$like = '%' . $q . '%';
$stmt = $conexion->prepare('SELECT titulo, resumen, categoria FROM base_conocimiento WHERE publicado=1 AND (titulo LIKE ? OR resumen LIKE ? OR contenido LIKE ? OR palabras_clave LIKE ?) ORDER BY CASE WHEN titulo LIKE ? THEN 0 ELSE 1 END, actualizado_en DESC LIMIT 4');
if (!$stmt) { http_response_code(503); echo json_encode(['articulos' => []]); exit; }
$stmt->bind_param('sssss', $like, $like, $like, $like, $like);
$stmt->execute();
$articulos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
echo json_encode(['articulos' => $articulos], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
