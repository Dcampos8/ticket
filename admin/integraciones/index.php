<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
require_once ROOT_PATH . 'shared/integration_settings.php';
if (!esSuperAdmin()) { http_response_code(403); exit('Acceso denegado.'); }
$csrf = asegurarTokenCsrf();
$mensaje = '';
$error = '';
$campos = [
    'MAIL_MENSAJERIA_HOST' => ['Correo de mensajería', 'Servidor SMTP', false, 'text'],
    'MAIL_MENSAJERIA_USER' => ['Correo de mensajería', 'Usuario', false, 'text'],
    'MAIL_MENSAJERIA_PASS' => ['Correo de mensajería', 'Contraseña', true, 'password'],
    'MAIL_TICKETS_TO' => ['Correo de mensajería', 'Destinatario de avisos de tickets', false, 'email'],
    'REPORTES_DIARIOS_TO' => ['Reportes de tickets', 'Destinatarios del resumen diario (separa varios con coma)', false, 'text'],
    'MAIL_SISTEMAS_HOST' => ['Correo de sistemas', 'Servidor IMAP', false, 'text'],
    'MAIL_SISTEMAS_USER' => ['Correo de sistemas', 'Usuario', false, 'text'],
    'MAIL_SISTEMAS_PASS' => ['Correo de sistemas', 'Contraseña', true, 'password'],
    'TELEGRAM_BOT_TOKEN' => ['Telegram', 'Token del bot', true, 'password'],
    'TELEGRAM_CHAT_ID' => ['Telegram', 'ID del chat', false, 'text'],
    'GEMINI_API_KEY' => ['Inteligencia artificial', 'Clave API de Gemini', true, 'password'],
    'GEMINI_MODEL' => ['Inteligencia artificial', 'Modelo Gemini', false, 'text'],
    'INTRANET_TICKET_API_SECRET' => ['Intranet', 'Secreto de integración', true, 'password'],
];
$keyCifradoConfigurado = claveCifradoIntegraciones() !== null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validarTokenCsrf()) $error = 'La sesión expiró. Recarga la página e intenta de nuevo.';
    elseif (($_POST['accion'] ?? '') === 'estado_sitio') {
        $estadoSitio = ($_POST['sitio_dado_de_baja'] ?? '0') === '1' ? '1' : '0';
        if (guardarConfiguracionIntegracion('SITIO_DADO_DE_BAJA', $estadoSitio, false, (string) ($_SESSION['usuario'] ?? 'superadmin'))) {
            header('Location: ' . BASE_URL . 'admin/integraciones/?estado=' . ($estadoSitio === '1' ? 'baja' : 'activo'));
            exit;
        }
        $error = 'No se pudo guardar el estado. Verifica que la migración de integraciones esté aplicada.';
    }
    else {
        $ok = true;
        foreach ($campos as $clave => [$grupo, $etiqueta, $secreto, $tipo]) {
            $valor = trim((string) ($_POST[$clave] ?? ''));
            if ($secreto && $valor === '') continue;
            if ($secreto && !$keyCifradoConfigurado) { $ok = false; $error = 'Configura APP_SETTINGS_ENCRYPTION_KEY en el .env antes de guardar claves.'; break; }
            if ($clave === 'REPORTES_DIARIOS_TO' && $valor !== '') {
                $correos = array_filter(array_map('trim', explode(',', $valor)), static fn($correo) => $correo !== '');
                foreach ($correos as $correo) if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $ok = false; $error = 'Revisa los correos del resumen diario; sepáralos con coma.'; break 2; }
            }
            if ($tipo === 'email' && $valor !== '' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) { $ok = false; $error = 'El correo destinatario no tiene un formato válido.'; break; }
            if ($tipo === 'url' && !filter_var($valor, FILTER_VALIDATE_URL)) { $ok = false; $error = 'La URL base debe ser válida e incluir https://'; break; }
            if ($clave === 'INTRANET_TICKET_API_SECRET' && strlen($valor) < 32) { $ok = false; $error = 'El secreto de intranet debe tener al menos 32 caracteres.'; break; }
            if (!guardarConfiguracionIntegracion($clave, $valor, $secreto, (string) ($_SESSION['usuario'] ?? 'superadmin'))) { $ok = false; $error = 'No se pudo guardar. Verifica que la migración de base de datos esté aplicada.'; break; }
        }
        if ($ok) { $mensaje = 'Configuración guardada.'; }
    }
}
include ROOT_PATH . 'admin/menu.php';
?>
<main class="container-fluid px-4 py-4 integrations-page">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div><h1 class="h3 mb-1"><i class="fa-solid fa-plug text-danger me-2"></i>Conexiones e integraciones</h1><p class="text-muted mb-0">Administra los servicios que utiliza la plataforma.</p></div>
    <span class="badge <?= $keyCifradoConfigurado ? 'text-bg-success' : 'text-bg-warning' ?>">Cifrado <?= $keyCifradoConfigurado ? 'configurado' : 'pendiente' ?></span>
  </div>
  <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (($_GET['estado'] ?? '') === 'baja'): ?><div class="alert alert-warning">El sitio está dado de baja. El superadministrador conserva el acceso.</div><?php elseif (($_GET['estado'] ?? '') === 'activo'): ?><div class="alert alert-success">El sitio está activo nuevamente.</div><?php endif; ?>
  <?php $sitioBaja = sitioEstaDadoDeBaja(); ?>
  <section class="card mb-4"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div><h2 class="h5 mb-1">Disponibilidad del sitio</h2><p class="text-muted mb-0">Al darlo de baja, los usuarios y administradores verán un aviso de indisponibilidad. El superadministrador seguirá usando el sistema.</p></div>
    <form method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="estado_sitio"><input type="hidden" name="sitio_dado_de_baja" value="<?= $sitioBaja ? '0' : '1' ?>"><button class="btn <?= $sitioBaja ? 'btn-success' : 'btn-danger' ?>" type="submit"><i class="fa-solid <?= $sitioBaja ? 'fa-circle-check' : 'fa-power-off' ?> me-2"></i><?= $sitioBaja ? 'Reactivar sitio' : 'Dar de baja el sitio' ?></button></form>
  </div></section>
  <?php if (!$keyCifradoConfigurado): ?><div class="alert alert-warning">Para guardar contraseñas y tokens, agrega <code>APP_SETTINGS_ENCRYPTION_KEY</code> al archivo .env con al menos 32 caracteres y reinicia PHP.</div><?php endif; ?>
  <form method="post" class="integration-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php $grupoActual = ''; foreach ($campos as $clave => [$grupo, $etiqueta, $secreto, $tipo]): ?>
      <?php if ($grupo !== $grupoActual): if ($grupoActual !== '') echo '</div></section>'; $grupoActual = $grupo; ?>
      <section class="card integration-card mb-4"><div class="card-body"><h2 class="h5 mb-3"><?= htmlspecialchars($grupo) ?></h2><div class="row g-3">
      <?php endif; $valorPredeterminado = $clave === 'GEMINI_MODEL' ? 'gemini-3.5-flash-lite' : ($clave === 'MAIL_SISTEMAS_HOST' ? '{imap.hostinger.com:993/imap/ssl}INBOX' : ''); $valor = obtenerConfiguracionIntegracion($clave, $valorPredeterminado); ?>
      <div class="col-12 col-md-6"><label class="form-label" for="<?= $clave ?>"><?= htmlspecialchars($etiqueta) ?></label>
        <?php if ($secreto): ?><input class="form-control" type="password" id="<?= $clave ?>" name="<?= $clave ?>" autocomplete="new-password" placeholder="<?= $valor !== '' ? 'Configurado; vacío conserva el valor actual' : 'Sin configurar' ?>" <?= !$keyCifradoConfigurado ? 'disabled' : '' ?>>
        <?php else: ?><input class="form-control" type="<?= in_array($tipo, ['email', 'url'], true) ? $tipo : 'text' ?>" id="<?= $clave ?>" name="<?= $clave ?>" value="<?= htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') ?>" <?= $clave === 'INTRANET_TICKET_API_SECRET' ? 'minlength="32"' : '' ?>><?php endif; ?>
      </div>
    <?php endforeach; ?></div></div></section>
    <div class="d-flex justify-content-end"><button class="btn btn-danger px-4" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar configuración</button></div>
  </form>
</main>
<link rel="stylesheet" href="<?= BASE_URL ?>css/integraciones.css?v=1">
