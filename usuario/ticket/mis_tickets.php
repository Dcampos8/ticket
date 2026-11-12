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
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$totalTickets = count($tickets);
$ticketsAbiertos = count(array_filter($tickets, static fn($ticket) => in_array($ticket['estatus'], ['Pendiente', 'En proceso'], true)));
?>

<!-- ✅ Estilo Moderno y Minimalista -->
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/usuario__ticket__mis_tickets.css?v=<?= filemtime(__DIR__ . '/../../css/pages/usuario__ticket__mis_tickets.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<link rel="icon" type="image/svg+xml" href="../../img/favicon.svg?v=2">
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
    const boton = document.querySelector('.ticket-row [data-ticket-id="' + String(id) + '"][data-chat-status]');
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
    const botonesTicket = Array.from(document.querySelectorAll('.ticket-row [data-ticket-id][data-chat-status]'));
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

<?php $estatusClase = static function (string $estatus): string {
    return match ($estatus) {
        'Pendiente' => 'badge-pendiente', 'En proceso' => 'badge-proceso',
        'Finalizado', 'Resuelto', 'Cerrado' => 'badge-finalizado', 'Cancelado' => 'badge-cancelado',
        default => 'badge-proceso',
    };
}; ?>
<section class="mis-tickets-shell" id="misTicketsShell" data-total="<?= $totalTickets ?>">
    <header class="tickets-header">
        <div><span class="tickets-kicker">SOPORTE · SEGUIMIENTO</span><h3><i class="fas fa-ticket-alt"></i> Mis tickets</h3><p>Consulta el avance y continúa la conversación con soporte.</p></div>
        <div class="tickets-summary"><strong><?= $totalTickets ?></strong><span>tickets en total</span><strong><?= $ticketsAbiertos ?></strong><span>en atención</span></div>
    </header>
    <div class="tickets-toolbar">
        <div class="ticket-tabs" role="tablist" aria-label="Filtrar tickets por estatus">
            <button class="ticket-tab active" type="button" role="tab" aria-selected="true" data-ticket-filter="todos">Todos <span><?= $totalTickets ?></span></button>
            <?php foreach (['Pendiente' => 'Pendientes', 'En proceso' => 'En proceso', 'Resuelto' => 'Resueltos', 'Cancelado' => 'Cancelados'] as $statusKey => $statusLabel): ?>
                <button class="ticket-tab" type="button" role="tab" aria-selected="false" data-ticket-filter="<?= htmlspecialchars($statusKey, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($statusLabel) ?> <span><?= count(array_filter($tickets, static fn($ticket) => $statusKey === 'Resuelto' ? in_array($ticket['estatus'], ['Resuelto', 'Finalizado', 'Cerrado'], true) : $ticket['estatus'] === $statusKey)) ?></span></button>
            <?php endforeach; ?>
        </div>
        <label class="ticket-search"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="buscarMisTickets" placeholder="Buscar por folio o solicitud" aria-label="Buscar tickets"></label>
    </div>
    <div class="tickets-table-wrap">
        <table class="tickets-table">
            <thead><tr><th>Folio</th><th>Solicitud</th><th>Fecha</th><th>Última actualización</th><th>Estado</th><th><span class="visually-hidden">Acción</span></th></tr></thead>
            <tbody id="misTicketsRows">
            <?php foreach ($tickets as $row): $resuelto = in_array($row['estatus'], ['Resuelto', 'Finalizado', 'Cerrado'], true); $tabStatus = $resuelto ? 'Resuelto' : $row['estatus']; ?>
                <tr class="ticket-row" data-ticket-id="<?= (int) $row['id'] ?>" data-status="<?= htmlspecialchars($tabStatus, ENT_QUOTES, 'UTF-8') ?>" data-search="<?= htmlspecialchars(mb_strtolower('#' . $row['id'] . ' ' . $row['descripcion'], 'UTF-8'), ENT_QUOTES, 'UTF-8') ?>">
                    <td class="ticket-folio">#<?= (int) $row['id'] ?></td>
                    <td class="ticket-request"><span><?= htmlspecialchars(trim((string) $row['descripcion']) ?: 'Sin descripción', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></span></td>
                    <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_creacion']))) ?></td>
                    <td><?= !empty($row['fecha_modificacion']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_modificacion']))) : '—' ?></td>
                    <td><span class="badge-ticket <?= $estatusClase((string) $row['estatus']) ?>"><?= htmlspecialchars($resuelto ? 'Resuelto' : (string) $row['estatus']) ?></span></td>
                    <td><button type="button" class="ticket-open" data-open-ticket="<?= (int) $row['id'] ?>" data-ticket-id="<?= (int) $row['id'] ?>" data-chat-status="<?= htmlspecialchars((string) $row['estatus'], ENT_QUOTES, 'UTF-8') ?>">Ver detalle <i class="fa-solid fa-arrow-right"></i><span class="chat-unread-dot" hidden aria-hidden="true"></span></button></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="tickets-empty" id="ticketsEmpty" <?= $totalTickets ? 'hidden' : '' ?>><span><i class="fa-regular fa-folder-open"></i></span><strong>No hay tickets en esta vista</strong><p>Cuando haya tickets con este estatus aparecerán aquí.</p></div>
    </div>
</section>

<div class="modal fade ticket-detail-modal" id="ticketDetailModal" tabindex="-1" aria-labelledby="ticketDetailTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><span class="tickets-kicker">DETALLE DE SOLICITUD</span><h2 class="modal-title" id="ticketDetailTitle">Ticket</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
        <div class="modal-body" id="ticketDetailBody"></div>
        <div class="modal-footer" id="ticketDetailActions"></div>
    </div></div>
</div>

<div class="ticket-templates" hidden>
<?php foreach ($tickets as $row): $resuelto = in_array($row['estatus'], ['Resuelto', 'Finalizado', 'Cerrado'], true); $archivoAdmin = $row['imagen_admin'] ?: $row['archivo_admin'] ?: ''; ?>
    <template id="ticket-template-<?= (int) $row['id'] ?>">
        <div class="ticket-detail-grid">
            <section class="ticket-detail-section ticket-detail-request"><span class="detail-label">SOLICITUD</span><p><?= nl2br(htmlspecialchars(trim((string) $row['descripcion']) ?: 'Sin descripción', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) ?></p></section>
            <section class="ticket-detail-section"><span class="detail-label">ESTADO</span><span class="badge-ticket <?= $estatusClase((string) $row['estatus']) ?>"><?= htmlspecialchars($resuelto ? 'Resuelto' : (string) $row['estatus']) ?></span><div class="detail-date"><span class="detail-label">CREADO</span><?= htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_creacion']))) ?></div><div class="detail-date"><span class="detail-label">ACTUALIZADO</span><?= !empty($row['fecha_modificacion']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['fecha_modificacion']))) : 'Sin cambios' ?></div></section>
            <section class="ticket-detail-section"><span class="detail-label">RESPUESTA DE SOPORTE</span><p><?= !empty($row['comentario_admin']) ? nl2br(htmlspecialchars($row['comentario_admin'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) : 'Aún no hay respuesta de soporte.' ?></p></section>
            <section class="ticket-detail-section"><span class="detail-label">ARCHIVOS</span><div class="archivos">
                <?php if (!empty($row['imagen'])): ?><a class="archivo-btn" href="../../uploads/<?= rawurlencode(basename($row['imagen'])) ?>" target="_blank" rel="noopener"><i class="fas fa-paperclip"></i> Archivo enviado</a><?php endif; ?>
                <?php if ($archivoAdmin !== ''): ?><a class="archivo-btn" href="../../uploads/<?= rawurlencode(basename($archivoAdmin)) ?>" target="_blank" rel="noopener"><i class="fas fa-file-download"></i> Archivo de soporte</a><?php endif; ?>
                <?php if (empty($row['imagen']) && $archivoAdmin === ''): ?><span class="text-muted">Sin archivos adjuntos</span><?php endif; ?>
            </div></section>
        </div>
        <span class="ticket-detail-survey" data-rated="<?= !empty($row['ya_calificado']) ? '1' : '0' ?>" data-closed="<?= $resuelto ? '1' : '0' ?>" data-description="<?= htmlspecialchars(mb_strimwidth((string) $row['descripcion'], 0, 120, '...'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></span>
    </template>
<?php endforeach; ?>
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

(() => {
let filtroTicketsActual = 'todos';
const ticketDetailModalEl = document.getElementById('ticketDetailModal');
const ticketDetailModal = bootstrap.Modal.getOrCreateInstance(ticketDetailModalEl);

function actualizarVistaTickets() {
    const buscar = (document.getElementById('buscarMisTickets')?.value || '').trim().toLocaleLowerCase('es');
    let visibles = 0;
    document.querySelectorAll('#misTicketsRows .ticket-row').forEach(row => {
        const coincideEstado = filtroTicketsActual === 'todos' || row.dataset.status === filtroTicketsActual;
        const coincideTexto = !buscar || (row.dataset.search || '').includes(buscar);
        row.hidden = !(coincideEstado && coincideTexto);
        if (!row.hidden) visibles++;
    });
    document.getElementById('ticketsEmpty').hidden = visibles > 0;
}

document.querySelectorAll('[data-ticket-filter]').forEach(tab => tab.addEventListener('click', () => {
    filtroTicketsActual = tab.dataset.ticketFilter;
    document.querySelectorAll('[data-ticket-filter]').forEach(item => {
        const activo = item === tab;
        item.classList.toggle('active', activo);
        item.setAttribute('aria-selected', activo ? 'true' : 'false');
    });
    actualizarVistaTickets();
}));
document.getElementById('buscarMisTickets')?.addEventListener('input', actualizarVistaTickets);

function abrirDetalleTicket(id) {
    const template = document.getElementById('ticket-template-' + id);
    const fila = document.querySelector('.ticket-row[data-ticket-id="' + id + '"]');
    if (!template || !fila) return;
    document.getElementById('ticketDetailTitle').textContent = 'Ticket #' + id;
    const contenido = template.content.cloneNode(true);
    const meta = contenido.querySelector('.ticket-detail-survey');
    const puedeCalificar = meta?.dataset.closed === '1' && meta.dataset.rated !== '1';
    const descripcion = meta?.dataset.description || '';
    meta?.remove();
    const body = document.getElementById('ticketDetailBody');
    body.replaceChildren(contenido);
    const actions = document.getElementById('ticketDetailActions');
    actions.replaceChildren();
    const chat = document.createElement('button');
    chat.type = 'button'; chat.className = 'btn-chat-ticket';
    chat.dataset.ticketId = String(id); chat.dataset.chatStatus = fila.dataset.status;
    chat.innerHTML = '<i class="fa-solid fa-comments" aria-hidden="true"></i> Abrir conversación';
    chat.addEventListener('click', () => { ticketDetailModal.hide(); abrirChat(id); });
    actions.append(chat);
    if (fila.dataset.status === 'Pendiente') {
        const cancelar = document.createElement('button');
        cancelar.type = 'button'; cancelar.className = 'btn-cancelar'; cancelar.textContent = 'Cancelar ticket';
        cancelar.addEventListener('click', () => cancelarTicket(id)); actions.append(cancelar);
    }
    if (puedeCalificar) {
        const calificar = document.createElement('button');
        calificar.type = 'button'; calificar.className = 'btn btn-warning fw-bold';
        calificar.innerHTML = '<i class="fa-solid fa-star"></i> Calificar servicio';
        calificar.addEventListener('click', () => {
            ticketDetailModal.hide();
            abrirEncuestaManual('ticket', Number(id), 'Ticket #' + id, descripcion, '¿Se resolvió tu problema?');
        }); actions.append(calificar);
    }
    ticketDetailModal.show();
}

document.querySelectorAll('[data-open-ticket]').forEach(button => button.addEventListener('click', () => abrirDetalleTicket(button.dataset.openTicket)));

if (window.ticketStatusPoll) window.clearInterval(window.ticketStatusPoll);
let sincronizandoEstados = false;
async function sincronizarEstadosTickets() {
    if (document.hidden || !document.getElementById('misTicketsShell') || sincronizandoEstados) return;
    sincronizandoEstados = true;
    try {
        const response = await fetch('ticket/estados_tickets.php?_=' + Date.now(), {credentials: 'same-origin', cache: 'no-store'});
        if (!response.ok) return;
        const data = await response.json();
        const estados = new Map((data.tickets || []).map(ticket => [String(ticket.id), ticket.estatus]));
        let cambiaron = false;
        const conteos = {todos: 0, Pendiente: 0, 'En proceso': 0, Resuelto: 0, Cancelado: 0};
        document.querySelectorAll('#misTicketsRows .ticket-row').forEach(row => {
            const estado = estados.get(row.dataset.ticketId);
            if (!estado) return;
            const normalizado = ['Resuelto', 'Finalizado', 'Cerrado'].includes(estado) ? 'Resuelto' : estado;
            if (row.dataset.status !== normalizado) cambiaron = true;
            row.dataset.status = normalizado;
            const abrir = row.querySelector('[data-chat-status]');
            if (abrir) abrir.dataset.chatStatus = estado;
            const badge = row.querySelector('.badge-ticket');
            if (badge) {
                badge.className = 'badge-ticket ' + (estado === 'Pendiente' ? 'badge-pendiente' : estado === 'En proceso' ? 'badge-proceso' : normalizado === 'Resuelto' ? 'badge-finalizado' : estado === 'Cancelado' ? 'badge-cancelado' : 'badge-proceso');
                badge.textContent = normalizado;
            }
            conteos.todos++;
            if (Object.prototype.hasOwnProperty.call(conteos, normalizado)) conteos[normalizado]++;
            const template = document.getElementById('ticket-template-' + row.dataset.ticketId);
            const detailBadge = template?.content.querySelector('.ticket-detail-section .badge-ticket');
            if (detailBadge) {
                detailBadge.className = badge?.className || 'badge-ticket';
                detailBadge.textContent = normalizado;
            }
            const meta = template?.content.querySelector('.ticket-detail-survey');
            if (meta) meta.dataset.closed = normalizado === 'Resuelto' ? '1' : '0';
        });
        document.querySelectorAll('[data-ticket-filter]').forEach(tab => {
            const key = tab.dataset.ticketFilter;
            const count = key === 'todos' ? conteos.todos : conteos[key] || 0;
            const badge = tab.querySelector('span'); if (badge) badge.textContent = count;
        });
        const resumen = document.querySelectorAll('.tickets-summary strong');
        if (resumen[0]) resumen[0].textContent = conteos.todos;
        if (resumen[1]) resumen[1].textContent = conteos.Pendiente + conteos['En proceso'];
        if (cambiaron) {
            actualizarVistaTickets();
            conectarFormularioChat();
            if (ticketDetailModalEl.classList.contains('show')) {
                const abierto = document.getElementById('ticketDetailTitle').textContent.match(/#(\d+)/);
                if (abierto) abrirDetalleTicket(abierto[1]);
            }
        }
    } catch (_) {} finally { sincronizandoEstados = false; }
}
window.sincronizarEstadosTickets = sincronizarEstadosTickets;
if (window.ticketVisibilityHandler) document.removeEventListener('visibilitychange', window.ticketVisibilityHandler);
window.ticketVisibilityHandler = () => { if (!document.hidden) sincronizarEstadosTickets(); };
document.addEventListener('visibilitychange', window.ticketVisibilityHandler);
sincronizarEstadosTickets();
window.ticketStatusPoll = window.setInterval(sincronizarEstadosTickets, 300000);
})();

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
            bootstrap.Modal.getInstance(document.getElementById('ticketDetailModal'))?.hide();
            window.sincronizarEstadosTickets?.();
        }else{
            alert(res.error || "No se pudo cancelar el ticket");
        }
    });
}
</script>

<?php $conexion->close(); ?>
