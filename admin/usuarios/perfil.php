<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

require_once(ROOT_PATH . 'admin/menu.php');

if (!isset($_SESSION['logueado'])) {
    header("Location: ../login.php");
    exit();
}

$usuario = $_SESSION['usuario'] ?? 'Usuario';
$nombre_completo = $_SESSION['nombre_completo'] ?? '';
$area = $_SESSION['area'] ?? '';
$rol = $_SESSION['rol'] ?? 'usuario';
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__perfil.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__usuarios__perfil.css') ?>">

<div id="mainContent" class="container py-4">
    <div class="profile-card">
        
        <div class="profile-header">
            <div class="profile-avatar-big">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="mb-1 fw-bold"><?= htmlspecialchars($usuario) ?></h4>
            <span class="badge badge-role px-3 py-2 rounded-pill"><?= htmlspecialchars(ucfirst($rol)) ?></span>
        </div>

        <div class="profile-body">
            <h5 class="section-title">Información Personal</h5>

            <form action="/backend/actualizar_usuario.php" method="POST">
                
                <div class="row">
                    <div class="col-md-6 input-group-custom">
                        <label class="form-label">Usuario</label>
                        <input type="text" class="form-control form-control-custom form-control-readonly" value="<?= htmlspecialchars($usuario) ?>" readonly>
                        <i class="fas fa-at"></i>
                    </div>

                    <div class="col-md-6 input-group-custom">
                        <label class="form-label">Nivel de Acceso</label>
                        <input type="text" class="form-control form-control-custom form-control-readonly" value="<?= htmlspecialchars(ucfirst($rol)) ?>" readonly>
                        <i class="fas fa-shield-alt"></i>
                    </div>
                </div>

                <div class="input-group-custom">
                    <label class="form-label">Nombre Completo</label>
                    <input type="text" name="nombre_completo" class="form-control form-control-custom" value="<?= htmlspecialchars($nombre_completo) ?>" required placeholder="Tu nombre">
                    <i class="fas fa-id-card"></i>
                </div>

                <div class="input-group-custom">
                    <label class="form-label">Área / Departamento</label>
                    <input type="text" name="area" class="form-control form-control-custom" value="<?= htmlspecialchars($area) ?>" placeholder="Ej. Sistemas, Logística...">
                    <i class="fas fa-building"></i>
                </div>

                <h5 class="section-title mt-4">Seguridad</h5>

                <div class="input-group-custom">
                    <label class="form-label">Nueva Contraseña</label>
                    <input type="password" name="contrasena" class="form-control form-control-custom" placeholder="Dejar en blanco para no cambiar">
                    <i class="fas fa-lock"></i>
                </div>

                <button type="submit" class="btn btn-update">
                    <i class="fas fa-save me-2"></i> Guardar Cambios
                </button>
            </form>
        </div>
    </div>
</div>
