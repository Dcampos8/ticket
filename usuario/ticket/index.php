<?php
session_start();
include '../menu.php';
?>
<link rel="stylesheet" href="../css/ticket_form.css?v=3">
<link rel="icon" href="https://ticket.transportesvaladez.com/img/icono.png">
<script src="https://code.jquery.com/jquery-3.6.0.min.js?v=3"></script>
<script>
$(document).ready(function () {

    var dropArea = $('#dropArea');
    var fileInput = $('#imagen');
    var preview = $('#preview');
    var dropText = $('#dropText');
    var uploadIcon = $('#uploadIcon');
    var archivoSeleccionado = null;
    var maxSize = 5 * 1024 * 1024;

    dropArea.on('click', function () {
        fileInput.click();
    });

    fileInput.on('change', function () {
        if (this.files.length > 0) {
            procesarArchivo(this.files[0]);
        }
    });

    $(document).on('paste', function (e) {

        var items = (e.originalEvent.clipboardData || window.clipboardData).items;

        for (var i = 0; i < items.length; i++) {

            if (items[i].type.indexOf("image") !== -1) {
                procesarArchivo(items[i].getAsFile());
            }
        }
    });

    function procesarArchivo(file) {

        if (file.size > maxSize) {
            alert("La imagen supera los 5 MB.");
            return;
        }

        archivoSeleccionado = file;

        var reader = new FileReader();

        reader.onload = function (e) {
            preview.attr('src', e.target.result).show();
            uploadIcon.hide();
            dropText.text("Imagen lista para enviar ✅");
        };

        reader.readAsDataURL(file);
    }

    $('#formCrearTicket').submit(function (e) {

        e.preventDefault();

        var btnEnviar = $('#btnEnviarTicket');
        btnEnviar.prop('disabled', true).text('Enviando...');

        var formData = new FormData(this);

        if (archivoSeleccionado) {
            formData.set('imagen', archivoSeleccionado);
        }

        $.ajax({
            url: '../../admin/tickets/backend/crear_ticket.php',
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,

            success: function () {
                $('#respuestaCrear').html(
                    '<div style="color:green;font-weight:bold;">✅ Ticket enviado.</div>'
                );

                setTimeout(function () {
                    location.reload();
                }, 1000);
            },

            error: function () {
                $('#respuestaCrear').html(
                    '<div style="color:red;font-weight:bold;">❌ Error al enviar. Intenta nuevamente.</div>'
                );

                btnEnviar.prop('disabled', false).text('Enviar Ticket');
            }
        });
    });
});
</script>