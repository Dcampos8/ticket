<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('avisos');

require_once(ROOT_PATH . 'admin/menu.php');


if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['publicar'])) {
    $titulo = $_POST['titulo'];
    $mensaje = $_POST['mensaje'];
    $color = $_POST['color'];
    $conexion->query("UPDATE avisos_sistema SET activo = 0");
    $stmt = $conexion->prepare("INSERT INTO avisos_sistema (titulo, mensaje, color_alerta) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $titulo, $mensaje, $color);
    $stmt->execute();
}

if (isset($_POST['quitar_aviso'])) {
    $conexion->query("UPDATE avisos_sistema SET activo = 0");
}

$res_actual = $conexion->query("SELECT * FROM avisos_sistema WHERE activo = 1 LIMIT 1");
$aviso_actual = $res_actual->fetch_assoc();
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__avisos__gestionar_aviso.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__avisos__gestionar_aviso.css') ?>">
<br>
<div class="container mt-5">
    <div class="row">
        
        <!-- SECCIÓN IZQUIERDA: CREAR -->
        <div class="col-lg-7">
            <div class="pro-card">
                <div class="pro-header">
                    <div class="indicator"></div>
                    <h4>Configurar Nuevo Aviso</h4>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label">Título del Mensaje</label>
                            <input type="text" name="titulo" class="form-control form-control-custom" placeholder="Ej. Aviso de Mantenimiento..." required>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-12">
                                <label class="form-label">Prioridad / Color</label>
                                <select name="color" class="form-control form-control-custom">
                                    <option value="warning">🟡 Amarillo (Precaución)</option>
                                    <option value="danger">🔴 Rojo (Urgente / Crítico)</option>
                                    <option value="info">🔵 Azul (Informativo)</option>
                                    <option value="success">🟢 Verde (Normal)</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Mensaje para los Operadores</label>
                            <textarea name="mensaje" class="form-control form-control-custom" rows="4" placeholder="Escribe los detalles aquí..." required></textarea>
                        </div>

                        <button type="submit" name="publicar" class="btn btn-valadez">
                            Publicar Comunicado
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- SECCIÓN DERECHA: ESTADO ACTUAL -->
        <div class="col-lg-5">
            <div class="pro-card">
                <div class="pro-header">
                    <div class="indicator" style="background: #64748b;"></div>
                    <h4>Estado Actual</h4>
                </div>
                <div class="card-body p-4">
                    <?php if ($aviso_actual): ?>
                        <div class="alert alert-<?= $aviso_actual['color_alerta'] ?> border-0 shadow-sm mb-4" style="border-radius: 12px; padding: 20px;">
                            <h5 class="fw-bold mb-1"><?= htmlspecialchars($aviso_actual['titulo']) ?></h5>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($aviso_actual['mensaje'])) ?></p>
                        </div>
                        
                        <form method="POST">
                            <button type="submit" name="quitar_aviso" class="btn btn-outline-danger w-100 fw-bold" style="border-radius: 12px; border-width: 2px;">
                                Finalizar y Quitar Aviso
                            </button>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">
                            <span style="font-size: 40px;">📢</span>
                            <p class="mt-2 fw-bold">No hay avisos activos</p>
                            <p class="small">El módulo de tickets se muestra normal.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>
