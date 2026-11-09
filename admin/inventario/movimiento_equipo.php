<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * Registro de Movimiento de Equipo
 */

/* ================= CARGA CONFIG ================= */
$base_path = '/home/u568802486/domains/transportesvaladez.com/public_html/ticket/';

if (file_exists($base_path . 'config.php')) {
    require_once $base_path . 'config.php';
} else {
    die("Error Crítico: No se pudo cargar la configuración del sistema.");
}

/* ================= SEGURIDAD ================= */
if (!isset($_SESSION['logueado'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

/* ================= COMPONENTES ================= */
include(ROOT_PATH . 'admin/menu.php');

/* ================= VALIDAR ID ================= */
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo "<script>alert('Equipo no válido'); window.location='inventario.php';</script>";
    exit();
}

$id = intval($_GET['id']);

/* ================= OBTENER EQUIPO ================= */
$stmt = $conexion->prepare("
    SELECT *
    FROM inventario_equipos
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$equipo = $stmt->get_result()->fetch_assoc();

if (!$equipo) {
    die("Equipo no encontrado.");
}

/* ================= LISTA DE USUARIOS ================= */
$usuarios = $conexion->query("
    SELECT id, nombre_completo
    FROM usuarios
    ORDER BY nombre_completo
");
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__inventario__movimiento_equipo.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__inventario__movimiento_equipo.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-8">

<div class="card card-mov">

<div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
    <div>
        <h3 class="fw-bold mb-0 text-dark">
            <i class="fas fa-exchange-alt me-2 text-primary"></i>
            Movimiento de Equipo
        </h3>
        <p class="text-muted small mb-0">
            Registrar cambio o movimiento del activo.
        </p>
    </div>

    <span class="badge bg-primary px-3 py-2">
        ID: <?= $equipo['id'] ?>
    </span>
</div>

<div class="card-body p-4">

<form method="POST" action="guardar_movimiento.php">

<input type="hidden" name="id_equipo" value="<?= $id ?>">

<h5 class="section-title">Información actual</h5>

<div class="info-box mb-4">
    <strong>Equipo:</strong>
    <?= htmlspecialchars($equipo['marca']) ?>
    <?= htmlspecialchars($equipo['modelo']) ?>
    <br>

    <strong>Serie:</strong>
    <?= htmlspecialchars($equipo['no_serie']) ?>
    <br>

    <strong>Área actual:</strong>
    <?= htmlspecialchars($equipo['area']) ?>
</div>

<h5 class="section-title">Datos del movimiento</h5>

<div class="row g-3">

<div class="col-md-6">
<label class="form-label">Tipo de movimiento</label>

<select name="tipo_movimiento"
        id="tipo_movimiento"
        class="form-select"
        required>

<option value="Asignacion">Asignación</option>
<option value="Cambio Responsable">Cambio Responsable</option>
<option value="Cambio Area">Cambio Área</option>
<option value="Prestamo">Préstamo</option>
<option value="Reparacion">Reparación</option>

</select>
</div>

<div class="col-md-6" id="area_box">
<label class="form-label">Nueva Área</label>

<input name="area_nueva"
       id="area_nueva"
       class="form-control"
       placeholder="Ingrese nueva área">
</div>

<div class="col-md-12" id="responsable_box">
<label class="form-label">Nuevo Responsable</label>

<select name="responsable_nuevo"
        id="responsable_nuevo"
        class="form-select">

<option value="">Seleccione responsable</option>

<?php while($u = $usuarios->fetch_assoc()) { ?>

<option value="<?= $u['id'] ?>">
<?= htmlspecialchars($u['nombre_completo']) ?>
</option>

<?php } ?>

</select>
</div>

<div class="col-md-12">
<label class="form-label">Observaciones</label>

<textarea name="observaciones"
          rows="3"
          class="form-control"
          placeholder="Detalles adicionales del movimiento..."></textarea>
</div>

</div>

<div class="mt-5 text-end border-top pt-4">

<a href="inventario.php"
   class="btn btn-light px-4 me-2">
   <i class="fas fa-arrow-left me-1"></i>
   Cancelar
</a>

<button type="submit" class="btn btn-primary px-5 fw-bold shadow">
    <i class="fas fa-save me-1"></i>
    Guardar Movimiento
</button>

</div>

</form>

</div>
</div>
</div>
</div>
</div>

<script>
const tipo = document.getElementById('tipo_movimiento');
const areaBox = document.getElementById('area_box');
const responsableBox = document.getElementById('responsable_box');

const inputArea = document.getElementById('area_nueva');
const selectResponsable = document.getElementById('responsable_nuevo');

function toggleCampos(){
    // Habilitar y mostrar todo por defecto
    areaBox.style.display = 'block';
    responsableBox.style.display = 'block';
    inputArea.disabled = false;
    selectResponsable.disabled = false;

    if(tipo.value === 'Cambio Responsable'){
        areaBox.style.display = 'none';
        inputArea.disabled = true;
    }

    if(tipo.value === 'Cambio Area'){
        responsableBox.style.display = 'none';
        selectResponsable.disabled = true;
    }

    if(tipo.value === 'Reparacion'){
        areaBox.style.display = 'none';
        responsableBox.style.display = 'none';
        inputArea.disabled = true;
        selectResponsable.disabled = true;
    }
}

toggleCampos();
tipo.addEventListener('change', toggleCampos);
</script>

</body>
</html>
