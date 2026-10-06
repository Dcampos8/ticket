<?php
// El menú también puede cargarse desde subcarpetas; asegurar configuración base.
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config.php';
}

// Helper de roles/módulos (superadmin ve todo; admin solo sus módulos)
require_once ROOT_PATH . 'shared/permisos.php';

include_once __DIR__ . '/header.php';

// Librerías JavaScript compartidas del panel: mantener una sola carga aquí.
if (!defined('ADMIN_SHARED_JS_LOADED')):
    define('ADMIN_SHARED_JS_LOADED', true);
?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
endif;

// 1. Captura la URL actual limpia de parámetros GET
$currentUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 2. Definimos qué páginas pertenecen a cada Dropdown para centralizar el control
$dropdowns = [
    'lineas'    => ['admin/lineas/index.php', 'admin/lineas/responsiva.php'],
    'mantto'    => ['admin/mantenimiento/plan_mantenimiento.php', 'admin/mantenimiento/programacion_mantenimientos.php', 'admin/mantenimiento/historial_mantenimientos.php'],
    'tickets'   => ['admin/tickets/'],
    'usuarios'  => ['admin/usuarios/', 'admin/employees.php', 'admin/add_empleado.php'],
    'perfil'    => ['admin/usuarios/perfil.php', 'admin/avisos/'],
    'licencias' => ['admin/vigencias/'],
    'config'    => ['admin/correo/']
];

// Los módulos visibles se obtienen del catálogo de la base de datos.
// Solo los submenús se mantienen como estructura de navegación específica.
$submenusModulos = [
    'lineas' => [
        ['ruta' => 'admin/lineas/', 'icon' => 'fa-list', 'label' => 'Gestión']
    ],
    'mantenimiento' => [
        ['ruta' => 'admin/mantenimiento/plan_mantenimiento.php', 'icon' => 'fa-calendar-alt', 'label' => 'Plan Anual'],
        ['ruta' => 'admin/mantenimiento/programacion_mantenimientos.php', 'icon' => 'fa-calendar-check', 'label' => 'Programación'],
        ['ruta' => 'admin/mantenimiento/historial_mantenimientos.php', 'icon' => 'fa-history', 'label' => 'Historial']
    ],
    'tickets' => [
        ['ruta' => 'admin/tickets/', 'icon' => 'fa-stream', 'label' => 'Ver Todos'],
        ['ruta' => 'admin/tickets/crear_ticket_admin.php', 'icon' => 'fa-plus-circle', 'label' => 'Crear Nuevo'],
        ['ruta' => 'admin/tickets/permisos_tickets.php', 'icon' => 'fa-user-lock', 'label' => 'Permisos']
    ],
    'usuarios' => [
        ['ruta' => 'admin/usuarios/entregas/entrega_recursos.php', 'icon' => 'fa-exchange-alt', 'label' => 'Entrada/Salida Puesto'],
        ['ruta' => 'admin/usuarios/entregas/h_entregas_datos.php', 'icon' => 'fa-folder-open', 'label' => 'Historial Entrada/Salida'],
        ['divider' => true],
        ['ruta' => 'admin/usuarios/index.php', 'icon' => 'fa-users', 'label' => 'Listado'],
        ['ruta' => 'admin/usuarios/crear_usuario.php', 'icon' => 'fa-user-plus', 'label' => 'Nuevo Usuario'],
        ['ruta' => 'admin/usuarios/subir_usuarios.php', 'icon' => 'fa-file-upload', 'label' => 'Carga Masiva'],
        ['superadmin' => true, 'ruta' => 'admin/usuarios/permisos_modulos.php', 'icon' => 'fa-shield-halved', 'label' => 'Permisos de Módulos'],
        ['divider' => true],
        ['ruta' => 'admin/usuarios/employees/', 'icon' => 'fa-id-card', 'label' => 'Empleados'],
        ['ruta' => 'admin/usuarios/employees/add_empleado.php', 'icon' => 'fa-user-tag', 'label' => 'Alta Empleado'],
        ['ruta' => 'admin/usuarios/employees/subir_employees.php', 'icon' => 'fa-file-csv', 'label' => 'Alta Empleados CSV']
    ]
];

/**
 * Función helper optimizada.
 */
function checkActive($target, $currentUri, $dropdowns = []) {
    if (array_key_exists($target, $dropdowns)) {
        foreach ($dropdowns[$target] as $path) {
            if (strpos($currentUri, $path) !== false) return 'active';
        }
        return '';
    }

    if ($target === 'admin/') {
        return (substr($currentUri, -7) === '/admin/' || substr($currentUri, -16) === '/admin/index.php') ? 'active' : '';
    }

    return (strpos($currentUri, $target) !== false) ? 'active' : '';
}
?>

<!-- El estilo de esta barra de navegación vive en css/theme.css y css/menu.css -->

<nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar">
  <div class="container-fluid px-4">

    <!-- Logo -->
    <a class="navbar-brand me-4" href="<?= BASE_URL ?>admin/">
      <img src="<?= BASE_URL ?>img/Camion%20Cont%20Der.png" height="36" class="logo-animate" alt="Logo">
    </a>

    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar">
      <span class="fas fa-bars"></span>
    </button>

    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">

        <li class="nav-item">
          <a class="nav-link <?= checkActive('admin/', $currentUri) ?>" href="<?= BASE_URL ?>admin/">
            <i class="fas fa-chart-line"></i> <span>Dashboard</span>
          </a>
        </li>

        <?php foreach (MODULOS_DISPONIBLES as $clave => $modulo): ?>
          <?php if (!tieneModulo($clave)) continue; ?>
          <?php
            $submenu = $submenusModulos[$clave] ?? null;
            $rutaModulo = trim((string) ($modulo['ruta'] ?? ''), '/');
            $urlModulo = BASE_URL . $rutaModulo;
            $etiquetaModulo = $modulo['label'] ?? $clave;
          ?>
          <?php if ($submenu): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= checkActive($clave, $currentUri, $dropdowns) ?>" href="#" data-bs-toggle="dropdown">
            <i class="fa-solid <?= htmlspecialchars($modulo['icon'] ?? 'fa-folder') ?>"></i> <span><?= htmlspecialchars($etiquetaModulo) ?></span>
          </a>
          <ul class="dropdown-menu shadow-lg">
            <?php foreach ($submenu as $item): ?>
              <?php if (!empty($item['divider'])): ?>
            <li><hr class="dropdown-divider"></li>
              <?php elseif (empty($item['superadmin']) || esSuperAdmin()): ?>
            <li><a class="dropdown-item" href="<?= BASE_URL . htmlspecialchars($item['ruta']) ?>"><i class="fa-solid <?= htmlspecialchars($item['icon']) ?>"></i> <?= htmlspecialchars($item['label']) ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ul>
        </li>
          <?php else: ?>
        <li class="nav-item">
          <a class="nav-link <?= checkActive($rutaModulo, $currentUri) ?>" href="<?= htmlspecialchars($urlModulo) ?>">
            <i class="fa-solid <?= htmlspecialchars($modulo['icon'] ?? 'fa-folder') ?>"></i> <span><?= htmlspecialchars($etiquetaModulo) ?></span>
            <?php if ($clave === 'calificaciones'):
              $badge_encuestas = 0;
              if (isset($conexion)) {
                  $res_badge = mysqli_query($conexion, "SELECT COUNT(*) AS c FROM encuestas_satisfaccion WHERE vista_admin = 0");
                  if ($res_badge) $badge_encuestas = (int) mysqli_fetch_assoc($res_badge)['c'];
              }
              if ($badge_encuestas > 0): ?>
                <span class="badge rounded-pill bg-danger ms-1"><?= $badge_encuestas ?></span>
              <?php endif; ?>
            <?php endif; ?>
          </a>
        </li>
          <?php endif; ?>
        <?php endforeach; ?>

      </ul>

      <!-- Lado Derecho: Usuario -->
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link user-profile-pill dropdown-toggle d-flex align-items-center <?= checkActive('perfil', $currentUri, $dropdowns) ?>" href="#" data-bs-toggle="dropdown" title="Cuenta: <?= htmlspecialchars($_SESSION['usuario'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?>" aria-label="Cuenta de usuario">
            <div class="user-avatar-small me-2">
              <i class="fas fa-user"></i>
            </div>
            <span><?= $_SESSION['usuario'] ?? 'Admin' ?></span>
            <?php if (esSuperAdmin()): ?>
              <span class="badge bg-danger ms-2" style="font-size:0.65rem;">SUPERADMIN</span>
            <?php endif; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/usuarios/perfil.php"><i class="fas fa-id-badge"></i> Mi Perfil</a></li>
            <?php if (tieneModulo('avisos')): ?>
            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/avisos/gestionar_aviso.php"><i class="fas fa-bullhorn"></i> Gestionar Avisos</a></li>
            <?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-bold" href="<?= BASE_URL ?>core/logout.php"><i class="fas fa-power-off"></i> Salir</a></li>
          </ul>
        </li>
      </ul>

    </div>
  </div>
</nav>

<?php require_once ROOT_PATH . 'shared/chat_widget.php'; ?>
