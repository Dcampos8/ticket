<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

// Usamos @ para evitar que un include fallido dispare error 500 directamente
if (!file_exists('../backend/conexion.php')) {
    die("Error: No se encuentra el archivo de conexión.");
}
require_once('../backend/conexion.php');
require_once(__DIR__ . '/../shared/permisos.php');

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
    if (password_verify($contrasena, $fila['contrasena'])) {
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
