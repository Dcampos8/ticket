<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 3. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Cargar helper de permisos y verificar seguridad
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('usuarios');

// 5. Cargar el menú
include(ROOT_PATH . 'admin/menu.php');

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
$puedeAsignarRoles = $actorEsSuperAdmin;

// 6. Obtener datos del usuario
$usuario = null;
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conexion->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
}

// Si no se encuentra el usuario, redirigir
if (!$usuario) {
    echo "<script>alert('Usuario no encontrado'); window.location='index.php';</script>";
    exit();
}

// Un admin puede administrar usuarios, pero la cuenta superadmin queda reservada.
if (strtolower(trim((string) ($usuario['rol'] ?? ''))) === 'superadmin' && !$actorEsSuperAdmin) {
    http_response_code(403);
    exit('No tienes permiso para ver o modificar esta cuenta.');
}

$modulosUsuario = parsearModulos($usuario['modulos_permitidos'] ?? null);
?>

<div class="content" style="padding-top: 20px; min-height: calc(100vh - 56px); background-color: #f5f5f5;">
  <div class="d-flex justify-content-center align-items-start px-3">
    <!-- El action ahora usa BASE_URL para no fallar nunca -->
    <form action="<?= BASE_URL ?>admin/usuarios/backend/actualizar_usuario.php" method="POST" class="bg-white p-4 shadow-sm rounded" style="width: 100%; max-width: 600px;">
      <h2 class="mb-4 text-center text-danger">Editar Usuario</h2>

      <input type="hidden" name="id" value="<?= $usuario['id']; ?>">

      <div class="mb-3">
        <label class="form-label">Usuario:</label>
        <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Nombre Completo:</label>
        <input type="text" name="nomcom" class="form-control" value="<?= htmlspecialchars($usuario['nombre_completo']) ?>" required>
      </div>

      <div class="mb-3">
        <label class="form-label">Correo Electrónico:</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Telefono:</label>
        <input type="number" name="phone" class="form-control" value="<?= htmlspecialchars($usuario['phone'] ?? '') ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Contraseña (dejar en blanco si no cambia):</label>
        <input type="password" name="contrasena" class="form-control" placeholder="Nueva contraseña opcional">
      </div>

      <div class="mb-3">
        <label class="form-label">Área:</label>
        <input type="text" name="area" class="form-control" value="<?= htmlspecialchars($usuario['area']) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label">Rol:</label>
        <select name="rol" id="rolSelect" class="form-select" required <?= $puedeAsignarRoles ? '' : 'disabled' ?>>
          <option value="usuario" <?= $usuario['rol'] === 'usuario' ? 'selected' : '' ?>>Usuario</option>
          <?php if ($puedeAsignarRoles || $usuario['rol'] === 'admin'): ?>
            <option value="admin" <?= $usuario['rol'] === 'admin' ? 'selected' : '' ?>>Administrador</option>
          <?php endif; ?>
          <?php if ($puedeAsignarRoles || $usuario['rol'] === 'superadmin'): ?>
            <option value="superadmin" <?= $usuario['rol'] === 'superadmin' ? 'selected' : '' ?>>Superadministrador</option>
          <?php endif; ?>
        </select>
        <?php if (!$puedeAsignarRoles): ?>
          <div class="form-text">Solo el superadministrador puede cambiar roles.</div>
          <!-- Si el select está disabled, el navegador no lo envía: lo replicamos oculto -->
          <input type="hidden" name="rol" value="<?= htmlspecialchars($usuario['rol']) ?>">
        <?php endif; ?>
      </div>

      <?php if ($puedeAsignarRoles): ?>
      <div class="mb-4" id="bloqueModulos" style="<?= $usuario['rol'] === 'admin' ? '' : 'display:none;' ?> border:1px solid #dee2e6; border-radius:0.6rem; padding:0.9rem; background:#f8f9fa;">
        <label class="form-label fw-bold mb-2"><i class="fa-solid fa-shield-halved me-1"></i>Módulos que puede ver este administrador</label>
        <?php foreach (MODULOS_DISPONIBLES as $clave => $info): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="modulos[]" value="<?= htmlspecialchars($clave) ?>" id="mod_<?= htmlspecialchars($clave) ?>"
                   <?= in_array($clave, $modulosUsuario, true) ? 'checked' : '' ?>>
            <label class="form-check-label" for="mod_<?= htmlspecialchars($clave) ?>">
              <i class="fa-solid <?= htmlspecialchars($info['icon']) ?> me-1"></i><?= htmlspecialchars($info['label']) ?>
            </label>
          </div>
        <?php endforeach; ?>
        <div class="form-text">No aplica si el rol es Superadministrador (ese siempre ve todo).</div>
      </div>
      <?php endif; ?>

      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-danger">Actualizar</button>
        <a href="<?= BASE_URL ?>admin/usuarios/index.php" class="btn btn-secondary">Cancelar</a>
      </div>
    </form>
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

<?php
if (isset($conexion)) {
    $conexion->close();
}
?>
