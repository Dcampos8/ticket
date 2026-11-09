<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
require_once ROOT_PATH . 'shared/passwords.php';
requerirAdmin();
if (!esSuperAdmin() && !tieneModulo('usuarios')) {
    http_response_code(403);
    exit('No tienes permiso para importar usuarios.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !validarTokenCsrf()) {
    http_response_code(403);
    exit('La sesión expiró o la solicitud no es válida. Regresa al formulario e inténtalo de nuevo.');
}

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$agregados = 0;
$actualizados = [];
$duplicados = [];
$errores = [];
$archivo = $_FILES['archivoUsuarios'] ?? null;

if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
    $errores[] = 'No se recibió un archivo CSV válido.';
} elseif (($handle = fopen($archivo['tmp_name'], 'rb')) === false) {
    $errores[] = 'No se pudo abrir el archivo CSV.';
} else {
    $encabezados = fgetcsv($handle, 0, ',', '"', '');
    if (!$encabezados) {
        $errores[] = 'El CSV está vacío o no contiene encabezados.';
    } else {
        $encabezados[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $encabezados[0]);
        $mapa = [];
        foreach ($encabezados as $indice => $nombre) {
            $nombre = strtolower(trim((string) $nombre));
            $nombre = strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);
            $nombre = str_replace([' ', '-'], '_', $nombre);
            $alias = ['nombre' => 'nombre_completo', 'contrasena' => 'contrasena', 'password' => 'contrasena', 'telefono' => 'phone', 'area' => 'area'];
            $mapa[$alias[$nombre] ?? $nombre] = $indice;
        }
        $requeridos = ['usuario', 'nombre_completo', 'contrasena', 'email', 'phone', 'area', 'rol'];
        $faltantes = array_values(array_diff($requeridos, array_keys($mapa)));
        if ($faltantes) {
            $errores[] = 'Faltan columnas requeridas: ' . implode(', ', $faltantes) . '.';
        } else {
            $stmtExiste = $conexion->prepare('SELECT id FROM usuarios WHERE usuario = ? LIMIT 1');
            $stmtInsertar = $conexion->prepare('INSERT INTO usuarios (usuario, contrasena, email, phone, nombre_completo, area, rol) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmtActualizar = $conexion->prepare('UPDATE usuarios SET contrasena = ?, email = ?, phone = ?, nombre_completo = ?, area = ? WHERE usuario = ?');
            $stmtLeer = $conexion->prepare('SELECT contrasena FROM usuarios WHERE usuario = ? LIMIT 1');
            if (!$stmtExiste || !$stmtInsertar || !$stmtActualizar || !$stmtLeer) {
                $errores[] = 'No se pudieron preparar las consultas de importación.';
            } else {
                $numeroFila = 1;
                while (($datos = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                    $numeroFila++;
                    if ($datos === [null] || (count($datos) === 1 && trim((string) $datos[0]) === '')) continue;
                    $valor = static function (string $campo) use ($mapa, $datos): string {
                        return (string) ($datos[$mapa[$campo]] ?? '');
                    };
                    $usuario = trim($valor('usuario'));
                    $nombre = trim($valor('nombre_completo'));
                    // No aplicar trim a la contraseña: espacios pueden formar parte de ella.
                    $contrasenaOriginal = $valor('contrasena');
                    $email = trim($valor('email'));
                    $phone = trim($valor('phone'));
                    $area = trim($valor('area'));
                    $rolArchivo = strtolower(trim($valor('rol')));

                    if ($usuario === '' || $nombre === '' || trim($contrasenaOriginal) === '' || $email === '' || $area === '') {
                        $errores[] = "Fila {$numeroFila}: faltan usuario, nombre, contraseña, correo o área.";
                        continue;
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $errores[] = "Fila {$numeroFila} ({$usuario}): correo no válido.";
                        continue;
                    }

                    $stmtExiste->bind_param('s', $usuario);
                    $stmtExiste->execute();
                    $stmtExiste->store_result();
                    $yaExiste = $stmtExiste->num_rows > 0;
                    $stmtExiste->free_result();

                    $rolesPermitidos = esSuperAdmin() ? ['usuario', 'admin', 'superadmin'] : ['usuario'];
                    $rol = in_array($rolArchivo, $rolesPermitidos, true) ? $rolArchivo : 'usuario';
                    $contrasenaNormalizada = normalizarContrasena($contrasenaOriginal);
                    $hash = password_hash($contrasenaNormalizada, PASSWORD_DEFAULT);
                    if (!is_string($hash) || !password_verify($contrasenaNormalizada, $hash)) {
                        $errores[] = "Fila {$numeroFila} ({$usuario}): no se pudo preparar el hash de contraseña.";
                        continue;
                    }

                    try {
                        $conexion->begin_transaction();
                        if ($yaExiste) {
                            // Actualiza datos de perfil y contraseña; preserva el rol
                            // existente para que el CSV no cambie privilegios.
                            $stmtActualizar->bind_param('ssssss', $hash, $email, $phone, $nombre, $area, $usuario);
                            $stmtActualizar->execute();
                        } else {
                            $stmtInsertar->bind_param('sssssss', $usuario, $hash, $email, $phone, $nombre, $area, $rol);
                            $stmtInsertar->execute();
                        }

                        $stmtLeer->bind_param('s', $usuario);
                        $stmtLeer->execute();
                        $filaGuardada = $stmtLeer->get_result()->fetch_assoc();
                        if (!$filaGuardada || !password_verify($contrasenaNormalizada, (string) $filaGuardada['contrasena'])) {
                            throw new RuntimeException('El hash guardado no pasó la verificación.');
                        }
                        $conexion->commit();
                        if ($yaExiste) $actualizados[] = $usuario;
                        else $agregados++;
                    } catch (Throwable $e) {
                        $conexion->rollback();
                        if ((int) $e->getCode() === 1062) {
                            $duplicados[] = $usuario;
                        } else {
                            error_log("Importación CSV, fila {$numeroFila}: " . $e->getMessage());
                            $errores[] = "Fila {$numeroFila} ({$usuario}): no se pudo guardar o verificar la contraseña.";
                        }
                    }
                }
            }
        }
    }
    fclose($handle);
}
$duplicados = array_values(array_unique($duplicados));
$actualizados = array_values(array_unique($actualizados));
?>
<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Resultado de importación</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><main class="container py-5" style="max-width:850px"><section class="card shadow-sm"><div class="card-body p-4">
<h1 class="h3 mb-4">Resultado de importación de usuarios</h1>
<div class="alert alert-success">Usuarios agregados: <strong><?= $agregados ?></strong></div>
<?php if ($actualizados): ?><div class="alert alert-info"><h2 class="h5">Usuarios duplicados detectados y actualizados</h2><p>Se actualizaron sus datos y contraseña desde el archivo; su rol actual se conservó.</p><ul class="mb-0"><?php foreach ($actualizados as $actualizado): ?><li><?= $escape($actualizado) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($duplicados): ?><div class="alert alert-warning"><h2 class="h5">Usuarios duplicados</h2><ul class="mb-0"><?php foreach ($duplicados as $duplicado): ?><li><?= $escape($duplicado) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($errores): ?><div class="alert alert-danger"><h2 class="h5">Filas que requieren atención</h2><ul class="mb-0"><?php foreach ($errores as $error): ?><li><?= $escape($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<a class="btn btn-primary" href="<?= htmlspecialchars(BASE_URL . 'admin/usuarios/subir_usuarios.php', ENT_QUOTES, 'UTF-8') ?>">Volver a importar</a>
<a class="btn btn-outline-secondary" href="<?= htmlspecialchars(BASE_URL . 'admin/usuarios/', ENT_QUOTES, 'UTF-8') ?>">Ver usuarios</a>
</div></section></main></body></html>
