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
<style>
.tickets-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}

.tickets-header h3{
    margin:0;
    font-size:28px;
    font-weight:700;
    color:var(--tv-ink,#0f172a);
}

.ticket-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));
    align-items:start;
    gap:20px;
}

.ticket-card{
    display:flex;
    flex-direction:column;
    background:#fff;
    border-radius:18px;
    padding:20px;
    border:1px solid #edf2f7;
    box-shadow:
        0 5px 20px rgba(0,0,0,.05),
        0 1px 4px rgba(0,0,0,.03);
    transition:.25s;
}

.ticket-card:hover{
    transform:translateY(-3px);
    box-shadow:
        0 10px 28px rgba(0,0,0,.08),
        0 2px 6px rgba(0,0,0,.04);
}

.ticket-top{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:15px;
}

.ticket-id{
    font-size:18px;
    font-weight:700;
    color:var(--tv-ink,#0f172a);
}

.ticket-desc{
    color:#555;
    line-height:1.6;
    margin-bottom:18px;
    display:-webkit-box;
    -webkit-line-clamp:3;
    -webkit-box-orient:vertical;
    overflow:hidden;
}

.ticket-meta{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(130px,1fr));
    gap:12px;
    margin-top:15px;
}

.meta-box{
    background:#f8fafc;
    border-radius:12px;
    padding:12px;
}

.meta-title{
    font-size:12px;
    text-transform:uppercase;
    color:#95a5a6;
    margin-bottom:5px;
    font-weight:600;
}

.meta-value{
    color:var(--tv-ink,#0f172a);
    font-weight:500;
}

.badge-ticket{
    padding:8px 14px;
    border-radius:30px;
    font-size:13px;
    font-weight:700;
}

.badge-pendiente{
    background:#fff7d6;
    color:#b7791f;
}

.badge-proceso{
    background:#dbeafe;
    color:#2563eb;
}

.badge-finalizado{
    background:#dcfce7;
    color:#16a34a;
}

.badge-cancelado{
    background:#fee2e2;
    color:#dc2626;
}

.archivos{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.archivo-btn{
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
    padding:10px 14px;
    border-radius:10px;
    background:#f5f7fa;
    color:#34495e;
    transition:.2s;
}

.archivo-btn:hover{
    background:#e9eef5;
}

.ticket-actions{
    margin-top:auto;
    padding-top:18px;
    display:flex;
    justify-content:flex-end;
}

.btn-cancelar{
    border:none;
    background:var(--tv-red,#e31e24);
    color:white;
    padding:10px 18px;
    border-radius:10px;
    font-weight:600;
}

.btn-cancelar:hover{
    background:var(--tv-red-dark,#b91c1c);
}

.sin-tickets{
    text-align:center;
    background:white;
    padding:60px;
    border-radius:18px;
    color:#95a5a6;
}
</style>
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
