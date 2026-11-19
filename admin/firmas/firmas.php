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
            <p class="firmas-intro">Captura los datos, revisa cómo se verá la firma y después descárgala o envíala por correo.</p>
        </div>
    </header>

    <?php if ($ok): ?>
        <div class="firmas-alert success" role="status">La firma se generó y se envió correctamente.</div>
    <?php elseif ($error !== ''): ?>
        <div class="firmas-alert error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form class="firmas-form" id="form-firma" method="POST" action="<?= htmlspecialchars((defined('BASE_URL') ? BASE_URL : '/') . 'admin/firmas/backend/procesar_firma.php', ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <div class="firmas-section-title"><span>1</span><div><h2>Datos de la persona</h2><p>Completa la información que aparecerá en la firma.</p></div></div>
        <div class="firmas-fields">
            <label class="firmas-field"><span>Nombre completo <b>*</b></span><input type="text" name="nombre" maxlength="120" autocomplete="name" placeholder="Ej. María López García" required></label>
            <label class="firmas-field"><span>Área</span><input type="text" name="area" maxlength="100" placeholder="Ej. Operaciones"></label>
            <label class="firmas-field"><span>Puesto <b>*</b></span><input type="text" name="puesto" maxlength="100" placeholder="Ej. Gerente de Operaciones" required></label>
            <label class="firmas-field"><span>Teléfono</span><input type="tel" name="telefono" maxlength="30" autocomplete="tel" placeholder="Ej. 55 1234 5678"></label>
            <label class="firmas-field firmas-field-wide"><span>Correo electrónico <b>*</b></span><input type="email" name="correo" maxlength="254" autocomplete="email" placeholder="persona@empresa.com" required></label>
        </div>
        <div class="firmas-actions"><p>Primero genera la vista previa para revisar los datos.</p><button type="submit" name="accion" value="preview" class="firmas-submit" id="ver-firma"><i class="fa-solid fa-eye" aria-hidden="true"></i> Previsualizar firma</button></div>
    </form>
    <section class="firma-preview" id="vista-firma" hidden aria-labelledby="titulo-vista-firma">
        <h2 id="titulo-vista-firma">Vista previa</h2>
        <img id="imagen-firma" alt="Vista previa de la firma de correo">
        <div class="firma-preview-actions">
            <a class="firmas-submit firmas-download" id="descargar-firma" download="firma.jpg"><i class="fa-solid fa-download" aria-hidden="true"></i> Descargar firma</a>
            <button type="submit" form="form-firma" name="accion" value="send" class="firmas-submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Enviar al correo</button>
        </div>
        <p id="error-vista-firma" class="firmas-alert error" role="alert" hidden></p>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('form-firma');
    const previewButton = document.getElementById('ver-firma');
    const preview = document.getElementById('vista-firma');
    const image = document.getElementById('imagen-firma');
    const download = document.getElementById('descargar-firma');
    const error = document.getElementById('error-vista-firma');
    let previewUrl = '';

    form.addEventListener('submit', async (event) => {
        if (event.submitter !== previewButton) return;
        event.preventDefault();
        if (!form.reportValidity()) return;
        previewButton.disabled = true;
        error.hidden = true;
        try {
            const data = new FormData(form);
            data.set('accion', 'preview');
            const response = await fetch(form.action, { method: 'POST', body: data, headers: { Accept: 'image/jpeg' } });
            if (!response.ok || !(response.headers.get('content-type') || '').includes('image/')) {
                const message = await response.text();
                throw new Error(message || 'No se pudo generar la vista previa.');
            }
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = URL.createObjectURL(await response.blob());
            image.src = previewUrl;
            download.href = previewUrl;
            preview.hidden = false;
            preview.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) {
            error.textContent = e.message || 'No se pudo generar la vista previa.';
            error.hidden = false;
            preview.hidden = false;
        } finally {
            previewButton.disabled = false;
        }
    });
})();
</script>
