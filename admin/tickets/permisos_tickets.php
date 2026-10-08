<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad y módulo
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('tickets');
if (!esAdminOSuperior()) {
    http_response_code(403);
    exit('No autorizado');
}

// 4. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 5. Cargar el menú y headers usando rutas absolutas
include(ROOT_PATH . 'admin/menu.php');
// Nota: He movido el header debajo del menú si es necesario, 
// pero usualmente el header contiene los estilos globales.


$currentPage = basename($_SERVER['PHP_SELF']);

// Obtener usuarios
$result = $conexion->query("SELECT usuario, nombre_completo, rol, puede_ver_todos_tickets FROM usuarios ORDER BY rol, nombre_completo");
?>

<style>
    /* Estilo del Contenedor Principal con margen para el menú lateral */
    .ticket-admin-content {
        padding: 20px;
        background-color: #f1f5f9;
        min-height: 100vh;
        transition: all 0.3s;
    }

    /* Título y Breadcrumb */
    h1 { 
        color: #1e3a8a; 
        font-weight: 800; 
        text-transform: uppercase; 
        font-size: 1.5rem; 
        border-left: 6px solid #e31e24; 
        padding-left: 15px; 
    }

    /* Tarjeta y Tabla */
    .card { border: none; border-radius: 15px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); overflow: hidden; }
    .table-permisos thead th {
        background-color: #1e3a8a;
        color: white;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 1px;
        border: none;
        padding: 15px;
    }

    /* DISEÑO DEL SWITCH */
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 24px;
    }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider {
        position: absolute;
        cursor: pointer;
        top: 0; left: 0; right: 0; bottom: 0;
        background-color: #cbd5e1;
        transition: .4s;
        border-radius: 34px;
    }
    .slider:before {
        position: absolute;
        content: "";
        height: 18px; width: 18px;
        left: 3px; bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }
    input:checked + .slider { background-color: #1e3a8a; }
    input:checked + .slider:before { transform: translateX(26px); }

    /* Alerta Flotante */
    #alerta {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 250px;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        font-weight: 600;
    }

    @media (max-width: 992px) {
        .ticket-admin-content { margin-left: 0; }
    }
</style>

<div class="ticket-admin-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h1>Permisos de Tickets</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="index.php" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active">Seguridad</li>
                </ol>
            </nav>
        </div>

        <!-- Alerta para AJAX -->
        <div id="alerta" class="alert d-none"></div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-permisos m-0">
                    <thead>
                        <tr>
                            <th><i class="fa-solid fa-id-badge me-2"></i>ID Usuario</th>
                            <th><i class="fa-solid fa-user me-2"></i>Nombre Completo</th>
                            <th><i class="fa-solid fa-shield-halved me-2"></i>Rol</th>
                            <th class="text-center">Visualización Global</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($fila = $result->fetch_assoc()): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($fila['usuario']) ?></code></td>
                            <td><?= htmlspecialchars($fila['nombre_completo']) ?></td>
                            <td>
                                <span class="badge bg-light text-dark border shadow-sm px-2 py-1" style="font-size: 0.7rem;">
                                    <?= htmlspecialchars($fila['rol']) ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <label class="switch">
                                    <input type="checkbox" 
                                           class="permiso-toggle" 
                                           data-usuario="<?= htmlspecialchars($fila['usuario']) ?>"
                                           <?= $fila['puede_ver_todos_tickets'] ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-3 text-muted">
            <small><i class="fa-solid fa-circle-info me-1"></i> Los cambios se guardan automáticamente al activar/desactivar el interruptor.</small>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    $(document).ready(function() {
        $(".permiso-toggle").change(function() {
            const usuario = $(this).data("usuario");
            const permiso = $(this).is(":checked") ? 1 : 0;
            const $alerta = $("#alerta");

            $.ajax({
                // Usamos la ruta absoluta desde la raíz del sitio
                url: "<?= BASE_URL ?>admin/usuarios/backend/actualizar_permiso.php",
                method: "POST",
                data: { usuario, permiso },
                success: function(response) {
                    $alerta.removeClass("d-none alert-danger").addClass("alert-success text-white bg-success")
                           .html('<i class="fa-solid fa-check-circle me-2"></i> ¡Permiso actualizado para ' + usuario + '!')
                           .fadeIn().delay(2000).fadeOut();
                },
                error: function() {
                    $alerta.removeClass("d-none alert-success").addClass("alert-danger text-white bg-danger")
                           .html('<i class="fa-solid fa-triangle-exclamation me-2"></i> Error de conexión con el servidor')
                           .fadeIn().delay(3000).fadeOut();
                }
            });
        });
    });
</script>

<?php 
if (isset($conexion)) {
    $conexion->close(); 
}
?>
