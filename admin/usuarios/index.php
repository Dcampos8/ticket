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
requerirModulo('usuarios');

// 4. Cargar el menú (Usando ROOT_PATH para asegurar que lo encuentre)
include(ROOT_PATH . 'admin/menu.php');

// 5. Consulta de Datos

$sql = "SELECT id, usuario, nombre_completo, email, phone, area, rol FROM usuarios ORDER BY nombre_completo ASC";
$resultado = $conexion->query($sql);

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

    <div class="toolbar">
      <input id="filtro" type="text" placeholder="🔎 Buscar por nombre, usuario o área..." class="search-bar">
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
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($resultado && $resultado->num_rows > 0): ?>
            <?php while ($u = $resultado->fetch_assoc()): ?>
              <?php $color = colorRol($u['rol']); ?>
              <tr>
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
                <td class="acciones">
                  <?php if (strtolower(trim((string) ($_SESSION['rol'] ?? ''))) === 'admin'
                    && strtolower(trim((string) ($u['rol'] ?? ''))) === 'superadmin'): ?>
                    <span title="Solo el superadmin puede administrar esta cuenta">Protegido</span>
                  <?php else: ?>
                    <a href="editar_usuario.php?id=<?= (int) $u['id'] ?>" title="Editar">✏️</a>
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
              <td colspan="7">
                <div class="estado-vacio">
                  <span class="icono">📭</span>
                  No hay usuarios registrados.
                </div>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
  const filtro = document.getElementById('filtro');
  const contador = document.getElementById('contador');
  const filas = document.querySelectorAll('tbody tr');

  function actualizarContador() {
    const visibles = [...filas].filter(tr => tr.style.display !== 'none').length;
    contador.textContent = visibles + ' usuario' + (visibles === 1 ? '' : 's');
  }

  filtro.addEventListener('input', function () {
    const term = this.value.toLowerCase();
    filas.forEach(tr => {
      const contenido = tr.innerText.toLowerCase();
      tr.style.display = contenido.includes(term) ? '' : 'none';
    });
    actualizarContador();
  });

  actualizarContador();

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
