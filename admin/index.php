<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
require_once ROOT_PATH . 'backend/conexion.php';
$conexion->set_charset('utf8mb4');

$dashboardGroups = [
    'Personal' => ['usuarios', 'asistencias'],
    'Activos y operación' => ['inventario', 'lineas', 'mantenimiento', 'licencias'],
    'Seguimiento' => ['auditorias', 'reportes', 'calificaciones'],
    'Herramientas' => ['cotizaciones', 'firmas', 'avisos', 'mail', 'notificaciones', 'integraciones']
];
$knownKeys = [];
foreach ($dashboardGroups as $keys) $knownKeys = array_merge($knownKeys, $keys);
foreach (array_keys(MODULOS_DISPONIBLES) as $key) {
    if ($key !== 'tickets' && !in_array($key, $knownKeys, true)) $dashboardGroups['Más herramientas'][] = $key;
}
$moduleDescriptions = [
    'usuarios' => 'Cuentas, empleados y entrega de recursos.',
    'asistencias' => 'Consulta y control de asistencias.',
    'inventario' => 'Equipos, existencias y movimientos.',
    'lineas' => 'Líneas telefónicas y asignaciones.',
    'mantenimiento' => 'Planes, programación e historial.',
    'licencias' => 'Licencias, servicios y fechas de renovación.',
    'auditorias' => 'Programa y resultados de auditoría.',
    'reportes' => 'Consultas y exportación de información.',
    'calificaciones' => 'Respuestas y satisfacción de usuarios.',
    'cotizaciones' => 'Elaboración y consulta de cotizaciones.',
    'firmas' => 'Generación y envío de firmas de correo.',
    'avisos' => 'Publicación de avisos internos.',
    'mail' => 'Consulta y organización del correo.',
    'notificaciones' => 'Avisos y notificaciones del sistema.'
];
$ticketBacklog = 0;
$ticketSummaryAvailable = false;
if (tieneModulo('tickets')) {
    $ticketResult = $conexion->query("SELECT COUNT(*) AS total FROM tickets WHERE estatus IN ('Pendiente','En proceso')");
    if ($ticketResult) {
        $ticketBacklog = (int) ($ticketResult->fetch_assoc()['total'] ?? 0);
        $ticketSummaryAvailable = true;
    }
}
require_once ROOT_PATH . 'admin/menu.php';
?>
<link rel="stylesheet" href="<?= BASE_URL ?>css/pages/admin__index.css?v=<?= filemtime(ROOT_PATH . 'css/pages/admin__index.css') ?>">
<main class="home-dashboard" id="mainContent">
    <header class="home-header">
        <div>
            <p class="home-eyebrow">Panel de administración</p>
            <h1>¿Qué necesitas hacer?</h1>
            <p>Accede a tus herramientas por área. Cada módulo conserva sus funciones y permisos.</p>
        </div>
        <?php if (tieneModulo('reportes') && isset(MODULOS_DISPONIBLES['reportes'])): ?>
            <a class="home-report-link" href="<?= htmlspecialchars(BASE_URL . trim(MODULOS_DISPONIBLES['reportes']['ruta'], '/'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-chart-pie"></i> Abrir reportes</a>
        <?php endif; ?>
    </header>

    <?php foreach ($dashboardGroups as $groupName => $keys): ?>
        <?php
            $visibleModules = array_values(array_filter($keys, static fn($key) => isset(MODULOS_DISPONIBLES[$key]) && tieneModulo($key)));
            if (!$visibleModules) continue;
        ?>
        <section class="home-section" aria-label="<?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?>">
            <div class="home-section-heading"><h2><?= htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8') ?></h2><span><?= count($visibleModules) ?> <?= count($visibleModules) === 1 ? 'módulo' : 'módulos' ?></span></div>
            <div class="home-module-grid">
                <?php foreach ($visibleModules as $key): ?>
                    <?php
                        $module = MODULOS_DISPONIBLES[$key];
                        $route = trim((string) ($module['ruta'] ?? ''), '/');
                        if ($route === '') continue;
                        $label = (string) ($module['label'] ?? $key);
                    ?>
                    <a class="home-module-card" href="<?= htmlspecialchars(BASE_URL . $route, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="home-module-icon"><i class="fa-solid <?= htmlspecialchars($module['icon'] ?? 'fa-folder', ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i></span>
                        <span class="home-module-copy"><strong><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars($moduleDescriptions[$key] ?? 'Abrir este módulo.', ENT_QUOTES, 'UTF-8') ?></small></span>
                        <i class="fa-solid fa-arrow-right home-module-arrow" aria-hidden="true"></i>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>

    <?php if (tieneModulo('tickets') && isset(MODULOS_DISPONIBLES['tickets'])): ?>
        <section class="home-tickets">
            <div class="home-tickets-icon"><i class="fa-solid fa-ticket" aria-hidden="true"></i></div>
            <div class="home-tickets-copy"><h2>Tickets</h2><p>El módulo sigue disponible para dar seguimiento cuando lo necesites.</p></div>
            <?php if ($ticketSummaryAvailable): ?><div class="home-ticket-count"><strong><?= $ticketBacklog ?></strong><span>pendientes o en proceso</span></div><?php endif; ?>
            <a class="home-ticket-link" href="<?= htmlspecialchars(BASE_URL . trim(MODULOS_DISPONIBLES['tickets']['ruta'], '/'), ENT_QUOTES, 'UTF-8') ?>">Abrir tickets <i class="fa-solid fa-arrow-right"></i></a>
        </section>
    <?php endif; ?>
</main>
