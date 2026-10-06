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

<style>
    /* Estilos específicos para la página de Perfil */
    body {
        background-color: #f1f5f9;
        font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    .profile-card {
        background: #ffffff;
        border: 1px solid rgba(226, 232, 240, 0.8);
        border-radius: 20px;
        box-shadow: 0 20px 30px -10px rgba(15, 23, 42, 0.08);
        overflow: hidden;
        max-width: 720px;
        margin: 0 auto;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .profile-header {
        background: #172033;
        padding: 40px 30px 30px;
        color: white;
        text-align: center;
        position: relative;
    }

    .profile-header::after {
        content: "";
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #c9252d;
    }

    .profile-avatar-big {
        width: 90px;
        height: 90px;
        background: #c9252d;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.2rem;
        color: #ffffff;
        margin: 0 auto 15px;
        border: 4px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
    }

    .profile-body {
        padding: 40px;
    }

    .form-label {
        font-weight: 600;
        color: #475569;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
        display: block;
    }

    .input-group-custom {
        position: relative;
        margin-bottom: 22px;
    }

    .input-group-custom i {
        position: absolute;
        left: 16px;
        bottom: 14px;
        color: #94a3b8;
        font-size: 1rem;
        transition: color 0.3s ease;
        z-index: 5;
    }

    .form-control-custom {
        border: 1.5px solid #e2e8f0;
        border-radius: 12px;
        padding: 12px 16px 12px 46px;
        font-size: 0.95rem;
        transition: all 0.25s ease;
        background-color: #f8fafc;
        color: #1e293b;
        width: 100%;
    }

    .form-control-custom:focus {
        border-color: #ef4444;
        box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.12);
        background-color: #ffffff;
        outline: none;
    }

    .form-control-custom:focus + i,
    .input-group-custom:focus-within i {
        color: #ef4444;
    }

    .form-control-readonly {
        background-color: #f1f5f9 !important;
        border-color: #e2e8f0 !important;
        cursor: not-allowed;
        color: #64748b !important;
        font-weight: 500;
    }

    .btn-update {
        background: #c9252d;
        color: #ffffff;
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-weight: 700;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        width: 100%;
        margin-top: 15px;
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);
    }

    .btn-update:hover {
        background: #a51f26;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(239, 68, 68, 0.35);
        color: #ffffff;
    }

    .btn-update:active {
        transform: translateY(0);
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: #0f172a;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
    }

    .section-title::after {
        content: "";
        flex: 1;
        height: 1px;
        background: #e2e8f0;
        margin-left: 15px;
    }

    .badge-role {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
        border: 1px solid rgba(239, 68, 68, 0.3);
        font-weight: 600;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
    }
</style>

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
