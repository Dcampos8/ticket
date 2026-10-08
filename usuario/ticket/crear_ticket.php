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
<link rel="icon" href="https://ticket.transportesvaladez.com/img/icono.png">
<div class="form-header">
    <h2>
        <i class="fas fa-ticket-alt text-primary"></i>
        Crear Nuevo Ticket
    </h2>
    <p>Describe tu solicitud para que el equipo pueda atenderla rápidamente.</p>
</div>

<div class="ticket-page">
    <form id="formCrearTicket" enctype="multipart/form-data" class="form-container">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="usuario" value="<?= htmlspecialchars($_SESSION['usuario']) ?>">
        <input type="hidden" name="area" value="<?= htmlspecialchars($_SESSION['area']) ?>">
        <input type="hidden" name="nombre" value="<?= htmlspecialchars($_SESSION['nombre_completo']) ?>">

        <div class="ticket-card">
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
            <label>Descripción del problema <span style="color:red">*</span></label>
            <textarea name="descripcion" required rows="4" placeholder="Describe brevemente el problema..."></textarea>
        </div>
        <div class="campo">
            <label>Tipo de solicitud</label>
            <p class="text-muted mb-0">La IA analizará la descripción y asignará la categoría más adecuada.</p>
        </div>
        <div class="campo">
            <label>Imagen (opcional)</label>
            <div id="dropArea">
                <div id="uploadIcon">⬆️</div>
                <p id="dropText">Haz clic o arrastra una imagen<br><small>Pega con Ctrl + V</small></p>
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
