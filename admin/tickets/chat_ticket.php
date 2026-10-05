<?php
session_start();
if (!isset($_SESSION['logueado'])) {
    die("Acceso denegado");
}

$ticket_id = $_GET['ticket_id'] ?? 0;
$usuarioActual = $_SESSION['usuario'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Chat Ticket #<?= $ticket_id ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
body {
    font-family: Arial, sans-serif;
    background: #ececec;
    margin:0;
}

h2 {
    background: #075E54;
    padding: 15px;
    color: white;
    margin: 0;
    text-align: center;
}

/* Caja contenedora del chat */
#chatBox {
    width: 90%;
    max-width: 600px;
    height: 440px;
    margin: 20px auto;
    padding: 15px;
    background: #ffffff;
    overflow-y: auto;
    border-radius: 10px;
    border: 1px solid #ddd;
    display: flex;
    flex-direction: column;
}

/* Burbuja de mensaje */
.msg {
    max-width: 70%;
    padding: 10px;
    border-radius: 15px;
    margin-bottom: 12px;
    position: relative;
    font-size: 14px;
    box-shadow: 0px 2px 4px rgba(0,0,0,0.1);
}

/* Mensaje del usuario actual */
.me {
    background: #DCF8C6;
    align-self: flex-end;
    text-align: right;
}

/* Mensaje del otro usuario */
.them {
    background: #e9ecef;
    align-self: flex-start;
}

.msg small {
    font-size: 11px;
    display: block;
    margin-top: 5px;
    opacity: 0.6;
}

/* Caja de envió */
#inputBox {
    width: 90%;
    max-width: 600px;
    margin: 10px auto;
    display: flex;
    gap: 10px;
}

#mensaje {
    width: 100%;
    padding: 12px;
    border-radius: 20px;
    border: 1px solid #aaa;
    font-size: 14px;
}

button {
    background: #25D366;
    border: none;
    padding: 12px 16px;
    border-radius: 50%;
    cursor: pointer;
    color: white;
    font-size: 18px;
}
</style>
</head>
<body>

<h2>💬 Ticket #<?= $ticket_id ?></h2>

<div id="chatBox"></div>

<div id="inputBox">
    <input type="text" id="mensaje" placeholder="Escribe un mensaje..." autocomplete="off">
    <button onclick="enviarMensaje()"><i class="fa fa-paper-plane"></i></button>
</div>

<script>
function cargarChat() {
    fetch('backend/obtener_chat.php?ticket_id=<?= $ticket_id ?>')
    .then(res => res.json())
    .then(data => {
        const box = document.getElementById('chatBox');
        box.innerHTML = '';

        data.forEach(m => {
            const div = document.createElement('div');
            div.classList.add('msg');

            if (m.usuario.toLowerCase().trim() === "<?= strtolower(trim($usuarioActual)) ?>") {
                div.classList.add('me');
                div.innerHTML = `<strong>Yo</strong><br>${m.mensaje}<br><small>${m.fecha}</small>`;
            } else {
                div.classList.add('them');
                div.innerHTML = `<strong>${m.usuario}</strong><br>${m.mensaje}<br><small>${m.fecha}</small>`;
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
        body: `ticket_id=<?= $ticket_id ?>&usuario=<?= urlencode($usuarioActual) ?>&mensaje=${encodeURIComponent(texto)}`
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
