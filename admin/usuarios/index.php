<?php
// 1. Cargar la configuración global (Ajusta la ruta si config.php está en otro nivel)
require_once(__DIR__ . '/../../config.php');

// 2. Conexión a la base de datos (Usando ROOT_PATH definido en config.php)
require_once(ROOT_PATH . 'backend/conexion.php');

// 3. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3b. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
require_once(ROOT_PATH . 'shared/security.php');
requerirModulo('usuarios');
$csrfEstadoUsuario = asegurarTokenCsrf();

// 4. Cargar el menú (Usando ROOT_PATH para asegurar que lo encuentre)
include(ROOT_PATH . 'admin/menu.php');

// 5. Consulta de Datos

$sql = "SELECT id, usuario, nombre_completo, email, phone, area, rol, fecha_creacion, activo, modulos_permitidos FROM usuarios ORDER BY nombre_completo ASC";
$resultado = $conexion->query($sql);
$actorEsSuperAdmin = false;
$idActor = (int) ($_SESSION['id'] ?? 0);
if ($idActor > 0) {
    $stmtActor = $conexion->prepare('SELECT rol FROM usuarios WHERE id = ? LIMIT 1');
    if ($stmtActor) {
        $stmtActor->bind_param('i', $idActor);
        $stmtActor->execute();
        $actorEsSuperAdmin = strtolower(trim((string) ($stmtActor->get_result()->fetch_assoc()['rol'] ?? ''))) === 'superadmin';
        $stmtActor->close();
    }
}

// 6. Helper: iniciales para el avatar
function iniciales($nombre) {
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = '';
    foreach (array_slice($partes, 0, 2) as $p) {
        $ini .= mb_strtoupper(mb_substr($p, 0, 1));
    }
    return $ini ?: '?';
}

// 7. Helper: color de badge según rol
function colorRol($rol) {
    $rol = mb_strtolower($rol);
    $mapa = [
        'admin'         => ['bg' => '#fde8e8', 'text' => '#c81e1e'],
        'administrador' => ['bg' => '#fde8e8', 'text' => '#c81e1e'],
        'editor'        => ['bg' => '#e7f0ff', 'text' => '#1d4ed8'],
        'supervisor'    => ['bg' => '#fef3e0', 'text' => '#b45309'],
        'usuario'       => ['bg' => '#eef2f7', 'text' => '#475569'],
    ];
    return $mapa[$rol] ?? ['bg' => '#eef2f7', 'text' => '#475569'];
}
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__index.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__usuarios__index.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container-fluid">
  <div class="container-fluid">
    <h2 class="titulo">👥 Lista de Usuarios</h2>
    <div class="subtitulo">Administra las cuentas y roles del sistema.</div>
    <?php if (($_GET['msg'] ?? '') === 'success'): ?><div class="alert alert-success">Usuario actualizado.</div><?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'estado_actualizado'): ?><div class="alert alert-success">Estado del usuario actualizado.</div><?php endif; ?>
    <?php if (($_GET['msg'] ?? '') === 'estado_error'): ?><div class="alert alert-danger">No se pudo actualizar el usuario.</div><?php endif; ?>

    <div class="toolbar">
      <input id="filtro" type="text" placeholder="🔎 Buscar por nombre, usuario o área..." class="search-bar">
      <div class="filtros-estado" role="group" aria-label="Filtrar usuarios por estado">
        <button type="button" class="filtro-estado" data-status-filter="all" aria-pressed="false">Todos <span data-status-count="all">0</span></button>
        <button type="button" class="filtro-estado activo" data-status-filter="active" aria-pressed="true">Activos <span data-status-count="active">0</span></button>
        <button type="button" class="filtro-estado" data-status-filter="inactive" aria-pressed="false">Inactivos <span data-status-count="inactive">0</span></button>
      </div>
      <span class="contador" id="contador"></span>
    </div>

    <div class="table-container">
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Email</th>
            <th>Teléfono</th>
            <th>Área</th>
            <th>Rol</th>
            <th>Estado</th>
            <th>Fecha de creación</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while ($u = $resultado->fetch_assoc()): ?>
              <?php $color = colorRol($u['rol']); ?>
              <?php $usuarioActivo = (int) ($u['activo'] ?? 1) === 1; ?>
              <?php $modulosUsuarioJson = json_encode(parsearModulos($u['modulos_permitidos'] ?? null), JSON_UNESCAPED_UNICODE); ?>
              <tr data-user-status="<?= $usuarioActivo ? 'active' : 'inactive' ?>">
                <td><?= $u['id'] ?></td>
                <td>
                  <div class="usuario-cell">
                    <div class="avatar"><?= iniciales($u['nombre_completo']) ?></div>
                    <div>
                      <div class="nombre-principal"><?= htmlspecialchars($u['nombre_completo']) ?></div>
                      <div class="usuario-handle">@<?= htmlspecialchars($u['usuario']) ?></div>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($u['email'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($u['phone'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($u['area']) ?></td>
                <td>
                  <span class="badge-rol" style="background: <?= $color['bg'] ?>; color: <?= $color['text'] ?>;">
                    <?= ucfirst($u['rol']) ?>
                  </span>
                </td>
                <td><span class="badge-estado <?= $usuarioActivo ? 'estado-activo' : 'estado-inactivo' ?>"><?= $usuarioActivo ? 'Activo' : 'Inactivo' ?></span></td>
                <td><?= !empty($u['fecha_creacion']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($u['fecha_creacion'])), ENT_QUOTES, 'UTF-8') : 'No registrada' ?></td>
                <td class="acciones">
                  <?php if (strtolower(trim((string) ($_SESSION['rol'] ?? ''))) === 'admin'
                    && strtolower(trim((string) ($u['rol'] ?? ''))) === 'superadmin'): ?>
                    <span title="Solo el superadmin puede administrar esta cuenta">Protegido</span>
                  <?php else: ?>
                    <button type="button" class="btn-editar-estado" title="Editar usuario" aria-label="Editar usuario <?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                      data-bs-toggle="modal" data-bs-target="#modalEstadoUsuario"
                      data-user-id="<?= (int) $u['id'] ?>"
                      data-user-name="<?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>"
                      data-user-handle="<?= htmlspecialchars($u['usuario'], ENT_QUOTES, 'UTF-8') ?>"
                      data-user-email="<?= htmlspecialchars((string) ($u['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-user-phone="<?= htmlspecialchars((string) ($u['phone'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-user-area="<?= htmlspecialchars((string) ($u['area'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                      data-user-role="<?= htmlspecialchars((string) ($u['rol'] ?? 'usuario'), ENT_QUOTES, 'UTF-8') ?>"
                      data-user-active="<?= $usuarioActivo ? '1' : '0' ?>"
                      data-user-modules="<?= htmlspecialchars(is_string($modulosUsuarioJson) ? $modulosUsuarioJson : '[]', ENT_QUOTES, 'UTF-8') ?>">✏️</button>
                    <form class="form-eliminar" action="<?= BASE_URL ?>admin/usuarios/backend/eliminar_usuario.php" method="POST" style="display:inline;">
                      <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                      <button type="button" class="btn-eliminar" title="Eliminar" data-nombre="<?= htmlspecialchars($u['nombre_completo'], ENT_QUOTES, 'UTF-8') ?>">🗑️</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr>
              <td colspan="9">
                <div class="estado-vacio">
                  <span class="icono">📭</span>
                  No hay usuarios registrados.
                </div>
              </td>
            </tr>
          <?php endif; ?>
          <tr id="sin-resultados-filtro" hidden>
            <td colspan="9"><div class="estado-vacio">No hay usuarios para el filtro seleccionado.</div></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="modal fade" id="modalEstadoUsuario" tabindex="-1" aria-labelledby="modalEstadoUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <form action="<?= BASE_URL ?>admin/usuarios/backend/actualizar_usuario.php" method="POST">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="modalEstadoUsuarioLabel">Editar usuario</h5>
            <div class="small text-muted" id="modalEstadoUsuarioNombre"></div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfEstadoUsuario, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="id" id="modalEstadoUsuarioId">
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="modalUsuario">Usuario</label><input class="form-control" name="usuario" id="modalUsuario" required maxlength="50"></div>
            <div class="col-md-6"><label class="form-label" for="modalNombreCompleto">Nombre completo</label><input class="form-control" name="nomcom" id="modalNombreCompleto" required maxlength="100"></div>
            <div class="col-md-6"><label class="form-label" for="modalEmail">Correo electrónico</label><input class="form-control" type="email" name="email" id="modalEmail" maxlength="125"></div>
            <div class="col-md-6"><label class="form-label" for="modalTelefono">Teléfono</label><input class="form-control" type="text" name="phone" id="modalTelefono" maxlength="255"></div>
            <div class="col-md-6"><label class="form-label" for="modalArea">Área</label><input class="form-control" name="area" id="modalArea" required maxlength="100"></div>
            <div class="col-md-6"><label class="form-label" for="modalEstadoUsuarioActivo">Estado</label><select class="form-select" name="activo" id="modalEstadoUsuarioActivo" required><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
            <div class="col-md-6">
              <label class="form-label" for="modalRol">Rol</label>
              <?php if ($actorEsSuperAdmin): ?>
                <select class="form-select" name="rol" id="modalRol" required>
                  <option value="usuario">Usuario</option><option value="admin">Administrador</option><option value="superadmin">Superadministrador</option>
                </select>
              <?php else: ?>
                <input class="form-control" id="modalRolTexto" readonly>
                <input type="hidden" name="rol" id="modalRol">
              <?php endif; ?>
            </div>
            <div class="col-md-6"><label class="form-label" for="modalContrasena">Nueva contraseña</label><input class="form-control" type="password" name="contrasena" id="modalContrasena" autocomplete="new-password" placeholder="Vacío para conservar la actual"></div>
          </div>
          <?php if ($actorEsSuperAdmin): ?>
            <div class="mt-3" id="modalBloqueModulos" style="display:none; border:1px solid #dee2e6; border-radius:.6rem; padding:.9rem; background:#f8f9fa;">
              <label class="form-label fw-bold mb-2"><i class="fa-solid fa-shield-halved me-1"></i>Módulos permitidos</label>
              <div class="row g-2">
                <?php foreach (MODULOS_DISPONIBLES as $clave => $info): ?>
                  <?php $idModuloModal = 'modal_mod_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $clave); ?>
                  <div class="col-sm-6 col-lg-4"><div class="form-check"><input class="form-check-input modal-modulo-checkbox" type="checkbox" name="modulos[]" value="<?= htmlspecialchars($clave, ENT_QUOTES, 'UTF-8') ?>" id="<?= htmlspecialchars($idModuloModal, ENT_QUOTES, 'UTF-8') ?>"><label class="form-check-label" for="<?= htmlspecialchars($idModuloModal, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= htmlspecialchars($info['icon'], ENT_QUOTES, 'UTF-8') ?> me-1"></i><?= htmlspecialchars($info['label'], ENT_QUOTES, 'UTF-8') ?></label></div></div>
                <?php endforeach; ?>
              </div>
              <div class="form-text">No aplica al superadministrador, que siempre ve todos los módulos.</div>
            </div>
          <?php endif; ?>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  const filtro = document.getElementById('filtro');
  const contador = document.getElementById('contador');
  const filas = [...document.querySelectorAll('tbody tr[data-user-status]')];
  const botonesEstado = [...document.querySelectorAll('[data-status-filter]')];
  const mensajeSinResultados = document.getElementById('sin-resultados-filtro');
  let estadoSeleccionado = 'active';

  function actualizarFilas() {
    const term = filtro.value.trim().toLocaleLowerCase();
    let visibles = 0;
    const conteos = {all: filas.length, active: 0, inactive: 0};
    filas.forEach(tr => {
      const estado = tr.dataset.userStatus;
      conteos[estado] = (conteos[estado] || 0) + 1;
      const coincideEstado = estadoSeleccionado === 'all' || estado === estadoSeleccionado;
      const coincideTexto = tr.innerText.toLocaleLowerCase().includes(term);
      const mostrar = coincideEstado && coincideTexto;
      tr.style.display = mostrar ? '' : 'none';
      if (mostrar) visibles++;
    });
    botonesEstado.forEach(boton => {
      const seleccionado = boton.dataset.statusFilter === estadoSeleccionado;
      boton.classList.toggle('activo', seleccionado);
      boton.setAttribute('aria-pressed', seleccionado ? 'true' : 'false');
      const contadorEstado = boton.querySelector('[data-status-count]');
      if (contadorEstado) contadorEstado.textContent = conteos[boton.dataset.statusFilter] || 0;
    });
    if (mensajeSinResultados) mensajeSinResultados.hidden = !(filas.length > 0 && visibles === 0);
    contador.textContent = visibles + ' usuario' + (visibles === 1 ? '' : 's');
  }

  filtro.addEventListener('input', actualizarFilas);
  botonesEstado.forEach(boton => boton.addEventListener('click', () => {
    estadoSeleccionado = boton.dataset.statusFilter;
    actualizarFilas();
  }));

  actualizarFilas();

  const modalEstadoUsuario = document.getElementById('modalEstadoUsuario');
  modalEstadoUsuario.addEventListener('show.bs.modal', function (event) {
    const boton = event.relatedTarget;
    const userId = boton.dataset.userId;
    document.getElementById('modalEstadoUsuarioId').value = userId;
    document.getElementById('modalEstadoUsuarioNombre').textContent = boton.dataset.userName + ' (@' + boton.dataset.userHandle + ')';
    document.getElementById('modalUsuario').value = boton.dataset.userHandle;
    document.getElementById('modalNombreCompleto').value = boton.dataset.userName;
    document.getElementById('modalEmail').value = boton.dataset.userEmail;
    document.getElementById('modalTelefono').value = boton.dataset.userPhone;
    document.getElementById('modalArea').value = boton.dataset.userArea;
    document.getElementById('modalEstadoUsuarioActivo').value = boton.dataset.userActive;
    const rol = boton.dataset.userRole;
    const selectorRol = document.getElementById('modalRol');
    if (selectorRol.tagName === 'SELECT') selectorRol.value = rol;
    else {
      selectorRol.value = rol;
      document.getElementById('modalRolTexto').value = rol.charAt(0).toUpperCase() + rol.slice(1);
    }
    const bloqueModulos = document.getElementById('modalBloqueModulos');
    if (bloqueModulos) {
      let modulos = [];
      try { modulos = JSON.parse(boton.dataset.userModules || '[]'); } catch (error) { modulos = []; }
      document.querySelectorAll('.modal-modulo-checkbox').forEach(checkbox => { checkbox.checked = modulos.includes(checkbox.value); });
      bloqueModulos.style.display = rol === 'admin' ? 'block' : 'none';
    }
    document.getElementById('modalContrasena').value = '';
  });
  const selectorRolModal = document.getElementById('modalRol');
  if (selectorRolModal.tagName === 'SELECT') selectorRolModal.addEventListener('change', function () {
    const bloqueModulos = document.getElementById('modalBloqueModulos');
    if (bloqueModulos) bloqueModulos.style.display = this.value === 'admin' ? 'block' : 'none';
  });

  // Confirmación con SweetAlert2 en vez del confirm() nativo
  document.querySelectorAll('.btn-eliminar').forEach(btn => {
    btn.addEventListener('click', function () {
      const nombre = this.dataset.nombre;
      Swal.fire({
        title: '¿Eliminar usuario?',
        text: `Se eliminará a "${nombre}" de forma permanente.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d'
      }).then((result) => {
        if (result.isConfirmed) {
          this.closest('form').submit();
        }
      });
    });
  });
</script>

<?php
if (isset($conexion)) {
    $conexion->close();
}
?>
