<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('tickets');
if (!esAdminOSuperior()) {
    http_response_code(403);
    exit('No autorizado');
}

// 4. Conexión a la base de datos (Usando ROOT_PATH)
require_once(ROOT_PATH . 'backend/conexion.php');

// 5. Cargar el menú usando la ruta absoluta
include(ROOT_PATH . 'admin/menu.php');

date_default_timezone_set('America/Mexico_City');

// Obtener usuarios
$usuarios_result = $conexion->query("SELECT id, usuario, nombre_completo, area, rol FROM usuarios ORDER BY nombre_completo");
$usuarios_array = [];

while ($u = $usuarios_result->fetch_assoc()) {
    $usuarios_array[$u['id']] = [
        'usuario' => $u['usuario'],
        'nombre_completo' => $u['nombre_completo'], 
        'area' => $u['area'],
        'rol' => $u['rol']
    ];
}

$fecha_actual_local = date('Y-m-d\TH:i');
?>

<!-- Uso de BASE_URL para el CSS -->
<link rel="stylesheet" href="<?= BASE_URL ?>css/ticket_form.css?v=<?= time() ?>">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__tickets__crear_ticket_admin.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__crear_ticket_admin.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="ticket-admin-content">
    <div class="ticket-page">
        <div class="form-container shadow-sm">
            <h3 class="mb-4 text-primary"><i class="fa-solid fa-clipboard-check me-2"></i>Nuevo Ticket Administrativo</h3>

            <form id="formTicketAdmin" class="grid-form" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <!-- ================= USUARIO ================= -->
                <div class="section-title">Información del Solicitante</div>

                <div class="campo">
                    <label class="fw-bold">Seleccionar Usuario</label>
                    <select id="nombre_select" name="usuario_id" class="form-select border-primary" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($usuarios_array as $id => $data): ?>
                            <option value="<?= $id ?>"><?= htmlspecialchars($data['nombre_completo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="campo">
                    <label>Usuario (Login)</label>
                    <input type="text" id="usuario_login" name="nombre" class="form-control bg-light" readonly>
                </div>

                <div class="campo">
                    <label>Nombre Completo</label>
                    <input type="text" id="nomcoml" name="nombre_completo" class="form-control bg-light" readonly>
                </div>

                <div class="campo">
                    <label>Área</label>
                    <input type="text" id="area_input" name="area" class="form-control bg-light" readonly>
                </div>

                <div class="campo">
                    <label>Puesto</label>
                    <input type="text" id="rol_input" name="puesto" class="form-control bg-light" readonly>
                </div>

                <!-- ================= DETALLES ================= -->
                <div class="section-title">Detalles del Ticket</div>

                <div class="campo">
                    <label>Tipo Ticket</label>
                    <select name="tipo_ticket" class="form-select">
                        <option value="Soporte">Soporte Técnico</option>
                        <option value="Capacitacion">Capacitación</option>
                        <option value="Ajuste facturas">Ajuste Facturas</option>
                        <option value="Camaras">Cámaras / Seguridad</option>
                        <option value="Diseño">Diseño Gráfico</option>
                    </select>
                </div>

                <div class="campo">
                    <label>Estatus</label>
                    <select name="estatus" class="form-select fw-bold text-primary border-primary">
                        <option value="En proceso" selected>En proceso</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Finalizado">Finalizado</option>
                    </select>
                </div>

                <div class="campo">
                    <label>Fecha</label>
                    <input type="datetime-local" name="fecha_creacion" class="form-control" value="<?= $fecha_actual_local ?>">
                </div>

                <div class="campo full-width">
                    <label>Descripción del Problema</label>
                    <textarea name="descripcion" class="form-control" rows="3" required placeholder="Describe el problema detalladamente..."></textarea>
                </div>

                <!-- ================= ADMIN ================= -->
                <div class="section-title">Gestión de Sistemas (Admin)</div>

                <div class="campo full-width">
                    <label>Comentario / Solución</label>
                    <textarea name="comentario_admin" class="form-control border-success" rows="2" placeholder="Notas técnicas o solución aplicada..."></textarea>
                </div>

                <!-- ================= ARCHIVOS ================= -->
                <div class="section-title">Evidencias y Adjuntos</div>
                
                <div class="campo">
                    <label>Imagen Usuario</label>
                    <div class="upload-box" id="dropAreaUser">
                        <span id="txtUser">📷 Click para subir</span>
                        <input type="file" name="imagen" id="imgUser" hidden accept="image/*">
                        <img id="prevUser" class="preview-img">
                    </div>
                </div>

                <div class="campo">
                    <label>Imagen Solución</label>
                    <div class="upload-box" id="dropAreaAdmin">
                        <span id="txtAdmin">🛠️ Evidencia Técnica</span>
                        <input type="file" name="imagen_admin" id="imgAdmin" hidden accept="image/*">
                        <img id="prevAdmin" class="preview-img">
                    </div>
                </div>

                <div class="campo">
                    <label>Documento Adjunto (PDF/Doc)</label>
                    <input type="file" name="archivo_admin" class="form-control">
                </div>

                <!-- ================= BOTÓN ================= -->
                <div class="full-width text-center mt-4">
                    <button type="submit" class="btn btn-primary btn-lg px-5 shadow">
                        <i class="fa-solid fa-save me-2"></i>Guardar Ticket
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary btn-lg ms-2">Cancelar</a>
                </div>

            </form>
        </div>
    </div>
</div>

<script>
const usuarios = <?= json_encode($usuarios_array, JSON_UNESCAPED_UNICODE) ?>;

// Autocompletar datos
document.getElementById('nombre_select').addEventListener('change', function() {
    const u = usuarios[this.value];
    if (u) {
        document.getElementById('usuario_login').value = u.usuario || '';
        document.getElementById('nomcoml').value = u.nombre_completo || '';
        document.getElementById('area_input').value = u.area || '';
        document.getElementById('rol_input').value = u.rol || '';
    }
});

// Drag & drop logic
function activarZona(idCaja, idInput, idImg, idTexto) {
    const caja = document.getElementById(idCaja);
    const input = document.getElementById(idInput);
    const img = document.getElementById(idImg);
    const txt = document.getElementById(idTexto);

    caja.addEventListener('click', () => input.click());

    input.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                img.style.display = 'block';
                txt.innerText = "Imagen lista ✅";
            };
            reader.readAsDataURL(this.files[0]);
        }
    });
}

activarZona('dropAreaUser','imgUser','prevUser','txtUser');
activarZona('dropAreaAdmin','imgAdmin','prevAdmin','txtAdmin');

// Envío AJAX usando BASE_URL para la ruta del fetch
document.getElementById('formTicketAdmin').addEventListener('submit', function(e) {
    e.preventDefault();

    Swal.fire({
        title: 'Procesando...',
        text: 'Guardando ticket en base de datos...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    const formData = new FormData(this);

    fetch('<?= BASE_URL ?>admin/tickets/backend/crear_ticket_admin.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'ok') {
            Swal.fire({
                icon: 'success',
                title: '¡Registrado!',
                text: 'Ticket #' + data.ticketId + ' guardado con éxito.'
            }).then(() => {
                window.location.href = 'index.php';
            });
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(() => Swal.fire('Error', 'No se pudo conectar con el servidor', 'error'));
});
</script>

<?php 
if (isset($conexion)) {
    $conexion->close(); 
}
?>
