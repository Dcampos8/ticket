<?php
// Tarea programada: agrega solo usuarios que aún no existen en MySQL.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/conexion.php';
require_once dirname(__DIR__) . '/shared/sqlserver_user_sync.php';

try {
    $stats = sincronizarUsuariosSqlServer($conexion, 'insert_only');
    echo sprintf(
        "Sincronización completada. Agregados: %d; omitidos (existentes o inactivos): %d; sin contraseña utilizable: %d.%s",
        $stats['insertados'],
        $stats['omitidos'],
        $stats['sin_contrasena'],
        PHP_EOL
    );
} catch (Throwable $e) {
    error_log('Sincronización periódica de usuarios SQL Server fallida (' . get_class($e) . ').');
    fwrite(STDERR, 'No se completó la sincronización. Revisa la conexión y los registros PHP.' . PHP_EOL);
    exit(1);
}
