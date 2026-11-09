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
window.intervaloChatActivo = null;
window.ticketChatVigilados = [];
window.ticketAudioContext = null;
window.ticketChatSonidoActivo = leerPreferenciaSonidoChat();
window.ticketChatSonidoPendiente = false;
window.ticketChatMemoria = window.ticketChatMemoria || {};

function leerPreferenciaSonidoChat() {
    try { return window.localStorage.getItem('ticket_chat_sonido') !== '0'; } catch (_) { return true; }
}

function firmaUltimoMensajeSoporte(mensajes) {
    const entrante = [...mensajes].reverse().find(m => String(m.usuario || '').trim().toLowerCase() !== String(window.usuarioActual).trim().toLowerCase());
    return entrante ? JSON.stringify([entrante.usuario, entrante.fecha, entrante.mensaje]) : '';
}

function claveChatLocal(id, dato) { return 'ticket_chat_' + dato + '_' + String(id); }

function leerDatoChat(id, dato) {
    const clave = claveChatLocal(id, dato);
    try {
        const valor = window.localStorage.getItem(clave);
        if (valor !== null) return valor;
    } catch (_) {}
    return window.ticketChatMemoria[clave] || null;
}

function guardarDatoChat(id, dato, valor) {
    const clave = claveChatLocal(id, dato);
    window.ticketChatMemoria[clave] = String(valor);
    try { window.localStorage.setItem(clave, String(valor)); } catch (_) {}
}

function mostrarIndicadorChat(id, nuevo) {
    const boton = document.querySelector('.btn-chat-ticket[data-ticket-id="' + String(id) + '"]');
    if (!boton) return;
    const punto = boton.querySelector('.chat-unread-dot');
    if (punto) punto.hidden = !nuevo;
    boton.classList.toggle('has-new-message', nuevo);
    boton.setAttribute('aria-label', nuevo ? 'Abrir chat del ticket ' + id + '. Hay un mensaje nuevo de soporte.' : 'Abrir chat del ticket ' + id);
    boton.title = nuevo ? 'Tienes un mensaje nuevo de soporte' : 'Abrir conversación con soporte';
}

function actualizarControlSonidoChat() {
    const boton = document.getElementById('chatSonidoControl');
    if (!boton) return;
    boton.innerHTML = window.ticketChatSonidoActivo
        ? '<i class="fas fa-volume-up" aria-hidden="true"></i><span>Probar sonido</span>'
        : '<i class="fas fa-volume-mute" aria-hidden="true"></i><span>Activar sonido</span>';
    boton.setAttribute('aria-pressed', window.ticketChatSonidoActivo ? 'true' : 'false');
    boton.title = window.ticketChatSonidoActivo ? 'Probar notificación sonora' : 'Activar notificaciones sonoras';
}

function activarSonidoChat(probar = false) {
    try {
        const ContextoAudio = window.AudioContext || window.webkitAudioContext;
        if (!ContextoAudio) { window.ticketChatSonidoActivo = false; actualizarControlSonidoChat(); return; }
        if (!window.ticketAudioContext) window.ticketAudioContext = new ContextoAudio();
        const audio = window.ticketAudioContext;
        const habilitar = audio.state === 'suspended' ? audio.resume() : Promise.resolve();
        habilitar.then(() => {
            window.ticketChatSonidoActivo = true;
            try { window.localStorage.setItem('ticket_chat_sonido', '1'); } catch (_) {}
            actualizarControlSonidoChat();
            if (probar || window.ticketChatSonidoPendiente) {
                window.ticketChatSonidoPendiente = false;
                sonarMensajeNuevo();
            }
        }).catch(() => { window.ticketChatSonidoActivo = false; actualizarControlSonidoChat(); });
    } catch (_) {}
}

function alternarSonidoChat() {
    if (window.ticketChatSonidoActivo) {
        activarSonidoChat(true);
        return;
    }
    activarSonidoChat(true);
}

function sonarMensajeNuevo() {
    const audio = window.ticketAudioContext;
    if (!window.ticketChatSonidoActivo) return;
    if (!audio || audio.state !== 'running') { window.ticketChatSonidoPendiente = true; return; }
    const inicio = audio.currentTime;
    const oscilador = audio.createOscillator();
    const volumen = audio.createGain();
    oscilador.type = 'sine';
    oscilador.frequency.setValueAtTime(740, inicio);
    oscilador.frequency.setValueAtTime(880, inicio + 0.09);
    volumen.gain.setValueAtTime(0.0001, inicio);
    volumen.gain.exponentialRampToValueAtTime(0.12, inicio + 0.015);
    volumen.gain.exponentialRampToValueAtTime(0.0001, inicio + 0.28);
    oscilador.connect(volumen); volumen.connect(audio.destination);
    oscilador.start(inicio); oscilador.stop(inicio + 0.3);
}

async function revisarChatsVigilados() {
    const ids = window.ticketChatVigilados.filter(id => String(window.ticketActual) !== String(id));
    if (!ids.length || document.hidden || window.ticketRevisandoVigilados) return;
    window.ticketRevisandoVigilados = true;
    try {
        const response = await fetch('../../admin/tickets/backend/obtener_novedades_chat_usuario.php?ticket_ids=' + encodeURIComponent(ids.join(',')), {cache: 'no-store', credentials: 'same-origin'});
        if (!response.ok) return;
        const respuesta = await response.json();
        if (respuesta.status !== 'ok' || !respuesta.tickets) return;
        Object.entries(respuesta.tickets).forEach(([id, mensaje]) => {
            const firmaEntrante = mensaje ? firmaUltimoMensajeSoporte([mensaje]) : '';
            const firmaVista = leerDatoChat(id, 'ultimo_soporte_visto');
            const inicializado = leerDatoChat(id, 'chat_inicializado') === '1';
            if (!inicializado) {
                guardarDatoChat(id, 'ultimo_soporte_visto', firmaEntrante);
                guardarDatoChat(id, 'chat_inicializado', '1');
            } else if (firmaEntrante && firmaVista !== firmaEntrante) {
                guardarDatoChat(id, 'ultimo_soporte_visto', firmaEntrante);
                guardarDatoChat(id, 'no_leido', '1');
                mostrarIndicadorChat(id, true);
                sonarMensajeNuevo();
            } else {
                mostrarIndicadorChat(id, leerDatoChat(id, 'no_leido') === '1');
            }
        });
    } catch (_) {} finally { window.ticketRevisandoVigilados = false; }
}

function abrirChat(id) {
    window.ticketActual = id;
    if (!window.ticketChatVigilados.includes(String(id))) window.ticketChatVigilados.push(String(id));
    activarSonidoChat();
    window.ticketChatFirma = '';
    document.getElementById('chatTitulo').textContent = 'Conversación · Ticket #' + id;
    document.getElementById('chatFlotante').hidden = false;
    document.getElementById('chatMensaje').focus();
    cargarChat();
    window.clearInterval(window.intervaloChatActivo);
    window.intervaloChatActivo = window.setInterval(cargarChat, 120000);
    if (!window.intervaloChatUsuario) window.intervaloChatUsuario = window.setInterval(revisarChatsVigilados, 300000);
}

function cerrarChat() {
    document.getElementById('chatFlotante').hidden = true;
    window.ticketActual = null;
    window.clearInterval(window.intervaloChatActivo);
    window.intervaloChatActivo = null;
}

function alternarTextoChat(texto, boton) {
    const expandido = texto.classList.toggle('is-expanded');
    texto.classList.toggle('is-collapsed', !expandido);
    boton.textContent = expandido ? 'Ver menos' : 'Ver más';
    boton.setAttribute('aria-expanded', expandido ? 'true' : 'false');
}

async function cargarChat() {
    if (!window.ticketActual || document.hidden || window.ticketChatCargando) return;
    window.ticketChatCargando = true;
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
        guardarDatoChat(window.ticketActual, 'ultimo_soporte_visto', firmaUltimoMensajeSoporte(data));
        guardarDatoChat(window.ticketActual, 'chat_inicializado', '1');
        guardarDatoChat(window.ticketActual, 'no_leido', '0');
        mostrarIndicadorChat(window.ticketActual, false);
        document.getElementById('chatError').textContent = '';
    } catch (error) { document.getElementById('chatError').textContent = error.message || 'Error de conexión.'; }
    finally { window.ticketChatCargando = false; }
}

function conectarFormularioChat() {
    const formulario = document.getElementById('chatForm');
    if (!formulario) return;
    const botonesTicket = Array.from(document.querySelectorAll('.btn-chat-ticket[data-ticket-id]'));
    const idsAbiertos = botonesTicket.filter(boton => ['Pendiente', 'En proceso'].includes(boton.dataset.chatStatus)).map(boton => String(boton.dataset.ticketId));
    const idsRecientes = botonesTicket.slice(0, 10).map(boton => String(boton.dataset.ticketId));
    window.ticketChatVigilados = Array.from(new Set(idsAbiertos.concat(idsRecientes)));
    botonesTicket.forEach(boton => mostrarIndicadorChat(boton.dataset.ticketId, leerDatoChat(boton.dataset.ticketId, 'no_leido') === '1'));
    revisarChatsVigilados();
    if (!window.intervaloChatUsuario) window.intervaloChatUsuario = window.setInterval(revisarChatsVigilados, 300000);
    actualizarControlSonidoChat();
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

document.addEventListener('visibilitychange', () => {
    if (document.hidden) return;
    if (window.ticketActual) cargarChat();
    revisarChatsVigilados();
});
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

        <button type="button" class="btn-chat-ticket" data-ticket-id="<?= (int) $row['id'] ?>" data-chat-status="<?= htmlspecialchars($row['estatus'], ENT_QUOTES, 'UTF-8') ?>" aria-label="Abrir chat del ticket <?= (int) $row['id'] ?>" title="Abrir conversación con soporte" onclick="abrirChat(<?= (int) $row['id'] ?>)">
            <i class="fas fa-comments" aria-hidden="true"></i> Abrir chat <span class="chat-unread-dot" hidden aria-hidden="true"></span>
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

<section id="chatFlotante" class="ticket-chat-panel" role="dialog" aria-labelledby="chatTitulo" hidden>
    <header class="ticket-chat-header">
        <div><span class="chat-eyebrow">SOPORTE</span><h2 id="chatTitulo">Conversación</h2></div>
        <div class="chat-header-actions"><button type="button" id="chatSonidoControl" class="chat-sound-toggle" onclick="alternarSonidoChat()" aria-label="Activar o probar sonido"></button><button type="button" class="chat-close" onclick="cerrarChat()" aria-label="Cerrar chat"><i class="fas fa-times"></i></button></div>
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
