<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../shared/permisos.php';
require_once __DIR__ . '/../../../shared/passwords.php';
require_once __DIR__ . '/../../../shared/security.php';

// Requiere estar logueado, ser admin/superadmin y tener el módulo "usuarios".
// (BASE_URL/ROOT_PATH aún no están definidas aquí porque este archivo no
// carga config.php; por eso requerirModulo() no se puede usar tal cual.
// Hacemos el mismo chequeo a mano.)
if (!isset($_SESSION['logueado']) || !esAdminOSuperior()) {
    header("Location: ../../../login.php");
    exit();
}
if (!esSuperAdmin() && !tieneModulo('usuarios')) {
    http_response_code(403);
    exit("No autorizado");
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf()) {
    http_response_code(400);
    exit('Solicitud no válida.');
}

include('../../../backend/conexion.php');
$actorEsSuperAdmin = false;
$idActor = (int) ($_SESSION['id'] ?? 0);
if ($idActor > 0) {
    $stmtActor = $conexion->prepare("SELECT rol FROM usuarios WHERE id = ?");
    if ($stmtActor) {
        $stmtActor->bind_param("i", $idActor);
        $stmtActor->execute();
        $rolActor = $stmtActor->get_result()->fetch_assoc()['rol'] ?? '';
        $stmtActor->close();
        $actorEsSuperAdmin = strtolower(trim((string) $rolActor)) === 'superadmin';
    }
}
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id      = intval($_POST["id"]);
    $usuario = $_POST["usuario"];
    $nomcom  = $_POST["nomcom"];
    $email   = $_POST['email'];
    $phone   = $_POST['phone'];
    $area    = $_POST["area"];
    $rol     = strtolower(trim((string) ($_POST["rol"] ?? 'usuario')));
    $activoPost = (string) ($_POST['activo'] ?? '1');
    if (!in_array($activoPost, ['0', '1'], true)) {
        http_response_code(400);
        exit('Estado no válido.');
    }
    $activo = (int) $activoPost;

    // Bloquea cambios directos por POST sobre la cuenta superadmin.
    $stmtActual = $conexion->prepare("SELECT rol, modulos_permitidos FROM usuarios WHERE id = ?");
    $stmtActual->bind_param("i", $id);
    $stmtActual->execute();
    $usuarioActual = $stmtActual->get_result()->fetch_assoc();
    $stmtActual->close();
    if (!$usuarioActual) {
        http_response_code(404);
        exit("Usuario no encontrado");
    }
    if (strtolower(trim((string) ($usuarioActual['rol'] ?? ''))) === 'superadmin' && !$actorEsSuperAdmin) {
        http_response_code(403);
        exit("No tienes permiso para modificar esta cuenta");
    }
    if ($id === $idActor && $activo === 0) {
        http_response_code(400);
        exit("No puedes desactivar tu propia cuenta");
    }

    if ($conexion->connect_error) {
        die("Conexión fallida: " . $conexion->connect_error);
    }

    if (!in_array($rol, ['usuario', 'admin', 'superadmin'], true)) {
        $rol = 'usuario';
    }

    // Solo el superadmin puede modificar roles.
    $rolActual = strtolower(trim((string) ($usuarioActual['rol'] ?? '')));
    if (!$actorEsSuperAdmin && $rol !== $rolActual) {
        http_response_code(403);
        die("No autorizado para cambiar el rol");
    }

    // Módulos: solo aplican si el rol final es 'admin'. Si deja de ser
    // admin (o es superadmin), se limpian.
    $modulosCsv = null;
    if ($rol === 'admin') {
        if ($actorEsSuperAdmin) {
            $modulosRecibidos = $_POST['modulos'] ?? [];
            if (!is_array($modulosRecibidos)) {
                $modulosRecibidos = [];
            }
            $modulosCsv = serializarModulos($modulosRecibidos);
        } elseif ($rolActual === 'admin') {
            // Un admin puede editar los datos de otro admin, sin tocar sus módulos.
            $modulosCsv = $usuarioActual['modulos_permitidos'] ?? null;
        }
    }

    if (!empty($_POST['contrasena'])) {
        $contrasenaNormalizada = normalizarContrasena((string) $_POST['contrasena']);
        $hash = password_hash($contrasenaNormalizada, PASSWORD_DEFAULT);

        // SQL: orden estricto de columnas
        $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, phone=?, area=?, rol=?, modulos_permitidos=?, contrasena=?, activo=? WHERE id=?");

        // bind_param: orden estricto de variables
        $stmt->bind_param("ssssssssii", $usuario, $nomcom, $email, $phone, $area, $rol, $modulosCsv, $hash, $activo, $id);
    } else {
        // SQL: orden estricto de columnas
        $stmt = $conexion->prepare("UPDATE usuarios SET usuario=?, nombre_completo=?, email=?, phone=?, area=?, rol=?, modulos_permitidos=?, activo=? WHERE id=?");

        // bind_param: orden estricto de variables
        $stmt->bind_param("sssssssii", $usuario, $nomcom, $email, $phone, $area, $rol, $modulosCsv, $activo, $id);
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
