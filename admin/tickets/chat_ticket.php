<?php
session_start();
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('tickets');
if (!esAdminOSuperior()) {
    http_response_code(403);
    die("Acceso denegado");
}

$ticket_id = filter_input(INPUT_GET, 'ticket_id', FILTER_VALIDATE_INT) ?: 0;
if (!usuarioPuedeAccederTicket($conexion, $ticket_id)) {
    http_response_code(404);
    die("Ticket no encontrado");
}
$usuarioActual = (string) ($_SESSION['usuario'] ?? '');
$csrfToken = asegurarTokenCsrf();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat Ticket #<?= (int) $ticket_id ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__tickets__chat_ticket.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__chat_ticket.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
</head>
<body>

<h2>💬 Ticket #<?= (int) $ticket_id ?></h2>

<div id="chatBox"></div>

<div id="inputBox">
    <input type="text" id="mensaje" placeholder="Escribe un mensaje..." autocomplete="off">
    <button onclick="enviarMensaje()"><i class="fa fa-paper-plane"></i></button>
</div>

<script>
function cargarChat() {
    fetch('backend/obtener_chat.php?ticket_id=<?= (int) $ticket_id ?>')
    .then(res => res.json())
    .then(data => {
        const box = document.getElementById('chatBox');
        box.innerHTML = '';

        data.forEach(m => {
            const div = document.createElement('div');
            div.classList.add('msg');

            if (m.usuario.toLowerCase().trim() === <?= json_encode(strtolower(trim($usuarioActual)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>) {
                div.classList.add('me');
                const autor = document.createElement('strong');
                autor.textContent = 'Yo';
                const mensaje = document.createElement('div');
                mensaje.textContent = m.mensaje;
                const fecha = document.createElement('small');
                fecha.textContent = m.fecha;
                div.append(autor, mensaje, fecha);
            } else {
                div.classList.add('them');
                const autor = document.createElement('strong');
                autor.textContent = m.usuario;
                const mensaje = document.createElement('div');
                mensaje.textContent = m.mensaje;
                const fecha = document.createElement('small');
                fecha.textContent = m.fecha;
                div.append(autor, mensaje, fecha);
            }

            box.appendChild(div);
        });

        box.scrollTop = box.scrollHeight;
    });
}

function enviarMensaje() {
    let texto = document.getElementById("mensaje").value.trim();
    if (!texto) return;

    fetch('backend/enviar_chat.php', {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: new URLSearchParams({ ticket_id: '<?= (int) $ticket_id ?>', mensaje: texto, csrf_token: <?= json_encode($csrfToken) ?> })
    })
    .then(() => {
        document.getElementById("mensaje").value = "";
        cargarChat();
    });
}

let cargando = false;

async function cargarChat() {
    if (cargando) return; // evita duplicados por clics o recargas rápidas
    cargando = true;

    try {
        const response = await fetch("backend/chat_polling.php");
        const data = await response.text();

        document.getElementById("chat").innerHTML = data;

        // Auto scroll al final (Opcional)
        const cont = document.getElementById("chat");
        cont.scrollTop = cont.scrollHeight;
    } catch (error) {
        console.error("Error cargando chat:", error);
    }

    cargando = false;

    // Se llama nuevamente solo cuando termina la ejecución
    setTimeout(cargarChat, 500); // 0.5s entre cada ciclo
}

cargarChat();


</script>

</body>
</html>
