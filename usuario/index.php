<?php
// 1. Sesión y Seguridad
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    header("Location: ../login.php");
    exit();
}

// 2. Conexión (config.php define BASE_URL/ROOT_PATH y ya incluye conexion.php)
require_once '../config.php';

// 3. Lógica de negocio (Avisos)
$aviso = null;
$query_aviso = "SELECT * FROM avisos_sistema WHERE activo = 1 ORDER BY id DESC LIMIT 1";
$resultado_aviso = $conexion->query($query_aviso);
if ($resultado_aviso && $resultado_aviso->num_rows > 0) {
    $aviso = $resultado_aviso->fetch_assoc();
}
$tituloAviso = trim((string) ($aviso['titulo'] ?? ''));
$lineasAviso = preg_split('/\R/u', trim((string) ($aviso['mensaje'] ?? ''))) ?: [];
if ($tituloAviso !== '' && isset($lineasAviso[0]) && mb_strtolower(trim($lineasAviso[0]), 'UTF-8') === mb_strtolower($tituloAviso, 'UTF-8')) {
    array_shift($lineasAviso);
    while (isset($lineasAviso[0]) && trim($lineasAviso[0]) === '') array_shift($lineasAviso);
}
$mensajeAviso = trim(implode("\n", $lineasAviso));
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Usuario</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- Tema global del rediseño (retinta Bootstrap a los colores de marca) -->
    <link rel="stylesheet" href="../css/theme.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../css/redesign.css?v=<?= time() ?>">
    <link rel="stylesheet" href="../css/brand.css?v=<?= filemtime(__DIR__ . '/../css/brand.css') ?>">
    <link rel="stylesheet" href="../css/pages/usuario__menu.css?v=<?= filemtime(__DIR__ . '/../css/pages/usuario__menu.css') ?>">
    <link rel="stylesheet" href="../css/pages/usuario__index.css?v=<?= filemtime(__DIR__ . '/../css/pages/usuario__index.css') ?>">
    <link rel="icon" type="image/svg+xml" href="../img/favicon.svg?v=2">
    <link rel="manifest" href="../manifest.webmanifest?v=2">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <div class="d-flex">
        <?php include('menu.php'); ?>
        
       <main class="flex-grow-1 p-4" id="pageContent" style="min-height: 100vh;">
    <section class="user-welcome">
        <div><span class="user-eyebrow">PORTAL DE SISTEMAS</span><h1>Hola, <?= htmlspecialchars(explode(' ', trim((string) ($_SESSION['nombre_completo'] ?? 'Usuario')))[0] ?: 'usuario') ?></h1><p>¿Qué necesitas resolver hoy?</p></div>
        <div class="welcome-mark" aria-hidden="true"><i class="fa-solid fa-headset"></i></div>
    </section>

    <?php if ($aviso): ?>
        <?php $coloresPermitidos = ['primary', 'success', 'warning', 'danger', 'info']; $colorAviso = in_array($aviso['color_alerta'], $coloresPermitidos, true) ? $aviso['color_alerta'] : 'primary'; ?>
        <section class="user-announcement announcement-<?= htmlspecialchars($colorAviso) ?>" role="status"><span class="announcement-icon"><i class="fa-solid fa-bullhorn"></i></span><div><span class="user-eyebrow">AVISO DEL SISTEMA</span><h2><?= htmlspecialchars($tituloAviso) ?></h2><p><?= nl2br(htmlspecialchars($mensajeAviso)) ?></p></div></section>
    <?php endif; ?>

    <section class="user-actions-section">
        <div class="user-section-heading"><div><span class="user-eyebrow">ACCESOS RÁPIDOS</span><h2>¿Qué quieres hacer?</h2></div></div>
        <div class="user-action-grid">
            <a class="user-action-primary" href="#" data-page="ticket/crear_ticket.php"><span class="user-action-icon"><i class="fa-solid fa-plus"></i></span><span><strong>Crear un ticket</strong><small>Reporta un problema o solicita ayuda</small></span><i class="fa-solid fa-arrow-right user-action-arrow"></i></a>
            <a class="user-action-link" href="#" data-page="ticket/mis_tickets.php"><span class="user-action-icon"><i class="fa-solid fa-ticket"></i></span><span><strong>Seguir mis tickets</strong><small>Consulta respuestas y avances</small></span><i class="fa-solid fa-arrow-right user-action-arrow"></i></a>
            <a class="user-action-link" href="#" data-page="ticket/ayuda.php"><span class="user-action-icon"><i class="fa-solid fa-book-open"></i></span><span><strong>Centro de ayuda</strong><small>Encuentra guías y soluciones rápidas</small></span><i class="fa-solid fa-arrow-right user-action-arrow"></i></a>
        </div>
    </section>

    <?php if (!$aviso): ?><p class="user-no-announcements"><i class="fa-regular fa-circle-check"></i> No hay avisos nuevos del sistema.</p><?php endif; ?>
    <details class="user-policy"><summary><i class="fa-solid fa-shield-halved"></i><span><strong>Política de uso de Sistemas</strong><small>Consulta los lineamientos de seguridad y soporte</small></span><i class="fa-solid fa-chevron-down policy-chevron"></i></summary>
        <div class="policy-content"><p><strong>Equipos y sistemas.</strong> Utiliza los recursos de la empresa para fines laborales. No instales software sin autorización.</p><p><strong>Seguridad.</strong> Mantén tus contraseñas confidenciales y no compartas credenciales. Maneja la información de forma segura.</p><p><strong>Soporte.</strong> Reporta fallas y solicitudes por los canales oficiales. No realices cambios técnicos sin autorización.</p><p><strong>Correo e internet.</strong> Utiliza estos servicios de forma responsable y evita contenido que pueda poner en riesgo la seguridad de la empresa.</p><p><strong>Resguardo.</strong> Cuida tus equipos y reporta de inmediato cualquier daño, pérdida o robo.</p><p><strong>Incumplimiento.</strong> El uso inadecuado puede ocasionar restricciones de acceso o las medidas administrativas que correspondan.</p><p><strong>Vigencia.</strong> Estos lineamientos pueden actualizarse cuando cambien las necesidades de seguridad u operación.</p></div>
    </details>
</main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include('encuesta_modal.php'); ?>
    <?php require_once ROOT_PATH . 'shared/chat_widget.php'; ?>
    <script>
        window.RUTA_BACKEND_ENCUESTAS = '../backend/';
        iniciarEncuestas('../');
    </script>
</body>
</html>

<?php
// Cerramos conexión al final
$conexion->close();
?>
