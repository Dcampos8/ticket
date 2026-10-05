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
  <style>
    :root {
      --primary-color: var(--tv-navy, #1e3a8a);
      --primary-color-dark: var(--tv-navy-dark, #172554);
      --accent-color: var(--tv-red, #e31e24);
      --background-color: #f1f5f9;
      --input-bg: #f8fafc;
      --text-color: #1e293b;
      --error-bg: #fdeceb;
      --error-text: #b91c1c;
      --radius: 12px;
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      font-family: var(--tv-font, 'Inter', 'Segoe UI', sans-serif);
      background: linear-gradient(160deg, #0f172a 0%, #1e3a8a 45%, #0f172a 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      padding: 20px;
    }

    .login-shell {
      width: 100%;
      max-width: 400px;
    }

    .login-logo {
      display: flex;
      justify-content: center;
      margin-bottom: 22px;
    }

    .login-logo img {
      height: 52px;
      width: auto;
    }

    .login-container {
      background: #fff;
      padding: 36px 32px;
      border-radius: 18px;
      box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.45);
      width: 100%;
      position: relative;
      overflow: hidden;
    }

    .login-container::before {
      content: "";
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: linear-gradient(90deg, var(--accent-color), var(--primary-color));
    }

    h2 {
      text-align: center;
      color: var(--text-color);
      margin: 6px 0 4px;
      font-size: 22px;
      font-weight: 800;
    }

    .login-subtitle {
      text-align: center;
      color: #64748b;
      font-size: 13.5px;
      margin-bottom: 26px;
    }

    label.field-label {
      display: block;
      font-size: 12.5px;
      font-weight: 600;
      color: #475569;
      margin-bottom: 6px;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 13px 14px;
      margin-bottom: 16px;
      border: 1px solid #dde3ea;
      border-radius: var(--radius);
      font-size: 15px;
      font-family: inherit;
      background-color: var(--input-bg);
      transition: border-color 0.2s ease, box-shadow 0.2s ease, background-color 0.2s ease;
    }

    input[type="text"]:focus,
    input[type="password"]:focus {
      outline: none;
      border-color: var(--primary-color);
      background-color: #fff;
      box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.12);
    }

    .login-note {
      color: #94a3b8;
      font-size: 12px;
      display: block;
      margin: -8px 0 18px;
    }

    button {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, var(--primary-color), var(--primary-color-dark));
      border: none;
      border-radius: var(--radius);
      color: #fff;
      font-weight: 700;
      font-size: 15px;
      cursor: pointer;
      transition: transform 0.15s ease, box-shadow 0.15s ease;
      box-shadow: 0 10px 20px -8px rgba(30, 58, 138, 0.55);
    }

    button:hover {
      transform: translateY(-1px);
      box-shadow: 0 14px 24px -8px rgba(30, 58, 138, 0.6);
    }

    button:active {
      transform: translateY(0);
    }

    .error-msg {
      background-color: var(--error-bg);
      color: var(--error-text);
      padding: 12px 14px;
      border-radius: var(--radius);
      margin-bottom: 18px;
      font-size: 13.5px;
      font-weight: 600;
      text-align: center;
    }

    .login-footer {
      text-align: center;
      margin-top: 22px;
      font-size: 12px;
      color: rgba(255, 255, 255, 0.65);
    }

    @media (max-width: 480px) {
      .login-container {
        padding: 28px 22px;
      }

      h2 {
        font-size: 20px;
      }

      input, button {
        font-size: 14px;
      }
    }
  </style>
  <link rel="icon" href="https://transportesvaladez.com/wp-content/uploads/2024/12/cropped-Recurso-10-192x192.png" sizes="192x192">
</head>
<body>

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
