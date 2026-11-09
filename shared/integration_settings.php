<?php
require_once __DIR__ . '/../config/env.php';

function claveCifradoIntegraciones(): ?string
{
    $secreto = trim((string) env('APP_SETTINGS_ENCRYPTION_KEY', ''));
    return strlen($secreto) >= 32 ? hash('sha256', $secreto, true) : null;
}

function obtenerConfiguracionIntegracion(string $clave, $predeterminado = '')
{
    static $cache = [];
    if (array_key_exists($clave, $cache)) return $cache[$clave];
    try {
        require_once __DIR__ . '/../backend/conexion.php';
        global $conexion;
        $stmt = $conexion->prepare('SELECT valor, es_secreto FROM integracion_configuracion WHERE clave = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $clave);
            $stmt->execute();
            $fila = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($fila) {
                $valor = (string) $fila['valor'];
                if ((int) $fila['es_secreto'] === 1) {
                    $key = claveCifradoIntegraciones();
                    $raw = base64_decode($valor, true);
                    if ($key && $raw !== false && strlen($raw) > 28) {
                        $descifrado = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
                        if ($descifrado !== false) return $cache[$clave] = $descifrado;
                    }
                    return $cache[$clave] = (string) env($clave, $predeterminado);
                }
                return $cache[$clave] = $valor;
            }
        }
    } catch (Throwable $e) {
        // Permite que instalaciones sin la migración sigan usando .env.
    }
    return $cache[$clave] = (string) env($clave, $predeterminado);
}

function guardarConfiguracionIntegracion(string $clave, string $valor, bool $secreto, string $actualizadoPor): bool
{
    if ($secreto) {
        $key = claveCifradoIntegraciones();
        if (!$key || !function_exists('openssl_encrypt')) return false;
        $nonce = random_bytes(12);
        $tag = '';
        $cifrado = openssl_encrypt($valor, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cifrado === false) return false;
        $valor = base64_encode($nonce . $tag . $cifrado);
    }
    try {
        require_once __DIR__ . '/../backend/conexion.php';
        global $conexion;
        $stmt = $conexion->prepare('INSERT INTO integracion_configuracion (clave, valor, es_secreto, actualizado_por) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor), es_secreto = VALUES(es_secreto), actualizado_por = VALUES(actualizado_por)');
        if (!$stmt) return false;
        $flag = $secreto ? 1 : 0;
        $stmt->bind_param('ssis', $clave, $valor, $flag, $actualizadoPor);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    } catch (Throwable $e) {
        return false;
    }
}
