<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Cargar helper de permisos y verificar seguridad (necesita el módulo "usuarios")
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');

// 4. Cargar el menú usando la ruta absoluta
include(ROOT_PATH . 'admin/menu.php');

$puedeAsignarRoles = esSuperAdmin();
?>
  <style>
     .create-user-page {
      background: #f3f5f8;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1rem;
      margin-top: 0;
    }

    .card {
      animation: fadeIn 0.6s ease-in-out;
      border-radius: 1rem;
    }

    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(20px); }
      to   { opacity: 1; transform: translateY(0); }
    }

    .form-icon {
      position: absolute;
      left: 12px;
      top: 50%;
      transform: translateY(-50%);
      color: #6c757d;
      font-size: 1rem;
      z-index: 10; /* Asegura que el icono esté arriba */
    }

    .input-group .form-control {
      padding-left: 2.5rem; /* Ajuste ligero para que no choque con el icono */
    }

    #bloqueModulos {
      border: 1px solid #dee2e6;
      border-radius: 0.6rem;
      padding: 0.9rem;
      background: #f8f9fa;
    }

    #bloqueModulos .form-check { margin-bottom: 0.4rem; }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
      h3 { font-size: 1.4rem; }
      .card-body { padding: 1.5rem; }
    }

    @media (max-width: 576px) {
      h3 { font-size: 1.2rem; }
      .card { border-radius: 0.8rem; }
       .create-user-page { padding: 0.5rem; }
    }
  </style>
<div class="container-fluid create-user-page">
  <div class="row justify-content-center">
    <div class="col-sm-10 col-md-7 col-lg-5 col-xl-4">
      <div class="card shadow-lg">
        <div class="card-body p-4">

          <h3 class="text-center mb-4">
            <i class="fa-solid fa-user-plus me-2"></i>Crear Nuevo Usuario
          </h3>

          <!-- Usamos BASE_URL para la acción del formulario -->
          <form action="<?= BASE_URL ?>admin/usuarios/backend/guardar_usuario.php" method="POST" id="create">

            <div class="mb-3 position-relative">
              <label class="form-label">Nombre de usuario</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-user"></i></span>
                <input type="text" class="form-control" name="usuario" placeholder="usuario123" required>
              </div>
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Contraseña</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-lock"></i></span>
                <input type="password" class="form-control" name="contrasena" placeholder="••••••••" required>
              </div>
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Nombre completo</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-id-card"></i></span>
                <input type="text" class="form-control" name="nombre" placeholder="Juan Pérez" required>
              </div>
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Correo Electrónico</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-envelope"></i></span>
                <input type="email" class="form-control" name="email" placeholder="ejemplo@empresa.com" required>
              </div>
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Correo Electrónico</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-envelope"></i></span>
                <input type="number" class="form-control" name="phone" placeholder="4491234567">
              </div>
            </div>

            <div class="mb-3 position-relative">
              <label class="form-label">Área</label>
              <div class="input-group">
                <span class="form-icon"><i class="fa-solid fa-building-user"></i></span>
                <input type="text" class="form-control" name="area" placeholder="Sistemas, RH, Logística..." required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Rol</label>
              <select class="form-select" name="rol" id="rolSelect" required>
                <option value="" selected disabled>Selecciona un rol</option>
                <option value="usuario">Usuario</option>
                <?php if ($puedeAsignarRoles): ?>
                  <option value="admin">Administrador</option>
                  <option value="superadmin">Superadministrador</option>
                <?php endif; ?>
              </select>
              <?php if (!$puedeAsignarRoles): ?>
                <div class="form-text">Solo el superadministrador puede crear cuentas de Administrador.</div>
              <?php endif; ?>
            </div>

            <?php if ($puedeAsignarRoles): ?>
            <div class="mb-4" id="bloqueModulos" style="display:none;">
              <label class="form-label fw-bold mb-2"><i class="fa-solid fa-shield-halved me-1"></i>Módulos que podrá ver este administrador</label>
              <?php foreach (MODULOS_DISPONIBLES as $clave => $info): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= htmlspecialchars($clave) ?>" id="mod_<?= htmlspecialchars($clave) ?>">
                  <label class="form-check-label" for="mod_<?= htmlspecialchars($clave) ?>">
                    <i class="fa-solid <?= htmlspecialchars($info['icon']) ?> me-1"></i><?= htmlspecialchars($info['label']) ?>
                  </label>
                </div>
              <?php endforeach; ?>
              <div class="form-text">No aplica si el rol es Superadministrador (ese siempre ve todo).</div>
            </div>
            <?php endif; ?>

            <div class="d-grid gap-2">
              <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Usuario
              </button>
              <a href="index.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Volver
              </a>
            </div>

          </form>

        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($puedeAsignarRoles): ?>
<script>
  const rolSelect = document.getElementById('rolSelect');
  const bloqueModulos = document.getElementById('bloqueModulos');
  function actualizarBloqueModulos() {
    bloqueModulos.style.display = (rolSelect.value === 'admin') ? 'block' : 'none';
  }
  rolSelect.addEventListener('change', actualizarBloqueModulos);
  actualizarBloqueModulos();
</script>
<?php endif; ?>
