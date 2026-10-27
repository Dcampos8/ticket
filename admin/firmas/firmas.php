<?php

require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('firmas');
if (!esAdminOSuperior()) {
    http_response_code(403);
    exit('No autorizado');
}
$csrfToken = asegurarTokenCsrf();
require_once ROOT_PATH . 'admin/menu.php';

$ok = isset($_GET['ok']) && $_GET['ok'] === '1';
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__firmas__firmas.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__firmas__firmas.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<main class="container firmas-page">
    <header class="firmas-header">
        <div>
            <p class="firmas-eyebrow">Recursos de correo</p>
            <h1>Generar firma</h1>
            <p class="firmas-intro">Captura los datos de la persona y enviaremos su firma directamente a su correo. No necesita tener una cuenta en la plataforma.</p>
        </div>
    </header>

    <?php if ($ok): ?>
        <div class="firmas-alert success" role="status">La firma se generó y se envió correctamente.</div>
    <?php elseif ($error !== ''): ?>
        <div class="firmas-alert error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="firmas-form" method="POST" action="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/') . 'admin/firmas/backend/procesar_firma.php', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="firmas-section-title"><span>1</span><div><h2>Datos de la persona</h2><p>Completa la información que aparecerá en la firma.</p></div></div>
        <div class="firmas-fields">
            <label class="firmas-field"><span>Nombre completo <b>*</b></span><input type="text" name="nombre" maxlength="120" autocomplete="name" placeholder="Ej. María López García" required></label>
            <label class="firmas-field"><span>Área</span><input type="text" name="area" maxlength="100" placeholder="Ej. Operaciones"></label>
            <label class="firmas-field"><span>Puesto <b>*</b></span><input type="text" name="puesto" maxlength="100" placeholder="Ej. Gerente de Operaciones" required></label>
            <label class="firmas-field"><span>Teléfono</span><input type="tel" name="telefono" maxlength="30" autocomplete="tel" placeholder="Ej. 55 1234 5678"></label>
            <label class="firmas-field firmas-field-wide"><span>Correo electrónico <b>*</b></span><input type="email" name="correo" maxlength="254" autocomplete="email" placeholder="persona@empresa.com" required></label>
        </div>
        <div class="firmas-actions"><p>La imagen de la firma se adjuntará al correo que indiques.</p><button type="submit" class="firmas-submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Generar y enviar</button></div>
    </form>
</main>
