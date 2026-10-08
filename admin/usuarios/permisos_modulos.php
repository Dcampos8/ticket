<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Cargar helper de permisos
require_once(ROOT_PATH . 'shared/permisos.php');

// 4. Verificar seguridad: SOLO superadmin puede entrar aquí
if (!isset($_SESSION['logueado']) || !esSuperAdmin()) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 5. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 6. Cargar el menú
include(ROOT_PATH . 'admin/menu.php');

// 7. Obtener administradores (superadmin incluido, solo para mostrarlo)
$result = $conexion->query("SELECT id, usuario, nombre_completo, rol, modulos_permitidos FROM usuarios WHERE rol IN ('admin','superadmin') ORDER BY rol DESC, nombre_completo ASC");
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__permisos_modulos.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__usuarios__permisos_modulos.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="ticket-admin-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mt-4 mb-4">
            <h1>Permisos de Módulos</h1>
            <div>
                <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modalNuevoModulo">
                    <i class="fa-solid fa-plus me-1"></i> Agregar Nuevo Módulo
                </button>
            </div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?= BASE_URL ?>admin/usuarios/index.php" class="text-decoration-none">Usuarios</a></li>
                    <li class="breadcrumb-item active">Permisos de Módulos</li>
                </ol>
            </nav>
        </div>

        <div id="alerta" class="alert d-none"></div>

        <p class="text-muted mb-3">
            <i class="fa-solid fa-circle-info me-1"></i>
            Marca qué módulos del panel puede ver y usar cada administrador. Los cambios se guardan solos al marcar/desmarcar.
            El <strong>superadmin</strong> siempre tiene acceso a todo, por eso no tiene checkboxes.
        </p>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-permisos m-0">
                    <thead>
                        <tr>
                            <th style="text-align:left;">Administrador</th>
                            <?php foreach (MODULOS_DISPONIBLES as $clave => $info): ?>
                                <th title="<?= htmlspecialchars($info['label']) ?>">
                                    <i class="fa-solid <?= htmlspecialchars($info['icon']) ?>"></i><br>
                                    <?= htmlspecialchars($info['label']) ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($fila = $result->fetch_assoc()): ?>
                            <?php $modulosUsuario = parsearModulos($fila['modulos_permitidos']); ?>
                            <tr data-usuario="<?= htmlspecialchars($fila['usuario']) ?>">
                                <td style="text-align:left;">
                                    <strong><?= htmlspecialchars($fila['nombre_completo']) ?></strong><br>
                                    <code><?= htmlspecialchars($fila['usuario']) ?></code>
                                </td>
                                <?php if ($fila['rol'] === 'superadmin'): ?>
                                    <td colspan="<?= count(MODULOS_DISPONIBLES) ?>">
                                        <span class="badge-superadmin"><i class="fa-solid fa-crown me-1"></i>Todos los módulos (superadmin)</span>
                                    </td>
                                <?php else: ?>
                                    <?php foreach (MODULOS_DISPONIBLES as $clave => $info): ?>
                                        <td>
                                            <input type="checkbox"
                                                   class="modulo-check"
                                                   data-modulo="<?= htmlspecialchars($clave) ?>"
                                                   <?= in_array($clave, $modulosUsuario, true) ? 'checked' : '' ?>>
                                        </td>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nuevo Módulo -->
<div class="modal fade" id="modalNuevoModulo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fa-solid fa-cube me-2"></i>Registrar Módulo en el Sistema</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formNuevoModulo">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Clave del Módulo (Sin espacios ni acentos)</label>
                        <input type="text" name="clave" class="form-control" placeholder="ej: facturacion" required pattern="[a-z0-9_]+">
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Nombre visible (Label)</label>
                        <input type="text" name="label" class="form-control" placeholder="ej: Facturación" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Ícono (FontAwesome)</label>
                        <input type="text" name="icon" class="form-control" placeholder="fa-file-pdf" value="fa-cubes">
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Ruta relativa (Opcional)</label>
                        <input type="text" name="ruta" class="form-control" placeholder="admin/facturacion/index.php">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar Módulo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function () {
    $('.modulo-check').change(function () {
        const $fila = $(this).closest('tr');
        const usuario = $fila.data('usuario');
        const modulos = [];
        $fila.find('.modulo-check:checked').each(function () {
            modulos.push($(this).data('modulo'));
        });

        const $alerta = $('#alerta');

        $.ajax({
            url: "<?= BASE_URL ?>admin/usuarios/backend/actualizar_modulos.php",
            method: "POST",
            data: { usuario: usuario, modulos: modulos },
            success: function () {
                $alerta.removeClass('d-none alert-danger').addClass('alert-success text-white bg-success')
                       .html('<i class="fa-solid fa-check-circle me-2"></i> Módulos actualizados para ' + usuario)
                       .fadeIn().delay(1800).fadeOut();
            },
            error: function () {
                $alerta.removeClass('d-none alert-success').addClass('alert-danger text-white bg-danger')
                       .html('<i class="fa-solid fa-triangle-exclamation me-2"></i> Error al guardar. Intenta de nuevo.')
                       .fadeIn().delay(3000).fadeOut();
            }
        });
    });
});

$('#formNuevoModulo').submit(function (e) {
    e.preventDefault();
    $.ajax({
        url: "<?= BASE_URL ?>admin/usuarios/backend/guardar_modulo.php",
        method: "POST",
        data: $(this).serialize() + "&accion=crear",
        dataType: "json",
        success: function (res) {
            if (res.status === 'success') {
                location.reload(); // Recarga para renderizar la nueva columna del módulo
            } else {
                alert(res.message);
            }
        },
        error: function () {
            alert('Error al registrar el módulo.');
        }
    });
});
</script>

<?php
if (isset($conexion)) {
    $conexion->close();
}
?>
