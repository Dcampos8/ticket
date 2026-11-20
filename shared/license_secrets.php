<?php
require_once __DIR__ . '/integration_settings.php';

function cifrarClaveLicencia(string $plain): ?string
{
    if ($plain === '') return '';
    $key = claveCifradoIntegraciones();
    if (!$key || !function_exists('openssl_encrypt')) return null;
    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
    return $cipher === false ? null : 'enc:v1:' . base64_encode($nonce . $tag . $cipher);
}

function descifrarClaveLicencia(?string $stored): string
{
    $stored = (string) $stored;
    if (!str_starts_with($stored, 'enc:v1:')) return $stored; // Compatibilidad temporal con claves anteriores.
    $key = claveCifradoIntegraciones();
    $raw = base64_decode(substr($stored, 7), true);
    if (!$key || $raw === false || strlen($raw) < 29) return '';
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}

function claveLicenciaEstaCifrada(?string $stored): bool
{
    return str_starts_with((string) $stored, 'enc:v1:');
}

function mascaraClaveLicencia(?string $stored): string
{
    if ((string) $stored === '') return 'Sin clave';
    if (claveLicenciaEstaCifrada($stored) && claveCifradoIntegraciones() === null) return 'Clave cifrada (configura la llave)';
    $plain = descifrarClaveLicencia($stored);
    if ($plain === '') return 'Clave no disponible';
    $suffix = mb_substr($plain, -4, null, 'UTF-8');
    return '•••• •••• •••• ' . $suffix;
}
