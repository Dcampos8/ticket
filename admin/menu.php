<?php
// El menú puede cargarse desde cualquier módulo.
if (!defined('ROOT_PATH')) {
    require_once __DIR__ . '/../config.php';
}
require_once ROOT_PATH . 'shared/permisos.php';
include_once __DIR__ . '/header.php';

$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$assetsPath = trim((string) (parse_url(BASE_URL, PHP_URL_PATH) ?: ''), '/');
$adminPath = ($assetsPath !== '' ? '/' . $assetsPath : '') . '/admin/';
$currentAdminPath = strpos($currentUri, $adminPath) === 0 ? substr($currentUri, strlen($adminPath)) : '';
$isReportPage = strpos($currentAdminPath, 'reportes/') === 0;
$isUserTablePage = in_array($currentAdminPath, ['usuarios/index.php', 'usuarios/employees/index.php'], true);
$isCalendarPage = $currentAdminPath === 'mantenimiento/programacion_mantenimientos.php';
$isTicketCreatePage = $currentAdminPath === 'tickets/crear_ticket_admin.php';
$isUserListPage = $currentAdminPath === 'usuarios/index.php';

if (!defined('ADMIN_SHARED_JS_LOADED')):
    define('ADMIN_SHARED_JS_LOADED', true);
?>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if ($isReportPage || $isUserTablePage): ?>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<?php endif; ?>
<?php if ($isReportPage): ?>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<?php endif; ?>
<?php if ($isCalendarPage): ?><script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script><?php endif; ?>
<?php if ($isUserListPage || $isTicketCreatePage): ?><script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><?php endif; ?>
<?php endif; ?>
<?php
$submenusModulos = [
    'mantenimiento' => [
        ['ruta' => 'admin/mantenimiento/programacion_mantenimientos.php', 'icon' => 'fa-calendar-check', 'label' => 'Programación'],
        ['ruta' => 'admin/mantenimiento/historial_mantenimientos.php', 'icon' => 'fa-history', 'label' => 'Historial']
    ],
    'tickets' => [
        ['ruta' => 'admin/tickets/', 'icon' => 'fa-stream', 'label' => 'Ver todos'],
        ['ruta' => 'admin/tickets/crear_ticket_admin.php', 'icon' => 'fa-plus-circle', 'label' => 'Crear nuevo'],
        ['ruta' => 'admin/tickets/conocimiento.php', 'icon' => 'fa-book-open', 'label' => 'Base de conocimiento'],
        ['ruta' => 'admin/tickets/permisos_tickets.php', 'icon' => 'fa-user-lock', 'label' => 'Permisos']
    ],
    'usuarios' => [
        ['ruta' => 'admin/usuarios/entregas/entrega_recursos.php', 'icon' => 'fa-exchange-alt', 'label' => 'Entrada/salida de recursos'],
        ['ruta' => 'admin/usuarios/entregas/h_entregas_datos.php', 'icon' => 'fa-folder-open', 'label' => 'Historial de recursos'],
        ['ruta' => 'admin/usuarios/crear_usuario.php', 'icon' => 'fa-user-plus', 'label' => 'Alta de usuario'],
        ['ruta' => 'admin/usuarios/subir_usuarios.php', 'icon' => 'fa-file-upload', 'label' => 'Carga de usuarios'],
        ['superadmin' => true, 'ruta' => 'admin/usuarios/permisos_modulos.php', 'icon' => 'fa-shield-halved', 'label' => 'Permisos de módulos']
    ]
];

$navGroups = [
    'Personal' => ['usuarios', 'asistencias'],
    'Activos y operación' => ['inventario', 'lineas', 'mantenimiento'],
    'Seguimiento' => ['auditorias', 'reportes', 'calificaciones','licencias'],
    'Herramientas' => ['cotizaciones', 'firmas', 'avisos', 'mail', 'notificaciones', 'integraciones']
];
$navGroupIcons = [
    'Personal' => 'fa-users',
    'Activos y operación' => 'fa-boxes-stacked',
    'Seguimiento' => 'fa-chart-line',
    'Herramientas' => 'fa-screwdriver-wrench',
    'Más herramientas' => 'fa-ellipsis'
];
$navGroupLabels = ['Activos y operación' => 'Operación'];
$groupedKeys = [];
foreach ($navGroups as $groupKeys) {
    $groupedKeys = array_merge($groupedKeys, $groupKeys);
}
foreach (array_keys(MODULOS_DISPONIBLES) as $moduleKey) {
    if ($moduleKey !== 'tickets' && !in_array($moduleKey, $groupedKeys, true)) {
        $navGroups['Más herramientas'][] = $moduleKey;
    }
}

$dropdownPaths = ['perfil' => ['usuarios/perfil.php']];
foreach ($navGroups as $groupLabel => $groupKeys) {
    foreach ($groupKeys as $moduleKey) {
        if (!isset(MODULOS_DISPONIBLES[$moduleKey])) continue;
        $moduleRoute = trim((string) (MODULOS_DISPONIBLES[$moduleKey]['ruta'] ?? ''), '/');
        if ($moduleRoute !== '') $dropdownPaths[$groupLabel][] = preg_replace('#^admin/#', '', $moduleRoute);
        foreach ($submenusModulos[$moduleKey] ?? [] as $item) {
            $dropdownPaths[$groupLabel][] = preg_replace('#^admin/#', '', trim($item['ruta'], '/'));
        }
    }
}
function checkActive($target, $currentPath, $dropdowns = []) {
    if (isset($dropdowns[$target])) {
        foreach ($dropdowns[$target] as $path) {
            if ($path !== '' && strpos($currentPath, $path) !== false) return 'active';
        }
        return '';
    }
    if ($target === 'admin/') {
        return ($currentPath === '' || $currentPath === 'index.php') ? 'active' : '';
    }
    return ($target !== '' && strpos($currentPath, $target) !== false) ? 'active' : '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top custom-navbar">
  <div class="container-fluid px-4">
    <a class="navbar-brand me-4" href="<?= BASE_URL ?>admin/">
      <img src="<?= BASE_URL ?>img/Camion%20Cont%20Der.png" height="36" class="logo-animate" alt="Transportes Valadez">
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Abrir navegación">
      <span class="fas fa-bars"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNavbar">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <li class="nav-item"><a class="nav-link <?= checkActive('admin/', $currentAdminPath) ?>" href="<?= BASE_URL ?>admin/"><i class="fas fa-house"></i> <span>Inicio</span></a></li>
        <?php foreach ($navGroups as $groupLabel => $groupKeys): ?>
          <?php
            $visibleKeys = array_values(array_filter($groupKeys, static fn($key) => isset(MODULOS_DISPONIBLES[$key]) && tieneModulo($key)));
            if (!$visibleKeys) continue;
          ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= checkActive($groupLabel, $currentAdminPath, $dropdownPaths) ?>" href="#" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid <?= htmlspecialchars($navGroupIcons[$groupLabel] ?? 'fa-folder-open', ENT_QUOTES, 'UTF-8') ?>"></i> <span><?= htmlspecialchars($navGroupLabels[$groupLabel] ?? $groupLabel, ENT_QUOTES, 'UTF-8') ?></span></a>
            <ul class="dropdown-menu shadow-lg">
              <?php foreach ($visibleKeys as $moduleKey): ?>
                <?php
                  $module = MODULOS_DISPONIBLES[$moduleKey];
                  $moduleLabel = (string) ($module['label'] ?? $moduleKey);
                  $moduleRoute = trim((string) ($module['ruta'] ?? ''), '/');
                  if ($moduleKey === 'lineas') $moduleRoute = 'admin/lineas/';
                  $moduleUrl = BASE_URL . $moduleRoute;
                ?>
                <?php if ($moduleRoute !== ''): ?>
                  <?php
                    $unreadSurveys = 0;
                    if ($moduleKey === 'calificaciones' && isset($conexion)) {
                        $surveyCount = $conexion->query("SELECT COUNT(*) AS total FROM encuestas_satisfaccion WHERE vista_admin = 0");
                        if ($surveyCount) $unreadSurveys = (int) ($surveyCount->fetch_assoc()['total'] ?? 0);
                    }
                  ?>
                  <li><a class="dropdown-item" href="<?= htmlspecialchars($moduleUrl, ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= htmlspecialchars($module['icon'] ?? 'fa-folder', ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($moduleLabel, ENT_QUOTES, 'UTF-8') ?><?php if ($unreadSurveys > 0): ?> <span class="badge rounded-pill bg-danger ms-1"><?= $unreadSurveys ?></span><?php endif; ?></a></li>
                <?php endif; ?>
                <?php foreach ($submenusModulos[$moduleKey] ?? [] as $item): ?>
                  <?php if (!empty($item['superadmin']) && !esSuperAdmin()) continue; ?>
                  <li><a class="dropdown-item" href="<?= BASE_URL . htmlspecialchars($item['ruta'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
                <?php if ($moduleKey !== end($visibleKeys) && !empty($submenusModulos[$moduleKey])): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
              <?php endforeach; ?>
            </ul>
          </li>
        <?php endforeach; ?>
        <?php if (isset(MODULOS_DISPONIBLES['tickets']) && tieneModulo('tickets')): ?>
          <?php $ticketItems = $submenusModulos['tickets']; ?>
          <li class="nav-item dropdown tickets-nav-item">
            <a class="nav-link dropdown-toggle <?= strpos($currentAdminPath, 'tickets/') === 0 ? 'active' : '' ?>" href="#" data-bs-toggle="dropdown" aria-expanded="false"><i class="fa-solid fa-ticket"></i> <span>Tickets</span></a>
            <ul class="dropdown-menu shadow-lg">
              <?php foreach ($ticketItems as $item): ?><li><a class="dropdown-item" href="<?= BASE_URL . htmlspecialchars($item['ruta'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?> me-2"></i><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></a></li><?php endforeach; ?>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item dropdown">
          <a class="nav-link user-profile-pill dropdown-toggle d-flex align-items-center" href="#" data-bs-toggle="dropdown" title="Cuenta de usuario" aria-label="Cuenta de usuario">
            <div class="user-avatar-small me-2"><i class="fas fa-user"></i></div>
            <span><?= htmlspecialchars($_SESSION['usuario'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></span>
            <?php if (esSuperAdmin()): ?><span class="badge bg-danger ms-2" style="font-size:.65rem">SUPERADMIN</span><?php endif; ?>
          </a>
          <ul class="dropdown-menu dropdown-menu-end shadow-lg">
            <li><a class="dropdown-item" href="<?= BASE_URL ?>admin/usuarios/perfil.php"><i class="fas fa-id-badge me-2"></i>Mi perfil</a></li>
            <?php if (tieneModulo('avisos')): ?><li><a class="dropdown-item" href="<?= BASE_URL ?>admin/avisos/gestionar_aviso.php"><i class="fas fa-bullhorn me-2"></i>Gestionar avisos</a></li><?php endif; ?>
            <?php if (esSuperAdmin()): ?><li><a class="dropdown-item" href="<?= BASE_URL ?>admin/integraciones/"><i class="fas fa-plug me-2"></i>Conexiones e integraciones</a></li><?php endif; ?>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger fw-bold" href="<?= BASE_URL ?>core/logout.php"><i class="fas fa-power-off me-2"></i>Salir</a></li>
          </ul>
        </li>
      </ul>
    </div>
  </div>
</nav>
<?php require_once ROOT_PATH . 'shared/chat_widget.php'; ?>
