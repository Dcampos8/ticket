<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../shared/permisos.php';

// Requiere estar logueado, ser admin/superadmin y tener el módulo "usuarios".
// (BASE_URL/ROOT_PATH aún no están definidas aquí porque este archivo no
// carga config.php; por eso requerirModulo() no se puede usar tal cual.
// Hacemos el mismo chequeo a mano.)
if (!isset($_SESSION['logueado']) || !esAdminOSuperior()) {
    header("Location: ../../../login.php");
    exit();
}
if (!esSuperAdmin() && !in_array('usuarios', $_SESSION['modulos_permitidos'] ?? [], true)) {
    http_response_code(403);
    exit("No autorizado");
}

include('../../../backend/conexion.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id      = intval($_POST["id"]);
    $usuario = $_POST["usuario"];
    $nomcom  = $_POST["nomcom"];
    $email   = $_POST['email'];
    $phone   = $_POST['phone'];
    $area    = $_POST["area"];
    $rol     = $_POST["rol"];

    if ($conexion->connect_error) {
        die("Conexión fallida: " . $conexion->connect_error);
    }

    // Solo el superadmin puede dejar/poner a alguien como admin o superadmin.
    if (!esSuperAdmin() && in_array($rol, ['admin', 'superadmin'], true)) {
        http_response_code(403);
        die("No autorizado para asignar ese rol");
    }
    if (!in_array($rol, ['usuario', 'admin', 'superadmin'], true)) {
        $rol = 'usuario';
    }

    // Módulos: solo aplican si el rol final es 'admin'. Si deja de ser
    // admin (o es superadmin), se limpian.
    $modulosCsv = null;
    if ($rol === 'admin') {
        $modulosRecibidos = $_POST['modulos'] ?? [];
        if (!is_array($modulosRecibidos)) {
            $modulosRecibidos = [];
        }
        $modulosCsv = serializarModulos($modulosRecibidos);
    }

    if (!empty($_POST['contrasena'])) {
        $hash = password_hash($_POST['contrasena'], PASSWORD_DEFAULT);

        // SQL: orden estricto de columnas
        $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, phone=?, area=?, rol=?, modulos_permitidos=?, contrasena=? WHERE id=?");

        // bind_param: orden estricto de variables
        $stmt->bind_param("ssssssssi", $usuario, $nomcom, $email, $phone, $area, $rol, $modulosCsv, $hash, $id);
    } else {
        // SQL: orden estricto de columnas
        $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, phone=?, area=?, rol=?, modulos_permitidos=? WHERE id=?");

        // bind_param: orden estricto de variables
        $stmt->bind_param("sssssssi", $usuario, $nomcom, $email, $phone, $area, $rol, $modulosCsv, $id);
    }

    if ($stmt->execute()) {
        header("Location: ../index.php?msg=success");
    } else {
        echo "❌ Error al actualizar: " . $stmt->error;
    }

    $stmt->close();
    $conexion->close();
}
?>
