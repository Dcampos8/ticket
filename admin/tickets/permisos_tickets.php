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

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__tickets__permisos_tickets.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__tickets__permisos_tickets.css') ?>">

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
