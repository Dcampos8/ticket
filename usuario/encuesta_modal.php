<!-- Modal de Encuesta de Satisfacción (tickets finalizados / mantenimientos realizados) -->
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__encuesta_modal.css?v=<?= filemtime(__DIR__ . '/../css/pages/usuario__encuesta_modal.css') ?>">

<div class="modal fade" id="modalEncuesta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fa-solid fa-star me-2"></i><span id="encuestaTitulo">Encuesta de satisfacción</span></h5>
      </div>
      <div class="modal-body">
        <p id="encuestaDetalle" class="text-muted small"></p>

        <label class="form-label fw-bold d-block text-center">¿Cómo calificarías el servicio?</label>
        <div class="estrellas-encuesta" id="estrellasEncuesta">
            <input type="radio" name="calificacion" id="e5" value="5"><label for="e5" title="5"><i class="fa-solid fa-star"></i></label>
            <input type="radio" name="calificacion" id="e4" value="4"><label for="e4" title="4"><i class="fa-solid fa-star"></i></label>
            <input type="radio" name="calificacion" id="e3" value="3"><label for="e3" title="3"><i class="fa-solid fa-star"></i></label>
            <input type="radio" name="calificacion" id="e2" value="2"><label for="e2" title="2"><i class="fa-solid fa-star"></i></label>
            <input type="radio" name="calificacion" id="e1" value="1"><label for="e1" title="1"><i class="fa-solid fa-star"></i></label>
        </div>

        <label class="form-label fw-bold" id="encuestaPreguntaResuelto">¿Se resolvió tu problema?</label>
        <div class="btn-group w-100 mb-3" role="group">
            <input type="radio" class="btn-check" name="resuelto" id="resueltoSi" value="Si">
            <label class="btn btn-outline-success" for="resueltoSi"><i class="fa-solid fa-check me-1"></i>Sí</label>

            <input type="radio" class="btn-check" name="resuelto" id="resueltoNo" value="No">
            <label class="btn btn-outline-danger" for="resueltoNo"><i class="fa-solid fa-xmark me-1"></i>No</label>
        </div>

        <label class="form-label fw-bold">Comentarios (opcional)</label>
        <textarea class="form-control" id="encuestaComentarios" rows="3" placeholder="Cuéntanos más..."></textarea>

        <div id="encuestaError" class="text-danger small mt-2" style="display:none;"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" id="btnEncuestaDespues">Responder después</button>
        <button type="button" class="btn btn-primary" id="btnEncuestaEnviar">
            <i class="fa-solid fa-paper-plane me-1"></i>Enviar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
let colaEncuestas = [];
let encuestaActual = null;
let modalEncuestaEl = null;

function iniciarEncuestas(baseUrl) {
    modalEncuestaEl = new bootstrap.Modal(document.getElementById('modalEncuesta'));

    fetch(baseUrl + 'backend/encuestas_pendientes.php')
        .then(r => r.json())
        .then(data => {
            colaEncuestas = Array.isArray(data) ? data : [];
            mostrarSiguienteEncuesta();
        })
        .catch(() => {});
}

function mostrarSiguienteEncuesta() {
    if (colaEncuestas.length === 0) return;
    encuestaActual = colaEncuestas.shift();
    abrirModalEncuesta(encuestaActual);
}

// Permite abrir una encuesta específica manualmente (ej. botón "Calificar" en Mis Tickets)
function abrirEncuestaManual(tipo, referencia_id, titulo, detalle, pregunta_resuelto) {
    abrirModalEncuesta({ tipo, referencia_id, titulo, detalle, pregunta_resuelto });
}

function abrirModalEncuesta(item) {
    document.getElementById('encuestaTitulo').innerText = item.titulo || 'Encuesta de satisfacción';
    document.getElementById('encuestaDetalle').innerText = item.detalle || '';
    document.getElementById('encuestaPreguntaResuelto').innerText = item.pregunta_resuelto || '¿Se resolvió tu problema?';
    document.getElementById('encuestaComentarios').value = '';
    document.getElementById('encuestaError').style.display = 'none';
    document.querySelectorAll('#estrellasEncuesta input').forEach(r => r.checked = false);
    document.querySelectorAll('input[name="resuelto"]').forEach(r => r.checked = false);
    encuestaActual = item;
    modalEncuestaEl.show();
}

document.addEventListener('DOMContentLoaded', function () {
    const btnDespues = document.getElementById('btnEncuestaDespues');
    const btnEnviar = document.getElementById('btnEncuestaEnviar');

    if (btnDespues) {
        btnDespues.addEventListener('click', function () {
            modalEncuestaEl.hide();
            // No se marca como respondida: seguirá apareciendo el botón "Calificar"
            // y, en el próximo inicio de sesión, el aviso automático.
            setTimeout(mostrarSiguienteEncuesta, 400);
        });
    }

    if (btnEnviar) {
        btnEnviar.addEventListener('click', function () {
            const calificacion = document.querySelector('#estrellasEncuesta input:checked');
            const resuelto = document.querySelector('input[name="resuelto"]:checked');
            const errorEl = document.getElementById('encuestaError');

            if (!calificacion || !resuelto) {
                errorEl.innerText = 'Por favor selecciona una calificación y responde la pregunta.';
                errorEl.style.display = 'block';
                return;
            }

            const body = new URLSearchParams({
                tipo: encuestaActual.tipo,
                referencia_id: encuestaActual.referencia_id,
                calificacion: calificacion.value,
                resuelto: resuelto.value,
                comentarios: document.getElementById('encuestaComentarios').value
            });

            fetch(rutaBackend() + 'guardar_encuesta.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    modalEncuestaEl.hide();
                    setTimeout(mostrarSiguienteEncuesta, 400);
                    if (typeof onEncuestaGuardada === 'function') onEncuestaGuardada(encuestaActual);
                } else {
                    errorEl.innerText = res.message || 'No se pudo guardar tu evaluación.';
                    errorEl.style.display = 'block';
                }
            })
            .catch(() => {
                errorEl.innerText = 'Error de conexión, intenta de nuevo.';
                errorEl.style.display = 'block';
            });
        });
    }
});

// Debe coincidir con la ruta relativa a /backend/ usada por cada página que incluye este modal
function rutaBackend() {
    return window.RUTA_BACKEND_ENCUESTAS || '../backend/';
}
</script>
