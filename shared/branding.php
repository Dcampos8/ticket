<?php
declare(strict_types=1);

require_once __DIR__ . '/integration_settings.php';

function nombreEmpresa(): string
{
    $nombre = trim((string) obtenerConfiguracionIntegracion('MARCA_NOMBRE_EMPRESA', 'Transportes Valadez'));
    return $nombre !== '' ? $nombre : 'Transportes Valadez';
}

function nombreEmpresaHtml(): string
{
    return htmlspecialchars(nombreEmpresa(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tituloPaginaEmpresa(string $titulo = ''): string
{
    $titulo = trim($titulo);
    return htmlspecialchars(($titulo !== '' ? $titulo . ' · ' : '') . nombreEmpresa(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function recursoMarcaEmpresa(string $clave, string $predeterminado): string
{
    $ruta = trim(str_replace('\\', '/', (string) obtenerConfiguracionIntegracion($clave, $predeterminado)));
    if (!preg_match('#^uploads/branding/[a-zA-Z0-9_-]+\.(?:png|jpe?g|webp)$#i', $ruta)) {
        $ruta = $predeterminado;
    }
    $archivo = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $ruta);
    if (strpos($ruta, 'uploads/branding/') === 0 && !is_file($archivo)) {
        $ruta = $predeterminado;
    }
    $baseUrl = defined('BASE_URL') ? BASE_URL : '/';
    return rtrim($baseUrl, '/') . '/' . implode('/', array_map('rawurlencode', explode('/', $ruta)));
}

function logoEmpresaUrl(): string
{
    return recursoMarcaEmpresa('MARCA_LOGO', 'img/Camion Cont Der.png');
}

function iconoPestanaEmpresaUrl(): string
{
    return recursoMarcaEmpresa('MARCA_ICONO', 'img/favicon.svg');
}
