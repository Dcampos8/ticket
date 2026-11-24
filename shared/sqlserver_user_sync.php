<?php
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/passwords.php';

/** Conecta a la base de usuarios de SQL Server sin exponer los errores al usuario. */
function conectarSqlServerUsuarios(): PDO
{
    if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
        throw new RuntimeException('El servidor PHP no tiene habilitado PDO_SQLSRV.');
    }

    $host = trim((string) env('DB2_HOST', ''));
    $database = trim((string) env('DB2_NAME', ''));
    $port = trim((string) env('DB2_PORT', '1433'));
    $username = (string) env('DB2_USER', '');
    $password = (string) env('DB2_PASS', '');
    if ($host === '' || $database === '' || $username === '') {
        throw new RuntimeException('Falta configurar DB2_HOST, DB2_NAME o DB2_USER en .env.');
    }

    $server = $host;
    if ($port !== '' && ctype_digit($port) && strpos($host, ',') === false) {
        $server .= ',' . $port;
    }
    $dsn = 'sqlsrv:Server=' . $server . ';Database=' . $database . ';Encrypt=1;TrustServerCertificate=1';
    return new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/**
 * Importa Usuarios de SQL Server a MySQL.
 * full actualiza las columnas sincronizadas de cuentas existentes; insert_only las omite.
 */
function sincronizarUsuariosSqlServer(mysqli $mysql, string $mode): array
{
    if (!in_array($mode, ['full', 'insert_only'], true)) {
        throw new InvalidArgumentException('Modo de sincronización no válido.');
    }

    $sqlsrv = conectarSqlServerUsuarios();
    $source = $sqlsrv->query('SELECT id_usuario, nombre, elpassword, status, email FROM dbo.Usuarios');
    $existing = $mysql->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
    $upsert = $mysql->prepare(
        'INSERT INTO usuarios (usuario, contrasena, nombre_completo, email, area, rol, activo)
         VALUES (?, ?, ?, ?, ?, \'usuario\', ?)
         ON DUPLICATE KEY UPDATE contrasena = VALUES(contrasena), nombre_completo = VALUES(nombre_completo),
             email = VALUES(email), activo = VALUES(activo)'
    );
    $insert = $mysql->prepare(
        'INSERT INTO usuarios (usuario, contrasena, nombre_completo, email, area, rol, activo)
         VALUES (?, ?, ?, ?, ?, \'usuario\', ?)'
    );
    $updateWithoutPassword = $mysql->prepare(
        'UPDATE usuarios SET nombre_completo = ?, email = ?, activo = ? WHERE usuario = ?'
    );
    if (!$existing || !$upsert || !$insert || !$updateWithoutPassword) {
        throw new RuntimeException('No se pudieron preparar las consultas de sincronización en MySQL.');
    }

    $stats = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0, 'sin_contrasena' => 0];
    $mysql->begin_transaction();
    try {
        while ($row = $source->fetch()) {
            $user = trim((string) ($row['id_usuario'] ?? ''));
            $plainPassword = (string) ($row['elpassword'] ?? '');
            if ($user === '') {
                $stats['sin_contrasena']++;
                continue;
            }

            $existing->bind_param('s', $user);
            $existing->execute();
            $found = $existing->get_result()->fetch_assoc();
            $active = strtoupper(trim((string) ($row['status'] ?? ''))) === 'A' ? 1 : 0;
            if ($mode === 'insert_only' && ($found || !$active)) {
                $stats['omitidos']++;
                continue;
            }

            if ($plainPassword === '') {
                $stats['sin_contrasena']++;
                if ($mode === 'full' && $found) {
                    $name = trim((string) ($row['nombre'] ?? ''));
                    $email = trim((string) ($row['email'] ?? ''));
                    $email = $email === '' ? null : $email;
                    $updateWithoutPassword->bind_param('ssis', $name, $email, $active, $user);
                    if (!$updateWithoutPassword->execute()) {
                        throw new RuntimeException('Falló una escritura de sincronización en MySQL.');
                    }
                    $stats['actualizados']++;
                }
                continue;
            }

            $password = normalizarContrasena($plainPassword);
            $hash = password_hash($password, PASSWORD_DEFAULT);
            if (!is_string($hash)) {
                throw new RuntimeException('No se pudo generar un hash para una cuenta de origen.');
            }
            $name = trim((string) ($row['nombre'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $email = $email === '' ? null : $email;
            $area = 'Sin asignar';
            if ($mode === 'full') {
                $upsert->bind_param('sssssi', $user, $hash, $name, $email, $area, $active);
                if (!$upsert->execute()) {
                    throw new RuntimeException('Falló una escritura de sincronización en MySQL.');
                }
                if ($found) $stats['actualizados']++;
                else $stats['insertados']++;
            } else {
                $insert->bind_param('sssssi', $user, $hash, $name, $email, $area, $active);
                if (!$insert->execute()) {
                    throw new RuntimeException('Falló una escritura de sincronización en MySQL.');
                }
                $stats['insertados']++;
            }
        }
        $mysql->commit();
    } catch (Throwable $e) {
        $mysql->rollback();
        throw $e;
    } finally {
        $existing->close();
        $upsert->close();
        $insert->close();
        $updateWithoutPassword->close();
        $sqlsrv = null;
    }
    return $stats;
}
