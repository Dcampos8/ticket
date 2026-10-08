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
window.ticketCsrfToken = <?= json_encode(asegurarTokenCsrf()) ?>;

function abrirChat(id) {
    ticketActual = id;
    document.getElementById("chatTitulo").innerText = "💬 Ticket #" + id;
    document.getElementById("chatFlotante").style.display = "block";
    cargarChat();
}

function cerrarChat() {
    document.getElementById("chatFlotante").style.display = "none";
    ticketActual = null;
}

function cargarChat() {
    if (!ticketActual) return;
    fetch("../../admin/tickets/backend/obtener_chat.php?ticket_id=" + ticketActual)
        .then(r => r.json())
        .then(data => {
            const cont = document.getElementById("chatMensajes");
            cont.innerHTML = "";

            data.forEach(m => {
                let div = document.createElement("div");
                div.className = "chat-message " + (m.usuario === usuarioActual ? "user" : "admin");
                div.textContent = m.mensaje;
                cont.appendChild(div);
            });

            cont.scrollTop = cont.scrollHeight;
        });
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

    <div class="ticket-desc">
        <?= nl2br(htmlspecialchars($row['descripcion'])) ?>
    </div>

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

<script>
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
