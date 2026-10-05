<?php
// 1. Cargar la configuración global
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad (Solo Admin)
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 4. Conexión a la base de datos (Usando ROOT_PATH)
require_once(ROOT_PATH . 'backend/conexion.php');

// 5. Cargar el menú usando la ruta absoluta
include($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');

$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id   = trim($_POST['employee_id'] ?? '');
    $employee_name = trim($_POST['employee_name'] ?? '');
    $puesto        = trim($_POST['puesto'] ?? '');

    if (empty($employee_id) || empty($employee_name) || empty($puesto)) {
        $err = "Debe ingresar ID, nombre y puesto del empleado.";
    } else {
        // Verificar si el empleado ya existe
        $stmtCheck = $conexion->prepare("SELECT COUNT(*) FROM employees WHERE employee_id=?");
        $stmtCheck->bind_param("s", $employee_id);
        $stmtCheck->execute();
        $stmtCheck->bind_result($count);
        $stmtCheck->fetch();
        $stmtCheck->close();

        if ($count > 0) {
            $err = "El empleado con ID <strong>$employee_id</strong> ya existe.";
        } else {
            // Insertar nuevo empleado
            $stmtInsert = $conexion->prepare("
                INSERT INTO employees (employee_id, employee_name, puesto, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmtInsert->bind_param("sss", $employee_id, $employee_name, $puesto);
            
            if ($stmtInsert->execute()) {
                $msg = "Empleado agregado correctamente.";
            } else {
                $err = "Error al agregar empleado: " . $stmtInsert->error;
            }
            $stmtInsert->close();
        }
    }
}
?>
<style>
    body{
        margin-top: 100px;
    }
</style>

<div class="content" style="margin-left: 220px; padding-top: 20px;">
    <div class="container-fluid mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white p-3">
                        <h4 class="mb-0"><i class="fa-solid fa-user-plus me-2"></i>Agregar Nuevo Empleado</h4>
                    </div>
                    <div class="card-body p-4">

                        <?php if(!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-circle-check me-2"></i><?= $msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php elseif(!empty($err)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $err ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Número de empleado</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-hashtag"></i></span>
                                    <input type="text" class="form-control" name="employee_id" placeholder="Ej: 45001" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label fw-bold">Nombre completo</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                                    <input type="text" class="form-control" name="employee_name" placeholder="Nombre y Apellidos" required>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold">Puesto</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-briefcase"></i></span>
                                    <input type="text" class="form-control" name="puesto" placeholder="Ej: Operador de Quinta Rueda" required>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Agregar Empleado
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    Cancelar y Volver
                                </a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($conexion)) {
    $conexion->close(); 
}
?>