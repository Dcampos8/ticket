<?php
session_start();
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Login - Transportes Valadez</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css?v=<?= time() ?>">
  <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/login.css?v=<?= filemtime(__DIR__ . '/css/pages/login.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/css/brand.css') ?>">
  <link rel="icon" href="https://transportesvaladez.com/wp-content/uploads/2024/12/cropped-Recurso-10-192x192.png" sizes="192x192">
</head>
<body class="login-page">

<div class="login-shell">
  <div class="login-logo">
    <img src="img/Camion%20Cont%20Der.png" alt="Transportes Valadez">
  </div>

  <div class="login-container">
    <h2>Iniciar Sesión</h2>
    <p class="login-subtitle">Sistema de Tickets &middot; Transportes Valadez</p>

    <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
      <div class="error-msg">Usuario o contraseña incorrectos.</div>
    <?php endif; ?>

    <form method="POST" action="core/validar_login.php">
      <label class="field-label" for="usuario">Usuario</label>
      <input type="text" id="usuario" name="usuario" placeholder="Tu usuario" required autocomplete="username" />

      <label class="field-label" for="contrasena">Contraseña</label>
      <input type="password" id="contrasena" name="contrasena" placeholder="Tu contraseña" required autocomplete="current-password" />

      <span class="login-note"><b>Nota:</b> la contraseña distingue entre mayúsculas y minúsculas.</span>

      <button type="submit">Ingresar</button>
    </form>
  </div>

  <p class="login-footer">&copy; <?= date('Y') ?> Transportes Valadez — Sistema de Tickets</p>
</div>

</body>
</html>
