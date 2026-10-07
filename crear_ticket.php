<?php
session_start();
require_once '../../backend/conexion.php';

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    http_response_code(403);
    exit("Acceso no autorizado");
}
?>

<style>
.ticket-page{
    padding:20px;
}

.form-container{
    max-width:850px;
    margin:auto;
    background:#fff;
    border-radius:18px;
    padding:35px;
    box-shadow:
        0 10px 25px rgba(0,0,0,.08),
        0 2px 10px rgba(0,0,0,.04);
    border:1px solid #eef1f4;
}

.form-header{
    margin-bottom:30px;
    text-align:center;
}

.form-header h2{
    margin:0;
    font-size:28px;
    font-weight:700;
    color:var(--tv-ink,#0f172a);
}

.form-header p{
    color:#7f8c8d;
    margin-top:8px;
}

.campo{
    margin-bottom:20px;
}

.campo label{
    display:block;
    margin-bottom:8px;
    font-weight:600;
    color:var(--tv-text,#334155);
}

.campo input,
.campo select,
.campo textarea{
    width:100%;
    border:1px solid #dfe6e9;
    border-radius:12px;
    padding:12px 15px;
    font-size:15px;
    transition:.25s;
    background:#fafafa;
}

.campo input:focus,
.campo select:focus,
.campo textarea:focus{
    outline:none;
    border-color:var(--tv-navy,#1e3a8a);
    box-shadow:0 0 0 4px rgba(30,58,138,.12);
    background:#fff;
}

.campo input:disabled{
    background:#f5f7fa;
    color:#495057;
}

.user-grid{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
    gap:15px;
}

#dropArea{
    border:2px dashed var(--tv-navy,#1e3a8a);
    border-radius:15px;
    padding:35px;
    background:#f5f7fc;
    transition:.3s;
    text-align:center;
}

#dropArea:hover{
    background:#eaeffb;
    transform:translateY(-2px);
}

#uploadIcon{
    font-size:42px;
    margin-bottom:10px;
}

#dropText{
    color:#6c757d;
    margin-bottom:0;
}

#preview{
    max-width:250px;
    max-height:250px;
    border-radius:12px;
    margin-top:15px;
    box-shadow:0 5px 15px rgba(0,0,0,.12);
}

#btnEnviarTicket{
    width:100%;
    border:none;
    border-radius:12px;
    padding:14px;
    font-size:16px;
    font-weight:600;
    background:var(--tv-navy,#1e3a8a);
    color:white;
    transition:.3s;
}

#btnEnviarTicket:hover{
    transform:translateY(-2px);
    box-shadow:0 8px 20px rgba(30,58,138,.3);
}

#btnEnviarTicket:disabled{
    opacity:.8;
    cursor:wait;
}

#respuestaCrear{
    margin-top:15px;
}

.ticket-card{
    background:#f8fafc;
    border-radius:14px;
    padding:18px;
    border:1px solid #e9ecef;
    margin-bottom:25px;
}
</style>
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
            <p class="text-muted mb-0">Lo asignaremos automáticamente según la descripción que escribas.</p>
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
            url: '../backend/crear_ticket.php', // Ajusta la ruta según donde esté tu procesador
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
            error: function() {
                // 3. Error: Reactivar botón y quitar bloqueo
                enviando = false;
                $btn.prop('disabled', false).text('Enviar Ticket');
                $('#respuestaCrear').html("<div class='alert alert-danger'>Error al enviar. Intenta de nuevo.</div>");
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
