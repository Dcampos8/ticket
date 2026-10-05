<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die("Acceso inválido");
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

// 4. Cifrar la contraseña
$contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);

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

if ($stmt->execute()) {
    header("Location: " . BASE_URL . "admin/usuarios/index.php?msg=success");
    exit();
} else {
    echo "❌ Error al crear usuario: " . $stmt->error;
}
?>
