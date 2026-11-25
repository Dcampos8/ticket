<?php
session_start();
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/shared/branding.php';
$recaptchaSiteKey = trim((string) env('RECAPTCHA_V3_SITE_KEY', ''));
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - <?= htmlspecialchars(nombreEmpresa(), ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/login.css?v=<?= filemtime(__DIR__ . '/css/pages/login.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/css/brand.css') ?>">
  <link rel="icon" href="<?= htmlspecialchars(iconoPestanaEmpresaUrl(), ENT_QUOTES, 'UTF-8') ?>">
  <link rel="manifest" href="<?= htmlspecialchars(rtrim((string) env('APP_BASE_URL', '/'), '/') . '/manifest.php', ENT_QUOTES, 'UTF-8') ?>?v=<?= rawurlencode(nombreEmpresa()) ?>">
</head>
<body class="login-page">

<div class="login-shell">
  <div class="login-logo">
    <img src="<?= htmlspecialchars(logoEmpresaUrl(), ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars(nombreEmpresa(), ENT_QUOTES, 'UTF-8') ?>">
  </div>

  <div class="login-container">
    <h2>Iniciar Sesión</h2>
    <p class="login-subtitle">Sistema de Tickets &middot; <?= htmlspecialchars(nombreEmpresa(), ENT_QUOTES, 'UTF-8') ?></p>

    <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
      <div class="error-msg">Usuario o contraseña incorrectos.</div>
    <?php endif; ?>
    <?php if (isset($_GET['error']) && $_GET['error'] === 'captcha'): ?>
      <div class="error-msg">No se pudo validar el CAPTCHA. Intenta de nuevo.</div>
    <?php endif; ?>
    <?php if ($recaptchaSiteKey === ''): ?>
      <div class="error-msg">El CAPTCHA no está configurado. Contacta a soporte.</div>
    <?php endif; ?>

    <form id="login-form" method="POST" action="core/validar_login.php">
      <input type="hidden" id="recaptcha-token" name="recaptcha_token" value="">
      <label class="field-label" for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" placeholder="Tu usuario" required autocomplete="username" />

      <label class="field-label" for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="contrasena" placeholder="Tu contraseña" required autocomplete="current-password" />

      <span class="login-note"><b>Nota:</b> la contraseña no distingue entre mayúsculas y minúsculas.</span>

      <button type="submit">Ingresar</button>
    </form>
  </div>

  <p class="login-footer">&copy; <?= date('Y') ?> <?= htmlspecialchars(nombreEmpresa(), ENT_QUOTES, 'UTF-8') ?> — Sistema de Tickets</p>
</div>

<?php if ($recaptchaSiteKey !== ''): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?= htmlspecialchars($recaptchaSiteKey, ENT_QUOTES, 'UTF-8') ?>"></script>
<script>
  const loginForm = document.getElementById('login-form');
  loginForm.addEventListener('submit', function (event) {
    event.preventDefault();
    grecaptcha.ready(function () {
      grecaptcha.execute(<?= json_encode($recaptchaSiteKey) ?>, {action: 'login'}).then(function (token) {
        document.getElementById('recaptcha-token').value = token;
        loginForm.submit();
      });
    });
  });
</script>
<?php endif; ?>

</body>
</html>
