<?php
/* ==========================================================================
   CONFIGURACIÓN Y AUTENTICACIÓN
   ========================================================================== */
require_once('../../config.php'); // Se asume que aquí defines BASE_PATH o la conexión

if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

include '../header.php';

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

<style>
    :root {
        --primary-valadez: #1e3a8a;
        --accent-valadez: #e31e24;
    }
    body { background-color: #f1f5f9; color: #334155; font-family: 'Segoe UI', sans-serif; }
    .main-container { max-width: 900px; margin: 20px auto; }
    .card-custom { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); overflow: hidden; }
    .card-header-val { background: var(--primary-valadez); color: white; padding: 15px 20px; border: none; }
    .header-title { font-weight: 700; text-transform: uppercase; letter-spacing: 1px; font-size: 1.1rem; margin: 0; }
    
    .data-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; background: #fff; padding: 20px; border-bottom: 2px solid #f1f5f9; }
    .data-item { display: flex; flex-direction: column; }
    .data-label { font-size: 0.75rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; margin-bottom: 2px; }
    .data-value { font-size: 0.95rem; font-weight: 600; color: #1e293b; }
    
    .form-section { padding: 25px; background: white; }
    .form-label { font-weight: 700; color: #475569; margin-bottom: 8px; font-size: 0.9rem; }
    .form-control { border: 1.5px solid #e2e8f0; border-radius: 8px; padding: 10px; transition: all 0.3s; }
    .form-control:focus { border-color: var(--primary-valadez); box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1); }
    
    .btn-submit { 
        background: linear-gradient(135deg, #059669 0%, #10b981 100%); 
        border: none; color: white; font-weight: 700; padding: 12px 30px; 
        border-radius: 8px; width: 100%; margin-top: 10px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        transition: transform 0.2s;
    }
    .btn-submit:hover { transform: translateY(-2px); color: white; opacity: 0.9; }
</style>

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