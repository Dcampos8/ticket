<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('tickets');
if (!esAdminOSuperior()) { http_response_code(403); exit('No autorizado'); }
$ticketId = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
if (!usuarioPuedeAccederTicket($conexion, $ticketId)) { http_response_code(404); exit('Ticket no encontrado'); }
$stmt = $conexion->prepare('SELECT t.estatus, t.prioridad, t.tipo_ticket, t.descripcion, t.asignado_a, COALESCE(u.nombre_completo, t.nombre_completo) AS solicitante FROM tickets t LEFT JOIN usuarios u ON t.nombre = u.usuario WHERE t.id = ? LIMIT 1');
$stmt->bind_param('i', $ticketId); $stmt->execute(); $ticket = $stmt->get_result()->fetch_assoc(); $stmt->close();
$descripcionTicket = trim((string) ($ticket['descripcion'] ?? ''));
$descripcionLarga = mb_strlen($descripcionTicket, 'UTF-8') > 180 || count(preg_split('/\R/u', $descripcionTicket)) > 4;
$csrfToken = asegurarTokenCsrf();
$usuarioActual = (string) ($_SESSION['usuario'] ?? '');
$baseUrl = defined('BASE_URL') ? BASE_URL : '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atender ticket #<?= (int) $ticketId ?> | Service Desk</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>css/pages/admin__tickets__chat_ticket.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__chat_ticket.css') ?>">
</head>
<body>
<header class="chat-header">
    <a class="back-link" href="index.php" aria-label="Volver al Service Desk"><i class="fas fa-arrow-left"></i></a>
    <div class="ticket-heading"><div class="eyebrow">SERVICE DESK · ATENCIÓN</div><h1>Ticket #<?= (int) $ticketId ?> <span class="status-chip"><?= htmlspecialchars($ticket['estatus']) ?></span></h1><p><?= htmlspecialchars($ticket['solicitante'] ?: 'Solicitante') ?> · <?= htmlspecialchars($ticket['tipo_ticket'] ?: 'Solicitud') ?> · Prioridad <?= htmlspecialchars($ticket['prioridad'] ?: 'Normal') ?></p></div>
    <a class="ticket-details" href="index.php?busqueda=<?= (int) $ticketId ?>"><i class="fas fa-list-check"></i> Volver al listado</a>
</header>
<main class="chat-layout">
    <section class="conversation-card" aria-label="Conversación del ticket">
        <div class="conversation-top"><div><strong><i class="fas fa-comments"></i> Conversación</strong><small>Mensajes visibles para el solicitante</small></div><span class="live-indicator"><i></i> Actualización automática</span></div>
        <div id="chatBox" aria-live="polite"><div class="chat-empty">Cargando conversación…</div></div>
        <form id="chatForm" class="composer">
            <input type="text" id="mensaje" maxlength="5000" placeholder="Escribe una respuesta para el solicitante…" autocomplete="off" aria-label="Mensaje">
            <button id="sendButton" type="submit"><i class="fas fa-paper-plane"></i><span>Enviar</span></button>
        </form>
        <div id="chatError" class="chat-error" role="alert"></div>
    </section>
    <aside class="ticket-context">
        <div class="context-title"><i class="fas fa-circle-info"></i> Datos de la solicitud</div>
        <div class="context-item"><small>SOLICITANTE</small><strong><?= htmlspecialchars($ticket['solicitante'] ?: 'Sin nombre') ?></strong></div>
        <div class="context-item"><small>ASIGNADO A</small><strong><?= htmlspecialchars($ticket['asignado_a'] ?: 'Sin asignar') ?></strong></div>
        <div class="context-item"><small>TIPO</small><strong><?= htmlspecialchars($ticket['tipo_ticket'] ?: 'Sin categoría') ?></strong></div>
        <div class="context-item"><small>DESCRIPCIÓN</small><p id="ticketDescription" class="ticket-description<?= $descripcionLarga ? ' is-collapsed' : '' ?>"><?= nl2br(htmlspecialchars($descripcionTicket !== '' ? $descripcionTicket : 'Sin descripción')) ?></p><?php if ($descripcionLarga): ?><button type="button" id="toggleDescription" class="description-toggle" aria-expanded="false" aria-controls="ticketDescription">Ver más</button><?php endif; ?></div>
        <a href="index.php" class="context-back"><i class="fas fa-arrow-left"></i> Regresar a la bandeja</a>
    </aside>
</main>
<script>
const ticketId = <?= (int) $ticketId ?>;
const currentUser = <?= json_encode(mb_strtolower(trim($usuarioActual)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const csrfToken = <?= json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const chatBox = document.getElementById('chatBox');
const chatError = document.getElementById('chatError');
const descriptionToggle = document.getElementById('toggleDescription');
if (descriptionToggle) {
    descriptionToggle.addEventListener('click', () => {
        const description = document.getElementById('ticketDescription');
        const expanded = description.classList.toggle('is-expanded');
        description.classList.toggle('is-collapsed', !expanded);
        descriptionToggle.textContent = expanded ? 'Ver menos' : 'Ver más';
        descriptionToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    });
}
let loading = false;
let lastSignature = '';

function renderMessages(messages) {
    const signature = JSON.stringify(messages);
    if (signature === lastSignature) return;
    const wasNearBottom = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 100;
    lastSignature = signature;
    chatBox.replaceChildren();
    if (!messages.length) {
        const empty = document.createElement('div'); empty.className = 'chat-empty'; empty.textContent = 'Aún no hay mensajes. Envía una respuesta para iniciar la conversación.'; chatBox.append(empty); return;
    }
    messages.forEach(message => {
        const mine = String(message.usuario || '').trim().toLowerCase() === currentUser;
        const item = document.createElement('article'); item.className = 'msg ' + (mine ? 'me' : 'them');
        const author = document.createElement('strong'); author.textContent = mine ? 'Tú · Soporte' : (message.usuario || 'Solicitante');
        const body = document.createElement('div'); body.className = 'message-body'; body.textContent = message.mensaje || '';
        const date = document.createElement('small'); date.textContent = message.fecha || '';
        item.append(author, body, date); chatBox.append(item);
    });
    if (wasNearBottom) chatBox.scrollTop = chatBox.scrollHeight;
}

async function cargarChat() {
    if (loading) return;
    loading = true;
    try {
        const response = await fetch('backend/obtener_chat.php?ticket_id=' + ticketId, {cache: 'no-store', credentials: 'same-origin'});
        if (!response.ok) throw new Error('No se pudo cargar la conversación.');
        const data = await response.json();
        if (!Array.isArray(data)) throw new Error(data.msg || 'Respuesta inválida del servidor.');
        renderMessages(data); chatError.textContent = '';
    } catch (error) { chatError.textContent = error.message || 'Error de conexión.'; }
    finally { loading = false; }
}

document.getElementById('chatForm').addEventListener('submit', async event => {
    event.preventDefault();
    const input = document.getElementById('mensaje');
    const text = input.value.trim();
    if (!text) { input.focus(); return; }
    const button = document.getElementById('sendButton'); button.disabled = true; chatError.textContent = '';
    try {
        const response = await fetch('backend/enviar_chat.php', {method: 'POST', credentials: 'same-origin', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: new URLSearchParams({ticket_id: String(ticketId), mensaje: text, csrf_token: csrfToken})});
        const result = await response.json();
        if (!response.ok || result.status !== 'ok') throw new Error(result.msg || 'No se pudo enviar el mensaje.');
        input.value = ''; await cargarChat(); input.focus();
    } catch (error) { chatError.textContent = error.message || 'Error al enviar el mensaje.'; }
    finally { button.disabled = false; }
});

cargarChat();
window.setInterval(cargarChat, 30000);
</script>
</body>
</html>
