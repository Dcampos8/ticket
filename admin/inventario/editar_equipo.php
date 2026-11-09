<?php
/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * Edición de Equipo (Espejo del formulario de captura)
 */

// 1. CARGA DE CONFIGURACIÓN
$base_path = '/home/u568802486/domains/transportesvaladez.com/public_html/ticket/';

if (file_exists($base_path . 'config.php')) {
    require_once $base_path . 'config.php';
} else {
    die("Error Crítico: No se pudo cargar la configuración del sistema.");
}

// 2. SEGURIDAD: Verificación de sesión
if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 3. COMPONENTES DE INTERFAZ
include(ROOT_PATH . 'admin/menu.php');

/* ================= VALIDAR ID Y OBTENER DATOS ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('ID inválido'); window.location='inventario.php';</script>";
    exit();
}
$id = intval($_GET['id']);

$res_equipo = $conexion->query("SELECT * FROM inventario_equipos WHERE id = $id");
$equipo = $res_equipo->fetch_assoc();

if (!$equipo) {
    die("Error: El equipo con ID $id no existe en la base de datos.");
}

/* ================= CONSULTAS PARA SELECTS ================= */
// Obtener usuarios para el responsable
$usuarios_res = $conexion->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo");

// Obtener tipos que ya existen en la BD para el select
$tipos_existentes_res = $conexion->query("SELECT DISTINCT tipo_dispositivo FROM inventario_equipos WHERE tipo_dispositivo IS NOT NULL AND tipo_dispositivo != ''");
$tipos_bd = [];
while($t = $tipos_existentes_res->fetch_assoc()){
    $tipos_bd[] = $t['tipo_dispositivo'];
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>css/inventario_ne.css">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-lg border-0" style="border-radius: 15px; overflow: hidden;">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center py-3">
                    <h3 class="mb-0 fs-5"><i class="fas fa-edit me-2"></i> Editar Activo: <?= htmlspecialchars($equipo['numero_resguardo']) ?></h3>
                    <span class="badge bg-white text-primary">Modo Edición</span>
                </div>
                
                <div class="card-body p-4 bg-white">
                    <form method="POST" action="actualizar_equipo.php">
                        <input type="hidden" name="id" value="<?= $equipo['id'] ?>">
                        
                        <div class="border-bottom mb-4 pb-2 text-primary fw-bold">
                            <i class="fas fa-info-circle me-2"></i>Información del Dispositivo
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Tipo de Dispositivo</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-microchip text-secondary"></i></span>
                                    <select name="tipo_dispositivo" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php 
                                        $opciones_fijas = ["Computo", "Impresion", "Redes", "Herramienta", "Otro"];
                                        $todas_las_opciones = array_unique(array_merge($opciones_fijas, $tipos_bd));
                                        
                                        foreach($todas_las_opciones as $opc): 
                                            $selected = (trim($equipo['tipo_dispositivo']) == trim($opc)) ? 'selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($opc) ?>" <?= $selected ?>><?= htmlspecialchars($opc) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Subtipo / Categoría</label>
                                <input name="tipo_equipo" class="form-control" value="<?= htmlspecialchars($equipo['tipo_equipo']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Marca</label>
                                <input name="marca" class="form-control" value="<?= htmlspecialchars($equipo['marca']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Modelo</label>
                                <input name="modelo" class="form-control" value="<?= htmlspecialchars($equipo['modelo']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Número de Serie</label>
                                <input name="no_serie" class="form-control" value="<?= htmlspecialchars($equipo['no_serie']) ?>">
                            </div>
                        </div>

                        <div class="border-bottom mb-4 pb-2 text-primary fw-bold">
                            <i class="fas fa-tools me-2"></i>Especificaciones Técnicas
                        </div>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Procesador</label>
                                <input name="procesador" class="form-control" value="<?= htmlspecialchars($equipo['procesador']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">RAM</label>
                                <input name="ram" class="form-control" value="<?= htmlspecialchars($equipo['ram']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Disco</label>
                                <input name="disco_duro" class="form-control" value="<?= htmlspecialchars($equipo['disco_duro']) ?>">
                            </div>
                        </div>

                        <!-- NUEVA SECCIÓN: PERIFÉRICOS (TECLADO, MOUSE, MONITOR) -->
                        <div class="border-bottom mb-4 pb-2 text-primary fw-bold">
                            <i class="fas fa-desktop me-2"></i>Periféricos y Accesorios
                        </div>
                        <div class="row g-3 mb-4">
                            <!-- Teclado -->
                            <div class="col-md-6">
                                <label class="form-label small text-muted"><i class="fa-solid fa-keyboard me-1"></i> Marca Teclado</label>
                                <input name="marca_teclado" class="form-control" value="<?= htmlspecialchars($equipo['marca_teclado'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Modelo Teclado</label>
                                <input name="modelo_teclado" class="form-control" value="<?= htmlspecialchars($equipo['modelo_teclado'] ?? '') ?>">
                            </div>

                            <!-- Mouse -->
                            <div class="col-md-6">
                                <label class="form-label small text-muted"><i class="fa-solid fa-computer-mouse me-1"></i> Marca Mouse</label>
                                <input name="marca_mouse" class="form-control" value="<?= htmlspecialchars($equipo['marca_mouse'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Modelo Mouse</label>
                                <input name="modelo_mouse" class="form-control" value="<?= htmlspecialchars($equipo['modelo_mouse'] ?? '') ?>">
                            </div>

                            <!-- Monitor -->
                            <div class="col-md-6">
                                <label class="form-label small text-muted"><i class="fa-solid fa-desktop me-1"></i> Marca Monitor</label>
                                <input name="marca_monitor" class="form-control" value="<?= htmlspecialchars($equipo['marca_monitor'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted">Modelo Monitor</label>
                                <input name="modelo_monitor" class="form-control" value="<?= htmlspecialchars($equipo['modelo_monitor'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="border-bottom mb-4 pb-2 text-primary fw-bold">
                            <i class="fas fa-user-check me-2"></i>Asignación y Estado
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Área / Departamento</label>
                                <input name="area" class="form-control" value="<?= htmlspecialchars($equipo['area']) ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">Responsable</label>
                                <select name="responsable" class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php while($u = $usuarios_res->fetch_assoc()): ?>
                                        <option value="<?= $u['id'] ?>" <?= ($equipo['responsable'] == $u['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($u['nombre_completo']) ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small text-muted">ID Resguardo (Fijo)</label>
                                <input class="form-control text-center fw-bold text-primary bg-light" value="<?= $equipo['numero_resguardo'] ?>" readonly>
                            </div>
                            
                            <div class="col-md-12 mt-3">
                                <label class="form-label small fw-bold">Estatus del Equipo</label>
                                <select name="estado" id="estado" class="form-select border-primary" required>
                                    <option value="Activo" <?= ($equipo['estado'] == 'Activo') ? 'selected' : '' ?>>🟢 Activo</option>
                                    <option value="Prestamo" <?= ($equipo['estado'] == 'Prestamo') ? 'selected' : '' ?>>🔵 Préstamo</option>
                                    <option value="Reparacion" <?= ($equipo['estado'] == 'Reparacion') ? 'selected' : '' ?>>🟠 Reparación</option>
                                    <option value="Baja" <?= ($equipo['estado'] == 'Baja') ? 'selected' : '' ?>>🔴 Baja</option>
                                </select>
                            </div>
                        </div>

                        <div id="campo_motivo" class="mt-3" style="<?= ($equipo['estado'] == 'Baja') ? '' : 'display:none;' ?>">
                            <div class="alert alert-danger">
                                <label class="form-label fw-bold">Motivo de Baja</label>
                                <textarea name="motivo_baja" class="form-control" rows="2"><?= htmlspecialchars($equipo['motivo_baja'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div id="campo_reparacion" class="mt-3" style="<?= ($equipo['estado'] == 'Reparacion') ? '' : 'display:none;' ?>">
                            <div class="alert alert-warning">
                                <label class="form-label fw-bold">Detalle de la Falla</label>
                                <textarea name="motivo_reparacion" class="form-control" rows="2"><?= htmlspecialchars($equipo['motivo_reparacion'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">
                        <div class="text-end">
                            <button type="button" onclick="history.back()" class="btn btn-light px-4">Cancelar</button>
                            <button type="submit" class="btn btn-success px-4 fw-bold">
                                <i class="fas fa-save me-2"></i>Actualizar Cambios
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const estadoSelect = document.getElementById('estado');
const campoMotivo = document.getElementById('campo_motivo');
const campoReparacion = document.getElementById('campo_reparacion');

estadoSelect.addEventListener('change', function() {
    campoMotivo.style.display = 'none';
    campoReparacion.style.display = 'none';
    if(this.value === 'Baja') campoMotivo.style.display = 'block';
    if(this.value === 'Reparacion') campoReparacion.style.display = 'block';
});
</script>
