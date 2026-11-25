<?php
require_once __DIR__ . '/config.php';
$nombreMarca = nombreEmpresa();
$iconoMarca = iconoPestanaEmpresaUrl();
$extensionIcono = strtolower(pathinfo(parse_url($iconoMarca, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
$tipoIcono = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'][$extensionIcono] ?? 'image/png';
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode([
    'id' => BASE_URL,
    'name' => 'Sistemas · ' . $nombreMarca,
    'short_name' => mb_strimwidth($nombreMarca, 0, 18, '', 'UTF-8'),
    'description' => 'Sistemas internos de ' . $nombreMarca . '.',
    'start_url' => BASE_URL,
    'scope' => BASE_URL,
    'display' => 'standalone',
    'background_color' => '#f6f7f9',
    'theme_color' => '#292b31',
    'icons' => [[
        'src' => $iconoMarca,
        'sizes' => 'any',
        'type' => $tipoIcono,
        'purpose' => 'any',
    ]],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
