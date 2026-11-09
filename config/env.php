<?php
/**
 * Carga variables de entorno desde el archivo .env en la raíz del proyecto.
 * No depende de Composer/librerías externas.
 * Uso: require_once __DIR__ . '/env.php'; luego env('DB_HOST');
 */

if (!function_exists('env')) {
    function env(string $key, $default = null) {
        static $loaded = false;

        if (!$loaded) {
            $envPath = dirname(__DIR__) . '/.env';
            if (file_exists($envPath)) {
                foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }
                    if (strpos($line, '=') === false) {
                        continue;
                    }
                    [$name, $value] = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value);
                    // quitar comillas envolventes si existen
                    if (strlen($value) >= 2 && (
                        ($value[0] === '"' && $value[-1] === '"') ||
                        ($value[0] === "'" && $value[-1] === "'")
                    )) {
                        $value = substr($value, 1, -1);
                    }
                    if (!array_key_exists($name, $_ENV)) {
                        $_ENV[$name] = $value;
                        putenv("$name=$value");
                    }
                }
            }
            $loaded = true;
        }

        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
