<?php
// 1. Cargar la configuración global
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('avisos');

require_once($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');


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

<style>
    :root {
        --dark-valadez: #1e293b; /* Azul oscuro elegante */
        --accent-valadez: #ef4444; /* Rojo para detalles */
        --bg-light: #f8fafc;
    }

    body { background-color: var(--bg-light); font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; margin-top:100px;}

    /* Tarjeta Principal */
    .pro-card {
        background: white;
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 25px rgba(0,0,0,0.05);
        overflow: hidden;
        margin-bottom: 30px;
    }

    /* Encabezados con Estilo */
    .pro-header {
        background: white;
        padding: 25px 30px;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
    }

    .pro-header h4 {
        margin: 0;
        font-weight: 700;
        color: var(--dark-valadez);
        font-size: 1.25rem;
        letter-spacing: -0.025em;
        text-transform: uppercase;
    }

    /* Indicador de color lateral en el título */
    .indicator {
        width: 6px;
        height: 24px;
        background: var(--accent-valadez);
        border-radius: 10px;
        margin-right: 15px;
    }

    /* Formulario */
    .form-label {
        font-size: 0.85rem;
        font-weight: 600;
        color: #64748b;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .form-control-custom {
        border: 2px solid #f1f5f9;
        border-radius: 12px;
        padding: 12px 15px;
        transition: all 0.3s ease;
        font-size: 1rem;
    }

    .form-control-custom:focus {
        border-color: #cbd5e1;
        box-shadow: none;
        outline: none;
        background-color: #fff;
    }

    /* Botón */
    .btn-valadez {
        background: var(--dark-valadez);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 14px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        transition: all 0.3s ease;
        width: 100%;
    }

    .btn-valadez:hover {
        background: #0f172a;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        color: white;
    }

    /* Vista Previa */
    .preview-box {
        border-radius: 15px;
        padding: 20px;
        border: 1px dashed #cbd5e1;
        background: #f1f5f9;
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #94a3b8;
    }

    .empty-state i { font-size: 3rem; display: block; margin-bottom: 10px; }
</style>
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