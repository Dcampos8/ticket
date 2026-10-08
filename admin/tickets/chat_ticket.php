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

<style>
:root { --chat-navy: #172554; --chat-blue: #1e3a8a; --chat-bg: #f3f5f8; --chat-line: #dfe4ec; }
* { box-sizing: border-box; }
body {
    display: flex;
    min-height: 100vh;
    flex-direction: column;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #202a3b;
    background: var(--chat-bg);
    margin: 0;
}

h2 {
    background: var(--chat-navy);
    padding: 16px 24px;
    color: white;
    margin: 0;
    text-align: left;
    font-size: 1.05rem;
    font-weight: 650;
    border-bottom: 3px solid #c9252d;
}

/* Caja contenedora del chat */
#chatBox {
    width: min(94%, 900px);
    height: calc(100vh - 160px);
    min-height: 300px;
    margin: 20px auto 12px;
    padding: 20px;
    background: #ffffff;
    overflow-y: auto;
    border-radius: 12px;
    border: 1px solid var(--chat-line);
    box-shadow: 0 2px 8px rgba(23, 32, 51, .06);
    display: flex;
    flex-direction: column;
}

/* Burbuja de mensaje */
.msg {
    max-width: min(78%, 620px);
    padding: 11px 14px;
    border-radius: 10px;
    margin-bottom: 10px;
    position: relative;
    font-size: 14px;
    box-shadow: none;
    overflow-wrap: anywhere;
}

/* Mensaje del usuario actual */
.me {
    background: var(--chat-blue);
    color: #fff;
    align-self: flex-end;
    text-align: right;
}

/* Mensaje del otro usuario */
.them {
    background: #eef1f5;
    align-self: flex-start;
}

.msg small {
    font-size: 11px;
    display: block;
    margin-top: 5px;
    opacity: 0.72;
}

/* Caja de envió */
#inputBox {
    width: min(94%, 900px);
    margin: 0 auto 20px;
    display: flex;
    gap: 10px;
}

#mensaje {
    width: 100%;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #d7dde7;
    font-size: 14px;
    min-width: 0;
}
#mensaje:focus {
    outline: none;
    border-color: #627cae;
    box-shadow: 0 0 0 3px rgba(30, 58, 138, .11);
}

button {
    flex: 0 0 46px;
    background: var(--chat-blue);
    border: none;
    padding: 10px;
    border-radius: 8px;
    cursor: pointer;
    color: white;
    font-size: 16px;
}
button:hover { background: #172554; }
@media (max-width: 600px) {
    h2 { padding: 14px 16px; }
    #chatBox { width: calc(100% - 24px); height: calc(100vh - 145px); padding: 14px; }
    #inputBox { width: calc(100% - 24px); }
    .msg { max-width: 88%; }
}
</style>
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
