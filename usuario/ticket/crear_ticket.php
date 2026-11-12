<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once '../../backend/conexion.php';

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    http_response_code(403);
    exit("Acceso no autorizado");
}
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__ticket__crear_ticket.css?v=<?= filemtime(__DIR__ . '/../../css/pages/usuario__ticket__crear_ticket.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<link rel="icon" type="image/svg+xml" href="../../img/favicon.svg?v=2">
<div class="form-header">
    <span class="form-eyebrow">SOPORTE TÉCNICO</span>
    <h2><i class="fas fa-ticket-alt"></i> Crear un ticket</h2>
    <p>Cuéntanos qué sucede y el equipo de soporte le dará seguimiento.</p>
</div>

<div class="ticket-page">
    <form id="formCrearTicket" enctype="multipart/form-data" class="form-container">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="usuario" value="<?= htmlspecialchars($_SESSION['usuario']) ?>">
        <input type="hidden" name="area" value="<?= htmlspecialchars($_SESSION['area']) ?>">
        <input type="hidden" name="nombre" value="<?= htmlspecialchars($_SESSION['nombre_completo']) ?>">

        <div class="ticket-card">
            <div class="ticket-requester-heading"><i class="fa-solid fa-circle-user"></i><span>Solicitud a nombre de</span></div>
            <div class="user-grid">
        
                <div class="campo">
                    <label>Usuario</label>
                    <input type="text" value="<?= htmlspecialchars($_SESSION['usuario']) ?>" disabled>
                </div>
        
                <div class="campo">
                    <label>Área</label>
                    <input type="text" value="<?= htmlspecialchars($_SESSION['area']) ?>" disabled>
                </div>
        
                <div class="campo">
                    <label>Nombre</label>
                    <input type="text" value="<?= htmlspecialchars($_SESSION['nombre_completo']) ?>" disabled>
                </div>
        
            </div>
        </div>
        <div class="campo">
            <label for="descripcionTicket">¿En qué podemos ayudarte? <span class="required-mark">*</span></label>
            <p class="field-help">Incluye qué intentabas hacer, qué ocurrió y desde cuándo sucede.</p>
            <textarea id="descripcionTicket" name="descripcion" required rows="5" maxlength="5000" placeholder="Ejemplo: al abrir el sistema de facturación aparece un mensaje de error desde esta mañana..."></textarea>
            <div class="field-counter"><span>Entre más detalles compartas, más rápido podremos ayudarte.</span><span id="descripcionConteo">0 / 5000</span></div>
            <div id="sugerenciasAyuda" class="kb-suggestions" aria-live="polite" hidden></div>
        </div>
        <div class="campo">
            <div class="category-note"><i class="fa-solid fa-wand-magic-sparkles"></i><span><strong>Categorización automática</strong><small>Analizaremos la descripción para dirigir tu solicitud al equipo adecuado.</small></span></div>
        </div>
        <div class="campo">
            <label for="imagen">Adjunta una captura (opcional)</label>
            <div id="dropArea">
                <div id="uploadIcon"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <p id="dropText">Haz clic o arrastra una imagen aquí<br><small>También puedes pegarla con Ctrl + V · máximo 5 MB</small></p>
                <input type="file" id="imagen" name="imagen" accept="image/*" hidden>
                <img id="preview" style="display:none;max-width:100%;margin-top:10px;">
            </div>
        </div>
        <div class="campo">
            <button type="submit" id="btnEnviarTicket">Enviar Ticket</button>
        </div>
        <div id="respuestaCrear"></div>
    </form>
</div>

<script>
$(document).ready(function() {
    // Variable bandera para evitar múltiples envíos
    let enviando = false;

    // Sugiere artículos publicados según el texto del problema, sin bloquear el formulario.
    let timerSugerencias;
    let consultaSugerencias = 0;
    const descripcion = document.getElementById('descripcionTicket');
    const contenedorSugerencias = document.getElementById('sugerenciasAyuda');
    descripcion?.addEventListener('input', function() {
        document.getElementById('descripcionConteo').textContent = this.value.length.toLocaleString('es-MX') + ' / 5000';
        window.clearTimeout(timerSugerencias);
        const texto = this.value.trim();
        if (texto.length < 12) { contenedorSugerencias.hidden = true; contenedorSugerencias.replaceChildren(); return; }
        timerSugerencias = window.setTimeout(async () => {
            const consulta = ++consultaSugerencias;
            try {
                const url = new URL('ticket/sugerir_articulos.php', window.location.href);
                url.searchParams.set('q', texto);
                const response = await fetch(url, {credentials:'same-origin', headers:{'Accept':'application/json'}});
                const data = await response.json();
                if (consulta !== consultaSugerencias) return;
                const articulos = Array.isArray(data.articulos) ? data.articulos : [];
                contenedorSugerencias.replaceChildren();
                if (!articulos.length) { contenedorSugerencias.hidden = true; return; }
                const titulo = document.createElement('strong');
                titulo.textContent = 'Quizá esto te ayude';
                contenedorSugerencias.append(titulo);
                articulos.forEach(articulo => {
                    const elemento = document.createElement('article');
                    const nombre = document.createElement('b'); nombre.textContent = articulo.titulo || 'Guía';
                    const resumen = document.createElement('span'); resumen.textContent = articulo.resumen || 'Consulta esta guía en el Centro de ayuda.';
                    const categoria = document.createElement('small'); categoria.textContent = articulo.categoria || 'General';
                    elemento.append(nombre, resumen, categoria); contenedorSugerencias.append(elemento);
                });
                contenedorSugerencias.hidden = false;
            } catch (_) { contenedorSugerencias.hidden = true; }
        }, 350);
    });

    $(document).off('submit', '#formCrearTicket').on('submit', '#formCrearTicket', function(e) {
        e.preventDefault();
        
        // Bloqueo de seguridad: si ya se está enviando, no hacer nada
        if (enviando) return;
        
        // 1. Deshabilitar botón y cambiar texto
        let $btn = $('#btnEnviarTicket');
        $btn.prop('disabled', true).text('Enviando...');
        enviando = true;
        
        let formData = new FormData(this);
        
        $.ajax({
            url: '../../admin/tickets/backend/crear_ticket.php', // Corregido: el procesador real vive en admin/tickets/backend/
            type: 'POST',
            data: formData,
            contentType: false, 
            processData: false,
            success: function(res) {
                // 2. Éxito: Feedback y redirección
                $('#respuestaCrear').html("<div class='alert alert-success'>Ticket enviado con éxito. Redirigiendo...</div>");
                
                setTimeout(function() {
                    // Redirigir a la vista de "Mis Tickets" vía AJAX
                    $('.nav-link[data-page="ticket/mis_tickets.php"]').trigger('click');
                }, 1500);
            },
            error: function(xhr) {
                // 3. Error: Reactivar botón y quitar bloqueo
                enviando = false;
                $btn.prop('disabled', false).text('Enviar Ticket');
                let mensaje = 'Error al enviar. Intenta de nuevo.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    mensaje = xhr.responseJSON.error;
                }
                $('#respuestaCrear').html($('<div>', { class: 'alert alert-danger', text: mensaje }));
            }
        });
    });

    // Lógica de Imagen: Clic, Arrastre y Pegado (Ctrl+V)
    $('#dropArea').click(() => $('#imagen').click());
    
    $('#imagen').change(function() {
        if (this.files && this.files[0]) {
            let reader = new FileReader();
            reader.onload = (e) => $('#preview').attr('src', e.target.result).show();
            reader.readAsDataURL(this.files[0]);
        }
    });

    $(document).on('paste', function(e) {
        let item = e.originalEvent.clipboardData.items[0];
        if (item && item.type.startsWith('image')) {
            let file = item.getAsFile();
            let dt = new DataTransfer();
            dt.items.add(file);
            $('#imagen')[0].files = dt.files;
            let reader = new FileReader();
            reader.onload = (e) => $('#preview').attr('src', e.target.result).show();
            reader.readAsDataURL(file);
        }
    });
});
</script>
