<?php
/* ==========================================================================
   CONFIGURACIÓN Y AUTENTICACIÓN
   ========================================================================== */
require_once('../../config.php'); // Se asume que aquí defines BASE_PATH o la conexión

if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

require_once ROOT_PATH . 'admin/menu.php';

/* ==========================================================================
   VALIDACIÓN DE ID
   ========================================================================== */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<div class="alert alert-danger m-3">ERROR: No se recibió un ID de mantenimiento válido.</div>');
}

$id = intval($_GET['id']);

/* ==========================================================================
   CONSULTA DE DATOS DEL MANTENIMIENTO
   ========================================================================== */
// Usamos Prepared Statements para mayor seguridad
$sql = "
SELECT 
  pm.id,
  pm.fecha_programada,
  p.area,
  p.tipo,
  p.responsable AS id_responsable,
  u.nombre_completo AS nombre_responsable,
  e.tipo_dispositivo AS tipo_equipo,
  e.marca,
  e.modelo,
  e.no_serie
FROM programacion_mantenimiento pm
JOIN plan_mantenimiento p ON pm.id_plan = p.id
LEFT JOIN inventario_equipos e ON p.id_equipo = e.id
LEFT JOIN usuarios u ON p.responsable = u.id
WHERE pm.id = ?
";

$stmt = $conexion->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows == 0) {
    die('<div class="alert alert-danger m-3">ERROR: El registro de mantenimiento no existe.</div>');
}

$data = $res->fetch_assoc();
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__mantenimiento__registrar_mantenimiento.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__mantenimiento__registrar_mantenimiento.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="main-container px-3">
    <div class="card card-custom">
        <div class="card-header-val">
            <h4 class="header-title"><i class="fa-solid fa-screwdriver-wrench me-2"></i> Ejecución de Mantenimiento</h4>
        </div>

        <div class="data-grid">
            <div class="data-item">
                <span class="data-label">Equipo</span>
                <span class="data-value"><?= htmlspecialchars($data['tipo_equipo'].' '.$data['marca']) ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Modelo / Serie</span>
                <span class="data-value"><?= htmlspecialchars($data['modelo']) ?> / <small class="text-muted"><?= htmlspecialchars($data['no_serie']) ?></small></span>
            </div>
            <div class="data-item">
                <span class="data-label">Área</span>
                <span class="data-value"><?= htmlspecialchars($data['area']) ?></span>
            </div>
            <div class="data-item">
                <span class="data-label">Programado para</span>
                <span class="data-value text-primary"><?= date('d/m/Y', strtotime($data['fecha_programada'])) ?></span>
            </div>
        </div>

        <div class="form-section">
            <!-- Ruta ajustada al backend relativo a la raíz -->
            <form method="POST" action="<?= BASE_URL ?>admin/mantenimiento/backend/guardar_mantenimiento.php">
                <input type="hidden" name="id_programacion" value="<?= $id ?>">
                <input type="hidden" name="id_responsable_notif" value="<?= $data['id_responsable'] ?>">

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha Real de Trabajo</label>
                        <input type="date" name="fecha_real" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Responsable Asignado</label>
                        <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($data['nombre_responsable'] ?? 'Sin asignar') ?>" readonly>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Actividades Realizadas</label>
                    <textarea name="actividades" class="form-control" rows="3" placeholder="Detalla el servicio..." required></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Refacciones / Insumos</label>
                        <textarea name="refacciones" class="form-control" rows="2" placeholder="Materiales usados"></textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Observaciones Técnicas</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Estado final del equipo"></textarea>
                    </div>
                </div>

                <div class="p-3 rounded mb-4 shadow-sm border border-danger-subtle bg-danger-subtle d-flex align-items-center">
                    <div class="form-check form-switch">
                        <input type="checkbox" class="form-check-input" name="generar_ticket" value="1" id="switchTicket">
                        <label class="form-check-label fw-bold text-danger ms-2" for="switchTicket">
                            ¿Requiere generar Ticket Correctivo?
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-submit">
                    <i class="fa-solid fa-save me-2"></i> GUARDAR Y FINALIZAR
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>
