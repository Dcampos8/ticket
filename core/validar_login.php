<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../config/env.php';

// reCAPTCHA v3: validar en servidor antes de consultar credenciales.
$siteKey = trim((string) env('RECAPTCHA_V3_SITE_KEY', ''));
$secretKey = trim((string) env('RECAPTCHA_V3_SECRET_KEY', ''));
$captchaToken = trim((string) ($_POST['recaptcha_token'] ?? ''));
$baseHost = parse_url((string) env('APP_BASE_URL', ''), PHP_URL_HOST);
$captchaValido = false;

if ($siteKey !== '' && $secretKey !== '' && $captchaToken !== '' && strlen($captchaToken) <= 4096) {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query(['secret' => $secretKey, 'response' => $captchaToken]),
            'timeout' => 5,
            'ignore_errors' => true,
        ],
    ]);
    $respuestaCaptcha = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    $resultadoCaptcha = is_string($respuestaCaptcha) ? json_decode($respuestaCaptcha, true) : null;
    $captchaValido = is_array($resultadoCaptcha)
        && ($resultadoCaptcha['success'] ?? false) === true
        && ($resultadoCaptcha['action'] ?? '') === 'login'
        && (float) ($resultadoCaptcha['score'] ?? 0) >= 0.5
        && is_string($baseHost)
        && strcasecmp((string) ($resultadoCaptcha['hostname'] ?? ''), $baseHost) === 0;
}

if (!$captchaValido) {
    header('Location: ../login.php?error=captcha');
    exit;
}

// Usamos @ para evitar que un include fallido dispare error 500 directamente
if (!file_exists('../backend/conexion.php')) {
    die("Error: No se encuentra el archivo de conexión.");
}
require_once('../backend/conexion.php');
require_once(__DIR__ . '/../shared/permisos.php');
require_once(__DIR__ . '/../shared/passwords.php');

$usuario = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';

// Verificamos que la variable $conexion exista
if (!isset($conexion)) {
    die("Error: La conexión a la base de datos no se inicializó.");
}

$stmt = $conexion->prepare("SELECT * FROM usuarios WHERE usuario = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$resultado = $stmt->get_result();

if ($fila = $resultado->fetch_assoc()) {
    if (isset($fila['activo']) && (int) $fila['activo'] !== 1) {
        $stmt->close();
        $conexion->close();
        header("Location: ../login.php?error=1");
        exit;
    }
    $contrasenaNormalizada = normalizarContrasena($contrasena);
    $contrasenaValida = password_verify($contrasenaNormalizada, (string) $fila['contrasena']);
    // Compatibilidad: hashes anteriores no están normalizados. Si la
    // contraseña exacta todavía valida, actualiza el hash al formato nuevo.
    if (!$contrasenaValida && $contrasenaNormalizada !== $contrasena
        && password_verify($contrasena, (string) $fila['contrasena'])) {
        $contrasenaValida = true;
        $hashNuevo = password_hash($contrasenaNormalizada, PASSWORD_DEFAULT);
        if (is_string($hashNuevo)) {
            $actualizarHash = $conexion->prepare('UPDATE usuarios SET contrasena = ? WHERE id = ?');
            if ($actualizarHash) {
                $actualizarHash->bind_param('si', $hashNuevo, $fila['id']);
                $actualizarHash->execute();
                $actualizarHash->close();
            }
        }
    }
    if ($contrasenaValida) {
        session_regenerate_id(true);
        $_SESSION['logueado'] = true;
        $_SESSION['id'] = $fila['id'];
        $_SESSION['usuario'] = $fila['usuario'];
        $_SESSION['rol'] = $fila['rol'];
        $_SESSION['nombre_completo'] = $fila['nombre_completo'];
        $_SESSION['area'] = $fila['area'];
        $_SESSION['puede_ver_todos_tickets'] = $fila['puede_ver_todos_tickets'];
        // Módulos que puede ver este admin dentro del panel (el superadmin
        // los ignora: siempre ve todo, ver shared/permisos.php).
        $_SESSION['modulos_permitidos'] = parsearModulos($fila['modulos_permitidos'] ?? null);

        $stmt->close();
        $conexion->close();

        $esPanelAdmin = in_array($fila['rol'], ['admin', 'superadmin'], true);
        header("Location: " . ($esPanelAdmin ? "../admin/" : "../usuario/"));
        exit;
    }
}

$conexion->close();
header("Location: ../login.php?error=1");
exit;
?>
