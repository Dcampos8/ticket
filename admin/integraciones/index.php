<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
require_once ROOT_PATH . 'shared/integration_settings.php';
require_once ROOT_PATH . 'shared/sqlserver_user_sync.php';
if (!esSuperAdmin()) { http_response_code(403); exit('Acceso denegado.'); }
$csrf = asegurarTokenCsrf();
$mensaje = '';
$error = '';
$vistaPreviaUsuarios = null;
$modoVistaPrevia = 'full';
$pestanasValidas = ['marca', 'usuarios', 'disponibilidad', 'servicios'];
$pestanaActiva = in_array((string) ($_GET['tab'] ?? ''), $pestanasValidas, true) ? (string) $_GET['tab'] : 'marca';
$campos = [
    'MAIL_MENSAJERIA_HOST' => ['Correo de mensajería', 'Servidor SMTP', false, 'text'],
    'MAIL_MENSAJERIA_USER' => ['Correo de mensajería', 'Usuario', false, 'text'],
    'MAIL_MENSAJERIA_PASS' => ['Correo de mensajería', 'Contraseña', true, 'password'],
    'MAIL_TICKETS_TO' => ['Correo de mensajería', 'Destinatario de avisos de tickets', false, 'email'],
    'REPORTES_DIARIOS_TO' => ['Reportes de tickets', 'Destinatarios del resumen diario (separa varios con coma)', false, 'text'],
    'SLA_ALERTAS_TO' => ['Alertas del Service Desk', 'Destinatarios de alertas SLA (separa varios con coma)', false, 'text'],
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
    $accionPost = (string) ($_POST['accion'] ?? '');
    $pestanaActiva = match ($accionPost) {
        'guardar_marca' => 'marca',
        'estado_sitio' => 'disponibilidad',
        'probar_conexion_sqlserver', 'previsualizar_usuarios_sqlserver', 'confirmar_sincronizacion_usuarios_sqlserver' => 'usuarios',
        default => 'servicios',
    };
    if (!validarTokenCsrf()) $error = 'La sesión expiró. Recarga la página e intenta de nuevo.';
    elseif (($_POST['accion'] ?? '') === 'estado_sitio') {
        $estadoSitio = ($_POST['sitio_dado_de_baja'] ?? '0') === '1' ? '1' : '0';
        if (guardarConfiguracionIntegracion('SITIO_DADO_DE_BAJA', $estadoSitio, false, (string) ($_SESSION['usuario'] ?? 'superadmin'))) {
            header('Location: ' . BASE_URL . 'admin/integraciones/?estado=' . ($estadoSitio === '1' ? 'baja' : 'activo') . '&tab=disponibilidad');
            exit;
        }
        $error = 'No se pudo guardar el estado. Verifica que la migración de integraciones esté aplicada.';
    }
    elseif (($_POST['accion'] ?? '') === 'guardar_marca') {
        $nombreMarca = trim((string) ($_POST['nombre_empresa'] ?? ''));
        $nombreAnterior = nombreEmpresa();
        if ($nombreMarca === '' || mb_strlen($nombreMarca, 'UTF-8') > 100) {
            $error = 'Escribe un nombre de empresa de 1 a 100 caracteres.';
        } else {
            $rutasMarca = [];
            $tiposImagen = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $directorioMarca = ROOT_PATH . 'uploads/branding';
            foreach (['logo_empresa' => 'MARCA_LOGO', 'icono_pestana' => 'MARCA_ICONO'] as $campoArchivo => $claveMarca) {
                if (!isset($_FILES[$campoArchivo]) || ($_FILES[$campoArchivo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
                $archivoMarca = $_FILES[$campoArchivo];
                if (($archivoMarca['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || (int) ($archivoMarca['size'] ?? 0) > 2 * 1024 * 1024) {
                    $error = 'Cada imagen debe pesar 2 MB o menos y cargarse correctamente.';
                    break;
                }
                $datosImagen = @getimagesize((string) $archivoMarca['tmp_name']);
                $mimeImagen = is_array($datosImagen) ? (string) ($datosImagen['mime'] ?? '') : '';
                if (!isset($tiposImagen[$mimeImagen])) {
                    $error = 'Usa imágenes PNG, JPG o WEBP para el logo y el icono.';
                    break;
                }
                if (!is_dir($directorioMarca) && !mkdir($directorioMarca, 0755, true) && !is_dir($directorioMarca)) {
                    $error = 'No se pudo preparar la carpeta para guardar las imágenes de marca.';
                    break;
                }
                $nombreArchivo = bin2hex(random_bytes(12)) . '.' . $tiposImagen[$mimeImagen];
                if (!move_uploaded_file((string) $archivoMarca['tmp_name'], $directorioMarca . DIRECTORY_SEPARATOR . $nombreArchivo)) {
                    $error = 'No se pudo guardar una de las imágenes de marca.';
                    break;
                }
                $rutasMarca[$claveMarca] = 'uploads/branding/' . $nombreArchivo;
            }
            if ($error === '') {
                $okMarca = guardarConfiguracionIntegracion('MARCA_NOMBRE_EMPRESA', $nombreMarca, false, (string) ($_SESSION['usuario'] ?? 'superadmin'));
                foreach ($rutasMarca as $claveMarca => $rutaMarca) {
                    $okMarca = guardarConfiguracionIntegracion($claveMarca, $rutaMarca, false, (string) ($_SESSION['usuario'] ?? 'superadmin')) && $okMarca;
                }
                if ($okMarca) {
                    try {
                        $stmtCarpetaMarca = $conexion->prepare('UPDATE correo_carpetas SET nombre = ? WHERE id = 1 AND nombre = ?');
                        if ($stmtCarpetaMarca) {
                            $stmtCarpetaMarca->bind_param('ss', $nombreMarca, $nombreAnterior);
                            $stmtCarpetaMarca->execute();
                            $stmtCarpetaMarca->close();
                        }
                    } catch (Throwable $e) {
                        // La tabla de correo puede no estar instalada en todas las instancias.
                    }
                    header('Location: ' . BASE_URL . 'admin/integraciones/?marca=guardada&tab=marca');
                    exit;
                }
                $error = 'No se pudo guardar la identidad visual en la base de datos.';
            }
        }
    }
    elseif (($_POST['accion'] ?? '') === 'probar_conexion_sqlserver') {
        $sqlHostTest = trim((string) env('DB2_HOST', ''));
        $sqlPortTest = (int) env('DB2_PORT', '1433');
        $socketError = 0;
        $socketMessage = '';
        $socket = $sqlHostTest !== '' && $sqlPortTest > 0
            ? @fsockopen($sqlHostTest, $sqlPortTest, $socketError, $socketMessage, 5)
            : false;
        if ($socket === false) {
            $error = sprintf(
                'El PHP cloud no logra abrir TCP a %s:%d (error %d). Revisa el reenvío del router hacia el puerto SQL local, la IP pública y si el proveedor permite ese tráfico saliente.',
                $sqlHostTest !== '' ? $sqlHostTest : '(sin host)',
                $sqlPortTest,
                $socketError
            );
            if ($socketMessage !== '') {
                $error .= ' Detalle de red: ' . $socketMessage;
            }
        } else {
            fclose($socket);
            try {
                $sqlsrvTest = conectarSqlServerUsuarios();
                $sqlsrvTest->query('SELECT TOP (0) id_usuario, nombre, elpassword, status, email FROM dbo.Usuarios');
                $mensaje = 'Conexión exitosa. El servidor cloud pudo acceder a dbo.Usuarios en SQL Server.';
            } catch (Throwable $e) {
                $detallePdo = trim((string) $e->getMessage());
                $valoresSensibles = array_filter([
                    (string) env('DB2_PASS', ''),
                    (string) env('DB2_USER', ''),
                ], static fn($valor) => $valor !== '');
                if ($valoresSensibles) {
                    $detallePdo = str_replace($valoresSensibles, '[oculto]', $detallePdo);
                }
                $codigoPdo = (string) $e->getCode();
                error_log('Prueba PDO SQL Server fallida (' . get_class($e) . ', código ' . $codigoPdo . '): ' . $detallePdo);
                $error = 'El puerto TCP responde, pero PDO ODBC falló. Detalle técnico: ' . ($detallePdo !== '' ? $detallePdo : 'sin mensaje del controlador') . ' (código ' . $codigoPdo . ').';
            }
        }
    }
    elseif (($_POST['accion'] ?? '') === 'previsualizar_usuarios_sqlserver') {
        try {
            $modoVistaPrevia = (string) ($_POST['modo_sincronizacion'] ?? 'full');
            if (!in_array($modoVistaPrevia, ['full', 'insert_only'], true)) {
                throw new InvalidArgumentException('Modo de vista previa no válido.');
            }
            $vistaPreviaUsuarios = previsualizarUsuariosSqlServer($conexion, $modoVistaPrevia);
            $_SESSION['sqlserver_users_sync_preview_token'] = bin2hex(random_bytes(32));
            $_SESSION['sqlserver_users_sync_preview_mode'] = $modoVistaPrevia;
        } catch (Throwable $e) {
            error_log('Vista previa de usuarios SQL Server fallida (' . get_class($e) . '): ' . $e->getMessage());
            $error = 'No se pudo obtener la vista previa. Revisa la conexión y los registros PHP.';
        }
    }
    elseif (($_POST['accion'] ?? '') === 'confirmar_sincronizacion_usuarios_sqlserver') {
        $tokenVista = (string) ($_SESSION['sqlserver_users_sync_preview_token'] ?? '');
        $tokenRecibido = (string) ($_POST['preview_token'] ?? '');
        $modoVistaPrevia = (string) ($_SESSION['sqlserver_users_sync_preview_mode'] ?? '');
        if ($tokenVista === '' || $tokenRecibido === '' || !hash_equals($tokenVista, $tokenRecibido)) {
            $error = 'La vista previa expiró. Obtén una nueva antes de sincronizar.';
        } elseif (!in_array($modoVistaPrevia, ['full', 'insert_only'], true)) {
            $error = 'El modo de sincronización expiró. Obtén una nueva vista previa.';
        } else {
            unset($_SESSION['sqlserver_users_sync_preview_token']);
            unset($_SESSION['sqlserver_users_sync_preview_mode']);
            try {
                $stats = sincronizarUsuariosSqlServer($conexion, $modoVistaPrevia);
                if ($modoVistaPrevia === 'insert_only') {
                    $mensaje = sprintf(
                        'Importación de nuevos usuarios activos completada. Agregados: %d; existentes omitidos: %d; inactivos omitidos: %d; sin contraseña utilizable: %d.',
                        $stats['insertados'],
                        $stats['omitidos'] - $stats['inactivos_omitidos'],
                        $stats['inactivos_omitidos'],
                        $stats['sin_contrasena']
                    );
                } else {
                    $mensaje = sprintf(
                        'Sincronización completa terminada. Agregados: %d; actualizados: %d; inactivos nuevos omitidos: %d; sin contraseña utilizable: %d.',
                        $stats['insertados'],
                        $stats['actualizados'],
                        $stats['inactivos_omitidos'],
                        $stats['sin_contrasena']
                    );
                }
            } catch (Throwable $e) {
                error_log('Sincronización manual de usuarios SQL Server fallida (' . get_class($e) . ').');
                $error = 'No se completó la sincronización. Revisa la conexión y los registros PHP.';
            }
        }
    }
    else {
        $ok = true;
        foreach ($campos as $clave => [$grupo, $etiqueta, $secreto, $tipo]) {
            $valor = trim((string) ($_POST[$clave] ?? ''));
            if ($secreto && $valor === '') continue;
            if ($secreto && !$keyCifradoConfigurado) { $ok = false; $error = 'Configura APP_SETTINGS_ENCRYPTION_KEY en el .env antes de guardar claves.'; break; }
            if (in_array($clave, ['REPORTES_DIARIOS_TO', 'SLA_ALERTAS_TO'], true) && $valor !== '') {
                $correos = array_filter(array_map('trim', explode(',', $valor)), static fn($correo) => $correo !== '');
                foreach ($correos as $correo) if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) { $ok = false; $error = 'Revisa los correos configurados; sepáralos con coma.'; break 2; }
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
  <header class="integration-hero mb-4">
    <div class="integration-hero-copy">
      <span class="integration-eyebrow">CENTRO DE ADMINISTRACIÓN</span>
      <h1><span class="integration-hero-icon"><i class="fa-solid fa-sliders"></i></span><span>Conexiones e integraciones<small>Configura la identidad y los servicios de tu plataforma.</small></span></h1>
    </div>
    <div class="integration-security <?= $keyCifradoConfigurado ? 'is-ready' : 'is-pending' ?>">
      <span class="security-indicator"><i class="fa-solid <?= $keyCifradoConfigurado ? 'fa-lock' : 'fa-lock-open' ?>"></i></span>
      <span><small>Seguridad de credenciales</small><strong><?= $keyCifradoConfigurado ? 'Cifrado activo' : 'Cifrado pendiente' ?></strong></span>
    </div>
  </header>
  <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if (($_GET['marca'] ?? '') === 'guardada'): ?><div class="alert alert-success">Nombre e identidad visual guardados.</div><?php endif; ?>
  <?php if (($_GET['estado'] ?? '') === 'baja'): ?><div class="alert alert-warning">El sitio está dado de baja. El superadministrador conserva el acceso.</div><?php elseif (($_GET['estado'] ?? '') === 'activo'): ?><div class="alert alert-success">El sitio está activo nuevamente.</div><?php endif; ?>
  <?php $sitioBaja = sitioEstaDadoDeBaja(); ?>
  <div class="integration-tabs-shell">
    <ul class="nav nav-tabs integration-tabs" id="integrationTabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link <?= $pestanaActiva === 'marca' ? 'active' : '' ?>" id="marca-tab" data-bs-toggle="tab" data-bs-target="#marca-pane" type="button" role="tab" aria-controls="marca-pane" aria-selected="<?= $pestanaActiva === 'marca' ? 'true' : 'false' ?>"><i class="fa-solid fa-palette"></i><span>Identidad</span></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link <?= $pestanaActiva === 'usuarios' ? 'active' : '' ?>" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios-pane" type="button" role="tab" aria-controls="usuarios-pane" aria-selected="<?= $pestanaActiva === 'usuarios' ? 'true' : 'false' ?>"><i class="fa-solid fa-database"></i><span>SQL Server</span></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link <?= $pestanaActiva === 'disponibilidad' ? 'active' : '' ?>" id="disponibilidad-tab" data-bs-toggle="tab" data-bs-target="#disponibilidad-pane" type="button" role="tab" aria-controls="disponibilidad-pane" aria-selected="<?= $pestanaActiva === 'disponibilidad' ? 'true' : 'false' ?>"><i class="fa-solid fa-power-off"></i><span>Disponibilidad</span></button></li>
      <li class="nav-item" role="presentation"><button class="nav-link <?= $pestanaActiva === 'servicios' ? 'active' : '' ?>" id="servicios-tab" data-bs-toggle="tab" data-bs-target="#servicios-pane" type="button" role="tab" aria-controls="servicios-pane" aria-selected="<?= $pestanaActiva === 'servicios' ? 'true' : 'false' ?>"><i class="fa-solid fa-plug"></i><span>Servicios</span></button></li>
    </ul>
    <div class="tab-content integration-tab-content" id="integrationTabsContent">
      <div class="tab-pane fade <?= $pestanaActiva === 'marca' ? 'show active' : '' ?>" id="marca-pane" role="tabpanel" aria-labelledby="marca-tab" tabindex="0">
  <section class="card mb-4 integration-feature-card"><div class="card-body">
    <div class="integration-section-heading"><span class="integration-section-icon"><i class="fa-solid fa-building"></i></span><div><h2 class="h5 mb-1">Identidad de la empresa</h2><p class="text-muted mb-0">Personaliza cómo se presenta la plataforma en sus páginas y documentos.</p></div></div>
    <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="accion" value="guardar_marca">
      <div class="col-12"><div class="brand-name-field"><label class="form-label" for="nombre_empresa">Nombre de la empresa</label><input class="form-control" id="nombre_empresa" name="nombre_empresa" maxlength="100" required value="<?= htmlspecialchars(nombreEmpresa(), ENT_QUOTES, 'UTF-8') ?>"><div class="form-text">Se reflejará en el sistema, los documentos y las notificaciones.</div></div></div>
      <div class="col-lg-6"><div class="brand-upload-card"><div class="brand-upload-top"><span class="brand-upload-icon"><i class="fa-regular fa-image"></i></span><div><label class="form-label" for="logo_empresa">Logo del menú</label><small>PNG, JPG o WEBP · máximo 2 MB</small></div></div><input class="form-control" id="logo_empresa" name="logo_empresa" type="file" accept="image/png,image/jpeg,image/webp"><div class="brand-preview"><span>Vista actual</span><img width="180" height="48" src="<?= htmlspecialchars(logoEmpresaUrl(), ENT_QUOTES, 'UTF-8') ?>" alt="Logo actual"></div></div></div>
      <div class="col-lg-6"><div class="brand-upload-card"><div class="brand-upload-top"><span class="brand-upload-icon"><i class="fa-regular fa-window-maximize"></i></span><div><label class="form-label" for="icono_pestana">Icono de pestaña</label><small>PNG, JPG o WEBP · máximo 2 MB</small></div></div><input class="form-control" id="icono_pestana" name="icono_pestana" type="file" accept="image/png,image/jpeg,image/webp"><div class="brand-preview"><span>Vista actual</span><img class="brand-favicon-preview" width="38" height="38" src="<?= htmlspecialchars(iconoPestanaEmpresaUrl(), ENT_QUOTES, 'UTF-8') ?>" alt="Icono actual"></div></div></div>
      <div class="col-12 d-flex justify-content-end"><button class="btn btn-danger integration-save-button" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar identidad</button></div>
    </form>
  </div></section>
      </div>
      <div class="tab-pane fade <?= $pestanaActiva === 'usuarios' ? 'show active' : '' ?>" id="usuarios-pane" role="tabpanel" aria-labelledby="usuarios-tab" tabindex="0">
  <?php
    $sqlsrvDisponible = extension_loaded('odbc') && in_array('odbc', PDO::getAvailableDrivers(), true);
    $sqlsrvConfigurado = trim((string) env('DB2_HOST', '')) !== ''
        && trim((string) env('DB2_NAME', '')) !== ''
        && trim((string) env('DB2_USER', '')) !== '';
  ?>
  <section class="card mb-4"><div class="card-body">
    <div class="integration-section-heading"><span class="integration-section-icon"><i class="fa-solid fa-database"></i></span><div><h2 class="h5 mb-1">Sincronización de usuarios de SQL Server</h2><p class="text-muted mb-0">Revisa los cambios antes de aplicarlos. La conexión usa el DSN ODBC <code>mssql_odbc</code>.</p></div></div>
    <?php if (!$sqlsrvDisponible): ?><div class="alert alert-warning mb-3">Habilita las extensiones PHP <code>odbc</code> y <code>pdo_odbc</code> en el panel del hosting.</div><?php endif; ?>
    <?php if (!$sqlsrvConfigurado): ?><div class="alert alert-warning mb-3">Falta completar DB2_HOST, DB2_NAME y DB2_USER en el archivo .env.</div><?php endif; ?>
    <div class="integration-connection-tools">
    <form method="post" class="d-inline-block mb-2">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <input type="hidden" name="accion" value="probar_conexion_sqlserver">
      <button class="btn btn-outline-primary" type="submit" <?= (!$sqlsrvDisponible || !$sqlsrvConfigurado) ? 'disabled' : '' ?>><i class="fa-solid fa-plug-circle-check me-2"></i>Probar conexión</button>
    </form>
    <div class="integration-sync-actions">
      <form method="post" class="integration-action-card" onsubmit="const button=this.querySelector('button[type=submit]'); button.disabled=true; button.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\' aria-hidden=\'true\'></span>Obteniendo datos...';">
        <span class="sync-action-icon"><i class="fa-solid fa-arrows-rotate"></i></span><span class="sync-action-copy"><strong>Vista previa completa</strong><small>Revisa usuarios nuevos y actualizaciones</small></span>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="previsualizar_usuarios_sqlserver">
        <input type="hidden" name="modo_sincronizacion" value="full">
        <button class="btn btn-primary" type="submit" <?= (!$sqlsrvDisponible || !$sqlsrvConfigurado) ? 'disabled' : '' ?>>Obtener vista previa</button>
      </form>
      <form method="post" class="integration-action-card" onsubmit="const button=this.querySelector('button[type=submit]'); button.disabled=true; button.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\' aria-hidden=\'true\'></span>Buscando nuevos...';">
        <span class="sync-action-icon is-new"><i class="fa-solid fa-user-plus"></i></span><span class="sync-action-copy"><strong>Solo usuarios nuevos</strong><small>Busca activos que todavía no existen</small></span>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="accion" value="previsualizar_usuarios_sqlserver">
        <input type="hidden" name="modo_sincronizacion" value="insert_only">
        <button class="btn btn-outline-primary" type="submit" <?= (!$sqlsrvDisponible || !$sqlsrvConfigurado) ? 'disabled' : '' ?>>Buscar nuevos activos</button>
      </form>
    </div>
    </div>
    <?php if (is_array($vistaPreviaUsuarios)): ?>
      <?php
        $resumenSync = $vistaPreviaUsuarios['estadisticas'];
        $filasSync = $vistaPreviaUsuarios['usuarios'];
      ?>
      <hr class="my-4">
      <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge text-bg-secondary">Leídos: <?= (int) $resumenSync['total'] ?></span>
        <span class="badge text-bg-success">Nuevos activos a agregar: <?= (int) $resumenSync['agregar'] ?></span>
        <?php if ($modoVistaPrevia === 'full'): ?>
          <span class="badge text-bg-primary">Existentes a actualizar: <?= (int) $resumenSync['actualizar'] ?></span>
        <?php else: ?>
          <span class="badge text-bg-secondary">Ya existen; se omiten: <?= (int) $resumenSync['omitir_existentes'] ?></span>
        <?php endif; ?>
        <span class="badge text-bg-dark">Nuevos inactivos; se omiten: <?= (int) $resumenSync['omitir_inactivos'] ?></span>
        <span class="badge text-bg-warning">Sin contraseña; se omiten: <?= (int) $resumenSync['omitir_sin_contrasena'] ?></span>
      </div>
      <div class="alert alert-info py-2">
        <?php if ($modoVistaPrevia === 'full'): ?>La vista previa no modifica MySQL. Al confirmar, actualiza datos y estado de cuentas existentes; solo agrega usuarios nuevos activos.<?php else: ?>La vista previa no modifica MySQL. Al confirmar, solo agrega usuarios activos que no existan en MySQL y omite todas las cuentas existentes.<?php endif; ?> Las contraseñas no se muestran y se guardan como hashes.
      </div>
      <?php if ($filasSync): ?>
        <div class="table-responsive" style="max-height: 460px; overflow: auto;">
          <table class="table table-sm table-striped table-hover align-middle">
            <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
              <tr><th>Usuario</th><th>Nombre</th><th>Correo</th><th>Estado origen</th><th>Contraseña</th><th>Acción</th></tr>
            </thead>
            <tbody>
              <?php foreach ($filasSync as $filaSync): ?>
                <tr>
                  <td><?= htmlspecialchars($filaSync['usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($filaSync['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($filaSync['email'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($filaSync['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($filaSync['contrasena'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($filaSync['accion'], ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <form method="post" class="mt-3" onsubmit="if(!confirm('Se aplicará la acción indicada en la vista previa. ¿Confirmas?')) return false; const button=this.querySelector('button[type=submit]'); button.disabled=true; button.innerHTML='<span class=\'spinner-border spinner-border-sm me-2\' aria-hidden=\'true\'></span>Sincronizando...';">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
          <input type="hidden" name="accion" value="confirmar_sincronizacion_usuarios_sqlserver">
          <input type="hidden" name="preview_token" value="<?= htmlspecialchars((string) ($_SESSION['sqlserver_users_sync_preview_token'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
          <button class="btn btn-success" type="submit"><i class="fa-solid fa-check me-2"></i><?= $modoVistaPrevia === 'insert_only' ? 'Agregar solo los nuevos activos' : 'Sincronizar usuarios existentes y nuevos activos' ?></button>
        </form>
      <?php else: ?>
        <div class="alert alert-warning mb-0">SQL Server no devolvió cuentas con un identificador de usuario válido.</div>
      <?php endif; ?>
    <?php endif; ?>
  </div></section>
      </div>
      <div class="tab-pane fade <?= $pestanaActiva === 'disponibilidad' ? 'show active' : '' ?>" id="disponibilidad-pane" role="tabpanel" aria-labelledby="disponibilidad-tab" tabindex="0">
  <section class="card mb-4"><div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
    <div class="integration-section-heading mb-0"><span class="integration-section-icon"><i class="fa-solid fa-globe"></i></span><div><h2 class="h5 mb-1">Disponibilidad del sitio</h2><p class="text-muted mb-0">Controla si el personal puede acceder al sistema. El superadministrador conserva el acceso.</p></div></div>
    <form method="post" class="m-0"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="estado_sitio"><input type="hidden" name="sitio_dado_de_baja" value="<?= $sitioBaja ? '0' : '1' ?>"><button class="btn <?= $sitioBaja ? 'btn-success' : 'btn-danger' ?>" type="submit"><i class="fa-solid <?= $sitioBaja ? 'fa-circle-check' : 'fa-power-off' ?> me-2"></i><?= $sitioBaja ? 'Reactivar sitio' : 'Dar de baja el sitio' ?></button></form>
  </div></section>
      </div>
      <div class="tab-pane fade <?= $pestanaActiva === 'servicios' ? 'show active' : '' ?>" id="servicios-pane" role="tabpanel" aria-labelledby="servicios-tab" tabindex="0">
  <?php if (!$keyCifradoConfigurado): ?><div class="alert alert-warning">Para guardar contraseñas y tokens, agrega <code>APP_SETTINGS_ENCRYPTION_KEY</code> al archivo .env con al menos 32 caracteres y reinicia PHP.</div><?php endif; ?>
  <form method="post" class="integration-form">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
    <?php $grupoActual = ''; foreach ($campos as $clave => [$grupo, $etiqueta, $secreto, $tipo]): ?>
      <?php if ($grupo !== $grupoActual): if ($grupoActual !== '') echo '</div></section>'; $grupoActual = $grupo; ?>
      <?php $iconoGrupo = ['Correo de mensajería' => 'fa-envelope', 'Reportes de tickets' => 'fa-chart-column', 'Alertas del Service Desk' => 'fa-bell', 'Correo de sistemas' => 'fa-inbox', 'Telegram' => 'fa-paper-plane', 'Inteligencia artificial' => 'fa-wand-magic-sparkles', 'Intranet' => 'fa-building-shield'][$grupo] ?? 'fa-plug'; ?>
      <section class="card integration-card mb-4"><div class="card-body"><div class="integration-group-heading"><span class="integration-group-icon"><i class="fa-solid <?= htmlspecialchars($iconoGrupo, ENT_QUOTES, 'UTF-8') ?>"></i></span><h2 class="h5"><?= htmlspecialchars($grupo) ?></h2></div><div class="row g-3">
      <?php endif; $valorPredeterminado = $clave === 'GEMINI_MODEL' ? 'gemini-3.5-flash-lite' : ($clave === 'MAIL_SISTEMAS_HOST' ? '{imap.hostinger.com:993/imap/ssl}INBOX' : ''); $valor = obtenerConfiguracionIntegracion($clave, $valorPredeterminado); ?>
      <div class="integration-field col-12 col-md-6"><label class="form-label" for="<?= $clave ?>"><?= htmlspecialchars($etiqueta) ?></label>
        <?php if ($secreto): ?><input class="form-control" type="password" id="<?= $clave ?>" name="<?= $clave ?>" autocomplete="new-password" placeholder="<?= $valor !== '' ? 'Configurado; vacío conserva el valor actual' : 'Sin configurar' ?>" <?= !$keyCifradoConfigurado ? 'disabled' : '' ?>>
        <?php else: ?><input class="form-control" type="<?= in_array($tipo, ['email', 'url'], true) ? $tipo : 'text' ?>" id="<?= $clave ?>" name="<?= $clave ?>" value="<?= htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8') ?>" <?= $clave === 'INTRANET_TICKET_API_SECRET' ? 'minlength="32"' : '' ?>><?php endif; ?>
      </div>
    <?php endforeach; ?></div></div></section>
    <div class="d-flex justify-content-end"><button class="btn btn-danger px-4" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Guardar configuración</button></div>
  </form>
      </div>
    </div>
  </div>
</main>
<link rel="stylesheet" href="<?= BASE_URL ?>css/integraciones.css?v=<?= filemtime(ROOT_PATH . 'css/integraciones.css') ?>">
