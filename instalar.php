<?php
declare(strict_types=1);

// Asistente inicial: configura el .env y valida la conexión. No crea ni modifica tablas.
session_start();
$envPath = __DIR__ . '/.env';
$installed = false;
if (is_file($envPath)) {
    $current = (string) file_get_contents($envPath);
    $installed = preg_match('/^DB_HOST\s*=\s*.+$/m', $current) && preg_match('/^DB_NAME\s*=\s*.+$/m', $current);
}
if (empty($_SESSION['installer_token'])) $_SESSION['installer_token'] = bin2hex(random_bytes(32));
$error = '';
$success = false;
$values = ['mode' => 'local', 'host' => 'localhost', 'port' => '3306', 'name' => '', 'user' => '', 'url' => 'http://localhost/'];

function h(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function envLine(string $value): string { return str_replace(["\r", "\n"], '', $value); }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    foreach (['mode', 'host', 'port', 'name', 'user', 'url'] as $key) $values[$key] = trim((string) ($_POST[$key] ?? $values[$key]));
    $password = (string) ($_POST['password'] ?? '');
    if (!hash_equals($_SESSION['installer_token'], (string) ($_POST['token'] ?? ''))) {
        $error = 'La sesión del instalador expiró. Recarga la página e intenta de nuevo.';
    } elseif (!in_array($values['mode'], ['local', 'instance'], true) || $values['host'] === '' || $values['name'] === '' || $values['user'] === '' || !filter_var($values['port'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]) || !filter_var($values['url'], FILTER_VALIDATE_URL)) {
        $error = 'Revisa los campos requeridos, el puerto y la URL base.';
    } elseif (!extension_loaded('mysqli') || !extension_loaded('openssl')) {
        $error = 'PHP requiere las extensiones mysqli y openssl.';
    } elseif (!is_writable(__DIR__)) {
        $error = 'PHP necesita permiso de escritura en la carpeta del proyecto para crear .env.';
    } else {
        mysqli_report(MYSQLI_REPORT_OFF);
        $db = @new mysqli($values['host'], $values['user'], $password, $values['name'], (int) $values['port']);
        if ($db->connect_errno) {
            $error = 'No se pudo conectar a MySQL. Verifica servidor, puerto, base de datos y credenciales.';
        } else {
            $key = bin2hex(random_bytes(32));
            $contents = "# Generado por instalar.php. Mantener fuera de Git y del directorio público.\n"
                . 'APP_BASE_URL=' . envLine(rtrim($values['url'], '/') . '/') . "\n"
                . 'APP_SETTINGS_ENCRYPTION_KEY=' . envLine($key) . "\n\n"
                . 'DB_HOST=' . envLine($values['host']) . "\n"
                . 'DB_PORT=' . envLine($values['port']) . "\n"
                . 'DB_USER=' . envLine($values['user']) . "\n"
                . 'DB_PASS=' . envLine($password) . "\n"
                . 'DB_NAME=' . envLine($values['name']) . "\n";
            if (file_put_contents($envPath, $contents, LOCK_EX) === false) {
                $error = 'No se pudo guardar .env. Revisa los permisos de la carpeta.';
            } else {
                @chmod($envPath, 0600);
                $installed = $success = true;
                $db->close();
            }
            if (!$success) $db->close();
        }
    }
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalador | Sistema de Tickets</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f1f5f9;color:#172033;font:16px system-ui,-apple-system,Segoe UI,sans-serif}.wrap{max-width:650px;margin:6vh auto;padding:20px}.card{background:white;border:1px solid #dce3ec;border-radius:16px;padding:30px;box-shadow:0 16px 40px #17203312}h1{margin:0 0 8px;font-size:27px}p{line-height:1.55;color:#526078}.grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}.field{margin:12px 0}.field.full{grid-column:1/-1}label{display:block;font-size:14px;font-weight:650;margin-bottom:6px}input,select{width:100%;padding:11px 12px;border:1px solid #c8d1df;border-radius:8px;font:inherit}button,.button{display:inline-block;border:0;border-radius:8px;background:#155eef;color:white;font:inherit;font-weight:650;padding:12px 18px;text-decoration:none;cursor:pointer;margin-top:10px}.notice{padding:13px 15px;border-radius:9px;margin:16px 0}.error{background:#fff1f0;color:#9a2718}.ok{background:#ecfdf3;color:#05603a}.hint{font-size:13px;color:#667085}.hidden{display:none}@media(max-width:560px){.grid{grid-template-columns:1fr}.field.full{grid-column:auto}.card{padding:22px}}
</style></head><body><main class="wrap"><section class="card">
<h1>Instalador del sistema</h1><p>Configura el servidor y la base de datos para iniciar la aplicación.</p>
<?php if ($success): ?>
<div class="notice ok"><strong>Configuración guardada.</strong><br>La conexión con MySQL fue validada y se creó el archivo <code>.env</code>. La llave para cifrar integraciones se generó automáticamente.</div>
<p>Falta importar la base de datos de la aplicación y aplicar las migraciones SQL disponibles. El instalador no crea tablas ni usuarios administradores.</p><p class="hint">Por seguridad, elimina o renombra <code>instalar.php</code> después de completar la configuración.</p><a class="button" href="index.php">Abrir aplicación</a>
<?php elseif ($installed): ?>
<div class="notice ok"><strong>Este sistema ya está configurado.</strong><br>Se detectó una conexión definida en <code>.env</code>; el instalador está bloqueado para evitar sobrescribirla.</div><p>Para instalar otra copia, usa un directorio nuevo y copia los archivos del proyecto sin el <code>.env</code> existente.</p>
<?php else: ?>
<?php if ($error !== ''): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
<form method="post" autocomplete="off"><input type="hidden" name="token" value="<?= h($_SESSION['installer_token']) ?>">
<div class="field full"><label for="mode">Tipo de servidor</label><select id="mode" name="mode"><option value="local" <?= $values['mode']==='local'?'selected':'' ?>>Localhost (mi equipo)</option><option value="instance" <?= $values['mode']==='instance'?'selected':'' ?>>Instancia o servidor remoto</option></select></div>
<div class="grid"><div class="field"><label for="host">Host MySQL</label><input id="host" name="host" required value="<?= h($values['host']) ?>" placeholder="localhost o IP/dominio"></div><div class="field"><label for="port">Puerto MySQL</label><input id="port" name="port" type="number" min="1" max="65535" required value="<?= h($values['port']) ?>"></div>
<div class="field"><label for="name">Base de datos</label><input id="name" name="name" required value="<?= h($values['name']) ?>"></div><div class="field"><label for="user">Usuario MySQL</label><input id="user" name="user" required value="<?= h($values['user']) ?>"></div>
<div class="field full"><label for="password">Contraseña MySQL</label><input id="password" name="password" type="password" autocomplete="new-password"></div><div class="field full"><label for="url">URL base de la aplicación</label><input id="url" name="url" type="url" required value="<?= h($values['url']) ?>" placeholder="http://localhost/ticket/"><span class="hint">Incluye la ruta de la aplicación si está dentro de una subcarpeta.</span></div></div>
<button type="submit">Validar conexión y guardar</button></form><p class="hint">Solo se piden los datos requeridos para operar. Correo, Telegram, Gemini y conexiones secundarias se configuran después desde el panel.</p>
<?php endif; ?></section></main>
<script>document.getElementById('mode')?.addEventListener('change',e=>{const host=document.getElementById('host');if(e.target.value==='local'&&['','localhost'].includes(host.value))host.value='localhost';});</script></body></html>
