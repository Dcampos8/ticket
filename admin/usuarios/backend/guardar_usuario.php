<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../../../config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(ROOT_PATH . 'shared/permisos.php');
requerirAdmin();
require_once(ROOT_PATH . 'shared/security.php');
require_once(ROOT_PATH . 'shared/passwords.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso inválido");
}
if (!validarTokenCsrf()) {
    http_response_code(403);
    exit('La sesión expiró. Regresa al formulario e inténtalo de nuevo.');
}

// 1. Capturar datos incluyendo el nuevo campo 'email'
$usuario    = $_POST['usuario'] ?? '';
$contrasena = $_POST['contrasena'] ?? '';
$nombre     = $_POST['nombre'] ?? '';
$email      = $_POST['email'] ?? '';
$phone      = $_POST['phone'] ?? '';
$area       = $_POST['area'] ?? '';
$rol        = $_POST['rol'] ?? '';

// 2. Solo el superadmin puede crear cuentas admin/superadmin. Cualquier
//    otro valor recibido de un admin normal se degrada a 'usuario' para
//    evitar que alguien se auto-eleve editando el formulario a mano.
if (!esSuperAdmin() && in_array($rol, ['admin', 'superadmin'], true)) {
    $rol = 'usuario';
}
if (!in_array($rol, ['usuario', 'admin', 'superadmin'], true)) {
    $rol = 'usuario';
}

// 3. Módulos permitidos: solo tienen sentido para rol 'admin'
$modulosCsv = null;
if ($rol === 'admin') {
    $modulosRecibidos = $_POST['modulos'] ?? [];
    if (!is_array($modulosRecibidos)) {
        $modulosRecibidos = [];
    }
    $modulosCsv = serializarModulos($modulosRecibidos);
}

// 4. Hashear la contraseña antes de guardarla.
if ($contrasena === '') {
    http_response_code(400);
    exit('La contraseña es obligatoria.');
}
$contrasenaNormalizada = normalizarContrasena($contrasena);
$contrasena_hash = password_hash($contrasenaNormalizada, PASSWORD_DEFAULT);
if (!is_string($contrasena_hash) || !password_verify($contrasenaNormalizada, $contrasena_hash)) {
    error_log('No se pudo generar/verificar el hash al crear un usuario.');
    http_response_code(500);
    exit('No se pudo preparar la contraseña. No se creó el usuario.');
}

// 5. VALIDAR SI YA EXISTE
$check = $conexion->prepare("SELECT id FROM usuarios WHERE usuario = ?");
$check->bind_param("s", $usuario);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("❌ El usuario ya existe");
}
$check->close();

// 6. INSERT actualizado con el campo 'email' y 'modulos_permitidos'
$stmt = $conexion->prepare("
    INSERT INTO usuarios (usuario, contrasena, nombre_completo, email, phone, area, rol, modulos_permitidos)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("ssssssss", $usuario, $contrasena_hash, $nombre, $email, $phone, $area, $rol, $modulosCsv);

try {
    $conexion->begin_transaction();
    if (!$stmt->execute()) {
        throw new RuntimeException('No se pudo insertar el registro.');
    }

    // Verifica el hash que realmente quedó persistido; detecta columnas
    // demasiado cortas o cualquier alteración antes de confirmar el alta.
    $idNuevo = (int) $conexion->insert_id;
    $verificar = $conexion->prepare('SELECT contrasena FROM usuarios WHERE id = ? LIMIT 1');
    if (!$verificar) {
        throw new RuntimeException('No se pudo verificar el registro insertado.');
    }
    $verificar->bind_param('i', $idNuevo);
    $verificar->execute();
    $filaGuardada = $verificar->get_result()->fetch_assoc();
    $verificar->close();
    if (!$filaGuardada || !password_verify($contrasenaNormalizada, (string) $filaGuardada['contrasena'])) {
        throw new RuntimeException('El hash guardado no pasó la verificación de contraseña.');
    }

    $conexion->commit();
    header("Location: " . BASE_URL . "admin/usuarios/index.php?msg=success");
    exit();
} catch (Throwable $e) {
    $conexion->rollback();
    error_log('Alta de usuario cancelada durante la verificación de contraseña: ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo confirmar el alta del usuario. Verifica la configuración de la base de datos o contacta a soporte.');
}
?>
