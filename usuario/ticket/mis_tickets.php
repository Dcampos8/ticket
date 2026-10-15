<?php
session_start();
require_once '../../shared/security.php';
asegurarTokenCsrf();

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'usuario') {
    http_response_code(403);
    echo "Acceso no autorizado";
    exit();
}

include '../../backend/conexion.php';

$usuario = $_SESSION['usuario'];

// 🔹 Si el usuario tiene permiso especial, ve todos los tickets
if (!empty($_SESSION['puede_ver_todos_tickets']) && $_SESSION['puede_ver_todos_tickets'] == 1) {
    $sql = "SELECT t.id, t.nombre, t.descripcion, t.estatus, t.fecha_modificacion, t.fecha_creacion, t.comentario_admin, t.imagen, t.imagen_admin, t.archivo_admin,
                   (e.id IS NOT NULL) AS ya_calificado
            FROM tickets t
            LEFT JOIN encuestas_satisfaccion e ON e.tipo = 'ticket' AND e.referencia_id = t.id
            ORDER BY t.fecha_creacion DESC";
    $stmt = $conexion->prepare($sql);
} else {
    // 🔹 Si no, solo ve sus propios tickets
    $sql = "SELECT t.id, t.nombre, t.descripcion, t.estatus, t.fecha_modificacion, t.fecha_creacion, t.comentario_admin, t.imagen, t.imagen_admin, t.archivo_admin,
                   (e.id IS NOT NULL) AS ya_calificado
            FROM tickets t
            LEFT JOIN encuestas_satisfaccion e ON e.tipo = 'ticket' AND e.referencia_id = t.id
            WHERE t.nombre = ? 
            ORDER BY t.fecha_creacion DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $usuario);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!-- ✅ Estilo Moderno y Minimalista -->
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__ticket__mis_tickets.css?v=<?= filemtime(__DIR__ . '/../../css/pages/usuario__ticket__mis_tickets.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<link rel="icon" href="https://ticket.transportesvaladez.com/img/icono.png">
<script>
window.ticketActual = null;
window.usuarioActual = <?= json_encode((string) ($_SESSION['usuario'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.ticketCsrfToken = <?= json_encode(asegurarTokenCsrf(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
window.ticketChatFirma = '';
window.intervaloChatUsuario = null;

function abrirChat(id) {
    window.ticketActual = id;
    window.ticketChatFirma = '';
    document.getElementById('chatTitulo').textContent = 'Conversación · Ticket #' + id;
    document.getElementById('chatFlotante').hidden = false;
    document.getElementById('chatMensaje').focus();
    cargarChat();
    window.clearInterval(window.intervaloChatUsuario);
    window.intervaloChatUsuario = window.setInterval(cargarChat, 5000);
}

function cerrarChat() {
    document.getElementById('chatFlotante').hidden = true;
    window.ticketActual = null;
    window.clearInterval(window.intervaloChatUsuario);
}

function alternarTextoChat(texto, boton) {
    const expandido = texto.classList.toggle('is-expanded');
    texto.classList.toggle('is-collapsed', !expandido);
    boton.textContent = expandido ? 'Ver menos' : 'Ver más';
    boton.setAttribute('aria-expanded', expandido ? 'true' : 'false');
}

async function cargarChat() {
    if (!window.ticketActual) return;
    const cont = document.getElementById('chatMensajes');
    try {
        const response = await fetch('../../admin/tickets/backend/obtener_chat.php?ticket_id=' + window.ticketActual, {cache: 'no-store', credentials: 'same-origin'});
        if (!response.ok) throw new Error('No se pudo cargar la conversación.');
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error(data.msg || 'Respuesta inválida del servidor.');
        const firma = JSON.stringify(data);
        if (firma === window.ticketChatFirma) return;
        const cercaDelFinal = cont.scrollHeight - cont.scrollTop - cont.clientHeight < 100;
        window.ticketChatFirma = firma;
        cont.replaceChildren();
        if (!data.length) {
            const vacio = document.createElement('p'); vacio.className = 'chat-vacio'; vacio.textContent = 'Aún no hay mensajes. Escribe aquí para contactar a soporte.'; cont.append(vacio);
        }
        data.forEach(m => {
            const mensaje = String(m.mensaje || '');
            const lineas = mensaje.split(/\r?\n/).length;
            const largo = mensaje.length > 180 || lineas > 4;
            const item = document.createElement('article');
            item.className = 'chat-message ' + (String(m.usuario || '').trim().toLowerCase() === String(window.usuarioActual).trim().toLowerCase() ? 'user' : 'admin');
            const autor = document.createElement('strong'); autor.className = 'chat-message-author';
            autor.textContent = item.classList.contains('user') ? 'Tú' : 'Soporte';
            const texto = document.createElement('div'); texto.className = 'chat-message-text' + (largo ? ' is-collapsed' : ''); texto.textContent = mensaje;
            item.append(autor, texto);
            if (largo) {
                const toggle = document.createElement('button'); toggle.type = 'button'; toggle.className = 'chat-message-toggle';
                toggle.textContent = 'Ver más'; toggle.setAttribute('aria-expanded', 'false');
                toggle.addEventListener('click', () => alternarTextoChat(texto, toggle)); item.append(toggle);
            }
            const fecha = document.createElement('small'); fecha.className = 'chat-message-date'; fecha.textContent = m.fecha || ''; item.append(fecha);
            cont.append(item);
        });
        if (cercaDelFinal) cont.scrollTop = cont.scrollHeight;
        document.getElementById('chatError').textContent = '';
    } catch (error) { document.getElementById('chatError').textContent = error.message || 'Error de conexión.'; }
}

function conectarFormularioChat() {
    const formulario = document.getElementById('chatForm');
    if (!formulario) return;
    formulario.onsubmit = async event => {
        event.preventDefault();
        const input = document.getElementById('chatMensaje');
        const text = input.value.trim();
        if (!window.ticketActual || !text) { input.focus(); return; }
        const boton = document.getElementById('chatEnviar'); boton.disabled = true;
        document.getElementById('chatError').textContent = '';
        try {
            const response = await fetch('../../admin/tickets/backend/enviar_chat.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ticket_id: String(window.ticketActual), mensaje: text, csrf_token: window.ticketCsrfToken})});
            const result = await response.json();
            if (!response.ok || result.status !== 'ok') throw new Error(result.msg || 'No se pudo enviar el mensaje.');
            input.value = ''; window.ticketChatFirma = ''; await cargarChat(); input.focus();
        } catch (error) { document.getElementById('chatError').textContent = error.message || 'Error al enviar el mensaje.'; }
        finally { boton.disabled = false; }
    };
}
</script>

<div class="tickets-header">
    <h3>
        <i class="fas fa-ticket-alt text-primary"></i>
        Mis Tickets
    </h3>
</div>

<div class="ticket-grid">

<?php if($result->num_rows > 0): ?>

<?php while($row = $result->fetch_assoc()): ?>
<?php
$descripcionUsuario = trim((string) ($row['descripcion'] ?? ''));
$descripcionUsuarioLarga = mb_strlen($descripcionUsuario, 'UTF-8') > 140 || count(preg_split('/\R/u', $descripcionUsuario)) > 3;
?>

<div class="ticket-card">

    <div class="ticket-top">

        <div class="ticket-id">
            Ticket #<?= $row['id'] ?>
        </div>

        <div>
            <span class="badge-ticket
                <?= $row['estatus']=='Pendiente' ? 'badge-pendiente':'' ?>
                <?= $row['estatus']=='En proceso' ? 'badge-proceso':'' ?>
                <?= $row['estatus']=='Finalizado' ? 'badge-finalizado':'' ?>
                <?= $row['estatus']=='Cancelado' ? 'badge-cancelado':'' ?>">
                <?= htmlspecialchars($row['estatus']) ?>
            </span>
        </div>

    </div>

    <div id="ticket-desc-<?= (int) $row['id'] ?>" class="ticket-desc<?= $descripcionUsuarioLarga ? ' is-collapsed' : '' ?>">
        <?= nl2br(htmlspecialchars($descripcionUsuario !== '' ? $descripcionUsuario : 'Sin descripción')) ?>
    </div>
    <?php if ($descripcionUsuarioLarga): ?>
        <button type="button" class="ticket-desc-toggle" aria-expanded="false" aria-controls="ticket-desc-<?= (int) $row['id'] ?>" onclick="toggleDescripcionTicket(<?= (int) $row['id'] ?>, this)">Ver más</button>
    <?php endif; ?>

    <div class="ticket-meta">

        <div class="meta-box">
            <div class="meta-title">Fecha creación</div>
            <div class="meta-value">
                <?= date('d/m/Y H:i', strtotime($row['fecha_creacion'])) ?>
            </div>
        </div>

        <div class="meta-box">
            <div class="meta-title">Última actualización</div>
            <div class="meta-value">
                <?= $row['fecha_modificacion']
                    ? date('d/m/Y H:i', strtotime($row['fecha_modificacion']))
                    : 'Sin cambios' ?>
            </div>
        </div>

    </div>

    <div class="ticket-meta">

        <div class="meta-box">
            <div class="meta-title">Comentario del administrador</div>
            <div class="meta-value">
                <?= !empty($row['comentario_admin'])
                    ? htmlspecialchars($row['comentario_admin'])
                    : 'Sin comentarios' ?>
            </div>
        </div>

    </div>

    <div style="margin-top:15px;">
        <div class="meta-title">Archivos</div>

        <div class="archivos">

            <?php if(!empty($row['imagen'])): ?>
                <a class="archivo-btn"
                   href="../../uploads/<?= htmlspecialchars($row['imagen']) ?>"
                   target="_blank">
                    <i class="fas fa-paperclip"></i>
                    Archivo Usuario
                </a>
            <?php endif; ?>

            <?php
            $archivoAdmin = $row['imagen_admin'] ?: $row['archivo_admin'] ?: '';
            if(!empty($archivoAdmin)):
            ?>
                <a class="archivo-btn"
                   href="../../uploads/<?= htmlspecialchars($archivoAdmin) ?>"
                   target="_blank">
                    <i class="fas fa-file-download"></i>
                    Archivo Admin
                </a>
            <?php endif; ?>

        </div>
    </div>

    <div class="ticket-actions">

        <button type="button" class="btn-chat-ticket" onclick="abrirChat(<?= (int) $row['id'] ?>)">
            <i class="fas fa-comments"></i> Abrir chat
        </button>

        <?php if($row['estatus']=='Pendiente'): ?>

            <button
                class="btn-cancelar"
                onclick="cancelarTicket(<?= $row['id'] ?>)">
                Cancelar Ticket
            </button>

        <?php endif; ?>

        <?php if(in_array($row['estatus'], ['Finalizado','Cerrado']) && !$row['ya_calificado']): ?>

            <button
                class="btn btn-warning fw-bold"
                onclick='abrirEncuestaManual("ticket", <?= (int)$row['id'] ?>, "Ticket #<?= (int)$row['id'] ?>", <?= json_encode(mb_strimwidth($row['descripcion'], 0, 120, "...")) ?>, "¿Se resolvió tu problema?")'>
                <i class="fa-solid fa-star"></i> Calificar
            </button>

        <?php endif; ?>

    </div>

</div>

<?php endwhile; ?>

<?php else: ?>

<div class="sin-tickets">
    <i class="fas fa-inbox fa-4x mb-3"></i>
    <h4>No tienes tickets registrados</h4>
</div>

<?php endif; ?>

</div>

<section id="chatFlotante" class="ticket-chat-panel" role="dialog" aria-modal="true" aria-labelledby="chatTitulo" hidden>
    <header class="ticket-chat-header">
        <div><span class="chat-eyebrow">SOPORTE</span><h2 id="chatTitulo">Conversación</h2></div>
        <button type="button" class="chat-close" onclick="cerrarChat()" aria-label="Cerrar chat"><i class="fas fa-times"></i></button>
    </header>
    <div id="chatMensajes" class="ticket-chat-messages" aria-live="polite"><p class="chat-vacio">Cargando conversación…</p></div>
    <div id="chatError" class="ticket-chat-error" role="alert"></div>
    <form id="chatForm" class="ticket-chat-composer">
        <input type="text" id="chatMensaje" maxlength="5000" autocomplete="off" placeholder="Escribe un mensaje…" aria-label="Mensaje para soporte">
        <button type="submit" id="chatEnviar" aria-label="Enviar mensaje"><i class="fas fa-paper-plane"></i></button>
    </form>
</section>

<script>
conectarFormularioChat();

function toggleDescripcionTicket(id, button) {
    const descripcion = document.getElementById('ticket-desc-' + id);
    if (!descripcion) return;
    const expandida = descripcion.classList.toggle('is-expanded');
    descripcion.classList.toggle('is-collapsed', !expandida);
    button.textContent = expandida ? 'Ver menos' : 'Ver más';
    button.setAttribute('aria-expanded', expandida ? 'true' : 'false');
}

function cancelarTicket(id){
    if(!confirm("¿Estás seguro de cancelar este ticket? Esta acción no se puede deshacer.")){
        return;
    }

    fetch("../../admin/tickets/backend/cancelar_ticket.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: new URLSearchParams({ id: String(id), csrf_token: window.ticketCsrfToken })
    })
    .then(r => r.json())
    .then(res => {
        if(res.success){
            alert("Ticket cancelado correctamente");
            location.reload();
        }else{
            alert(res.error || "No se pudo cancelar el ticket");
        }
    });
}
</script>

<?php $conexion->close(); ?>
