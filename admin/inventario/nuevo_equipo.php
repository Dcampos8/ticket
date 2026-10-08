<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

/**
 * SISTEMA DE GESTIÓN DE ACTIVOS - TRANSPORTES VALADEZ
 * Registro de Nuevo Equipo
 */

/* ================= CARGA DE CONFIGURACIÓN ================= */
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

/* ================= OBTENER USUARIOS ================= */
$usuarios_res = $conexion->query("
    SELECT id, nombre_completo
    FROM usuarios
    ORDER BY nombre_completo
");

/* ================= GENERAR NUEVO RESGUARDO ================= */
$res = $conexion->query("
    SELECT numero_resguardo
    FROM inventario_equipos
    ORDER BY id DESC
    LIMIT 1
");

$ultimo = $res->fetch_assoc();

$folio = 'TV00001';

if ($ultimo && !empty($ultimo['numero_resguardo'])) {
    preg_match('/TV(\d+)/', $ultimo['numero_resguardo'], $m);

    if (isset($m[1])) {
        $numero = intval($m[1]) + 1;
        $folio = 'TV' . str_pad($numero, 5, '0', STR_PAD_LEFT);
    }
}
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>css/inventario_ne.css">

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__inventario__nuevo_equipo.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__inventario__nuevo_equipo.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container py-5">
<div class="row justify-content-center">
<div class="col-lg-10">

<div class="card card-edit">

<div class="card-header bg-white border-0 pt-4 px-4">
    <h3 class="fw-bold mb-0 text-dark">
        <i class="fas fa-plus-circle me-2 text-primary"></i>
        Nuevo Activo
    </h3>
    <p class="text-muted small mb-0">
        Registre un nuevo equipo en inventario.
    </p>
</div>

<div class="card-body p-4">

<form method="POST" action="guardar_equipo.php">

<h5 class="section-title">Información del Hardware</h5>

<div class="row g-3 mb-4">

<div class="col-md-4">
<label class="form-label">Tipo de Dispositivo</label>
<select name="tipo_dispositivo" class="form-select" required>
    <option value="">Seleccione...</option>
    <option value="PC">PC</option>
    <option value="Laptop">Laptop</option>
    <option value="Monitor">Monitor</option>
    <option value="Impresora">Impresora</option>
    <option value="Servidor">Servidor</option>
    <option value="Redes">Redes</option>
    <option value="Herramienta">Herramienta</option>
    <option value="GPS">GPS</option>
    <option value="Otro">Otro</option>
</select>
</div>

<div class="col-md-4">
<label class="form-label">Subtipo / Categoría</label>
<input name="tipo_equipo"
       class="form-control"
       placeholder="Ej: All in One, LaserJet...">
</div>

<div class="col-md-4">
<label class="form-label">Marca</label>
<input name="marca"
       class="form-control"
       required>
</div>

<div class="col-md-4">
<label class="form-label">Modelo</label>
<input name="modelo"
       class="form-control"
       required>
</div>

<div class="col-md-4">
<label class="form-label">Número de Serie</label>
<input name="no_serie"
       class="form-control fw-bold">
</div>

<div class="col-md-4">
<label class="form-label">Procesador</label>
<input name="procesador"
       class="form-control">
</div>

<div class="col-md-6">
<label class="form-label">Memoria RAM</label>
<input name="ram"
       class="form-control">
</div>

<div class="col-md-6">
<label class="form-label">Almacenamiento (Disco)</label>
<input name="disco_duro"
       class="form-control">
</div>

</div>

<!-- NUEVA SECCIÓN: PERIFÉRICOS Y ACCESORIOS -->
<h5 class="section-title">Periféricos y Accesorios</h5>

<div class="row g-3 mb-4">
    <!-- Teclado -->
    <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-keyboard me-1"></i> Marca Teclado</label>
        <input name="marca_teclado" class="form-control" placeholder="Ej: Logitech, Dell...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Modelo Teclado</label>
        <input name="modelo_teclado" class="form-control" placeholder="Ej: K120">
    </div>

    <!-- Mouse -->
    <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-computer-mouse me-1"></i> Marca Mouse</label>
        <input name="marca_mouse" class="form-control" placeholder="Ej: Logitech, HP...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Modelo Mouse</label>
        <input name="modelo_mouse" class="form-control" placeholder="Ej: M90">
    </div>

    <!-- Monitor -->
    <div class="col-md-6">
        <label class="form-label"><i class="fa-solid fa-desktop me-1"></i> Marca Monitor</label>
        <input name="marca_monitor" class="form-control" placeholder="Ej: Dell, Samsung...">
    </div>
    <div class="col-md-6">
        <label class="form-label">Modelo Monitor</label>
        <input name="modelo_monitor" class="form-control" placeholder="Ej: E2220H">
    </div>
</div>

<h5 class="section-title">Asignación y Resguardo</h5>

<div class="row g-3 mb-4">

<div class="col-md-4">
<label class="form-label">Área Destino</label>
<input name="area"
       class="form-control">
</div>

<div class="col-md-4">
<label class="form-label">Responsable</label>
<select name="responsable" class="form-select">
    <option value="">Sin asignar</option>

    <?php while($u = $usuarios_res->fetch_assoc()): ?>
        <option value="<?= $u['id'] ?>">
            <?= htmlspecialchars($u['nombre_completo']) ?>
        </option>
    <?php endwhile; ?>
</select>
</div>

<div class="col-md-4">
<label class="form-label">Número de Resguardo</label>
<input name="numero_resguardo"
       class="form-control readonly-custom text-center"
       value="<?= $folio ?>"
       readonly>
</div>

</div>

<h5 class="section-title">Estado Operativo</h5>

<div class="row g-3">

<div class="col-md-4">
<label class="form-label">Estatus</label>
<select name="estado"
        id="estado"
        class="form-select border-primary fw-bold text-primary">

    <option value="Activo" selected>🟢 Activo</option>
    <option value="Prestamo">🔵 Préstamo</option>
    <option value="Reparacion">🟠 Reparación</option>
    <option value="Baja">🔴 Baja</option>

</select>
</div>

<div class="col-md-8">

<div id="campo_motivo" style="display:none;">
<div class="alert alert-danger py-2">
<label class="form-label text-danger mb-1">
Motivo de Baja
</label>

<textarea name="motivo_baja"
          class="form-control"
          rows="2"></textarea>
</div>
</div>

<div id="campo_reparacion" style="display:none;">
<div class="alert alert-warning py-2">
<label class="form-label text-dark mb-1">
Descripción Técnica de la Falla
</label>

<textarea name="motivo_reparacion"
          class="form-control"
          rows="2"></textarea>
</div>
</div>

</div>
</div>

<div class="mt-5 text-end border-top pt-4">

<a href="inventario.php"
   class="btn btn-light px-4 me-2">
   <i class="fas fa-arrow-left me-1"></i>
   Volver
</a>

<button type="submit"
        class="btn btn-primary px-5 fw-bold shadow">
    <i class="fas fa-save me-1"></i>
    Guardar Equipo
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

function toggleCampos() {
    campoMotivo.style.display = 'none';
    campoReparacion.style.display = 'none';

    if (estadoSelect.value === 'Baja') {
        campoMotivo.style.display = 'block';
    }

    if (estadoSelect.value === 'Reparacion') {
        campoReparacion.style.display = 'block';
    }
}

toggleCampos();

estadoSelect.addEventListener('change', toggleCampos);

document.querySelector('form').addEventListener('submit', function(e){

    if(estadoSelect.value === 'Baja'){
        const v = document.getElementsByName('motivo_baja')[0].value.trim();
        if(v === ''){
            alert('Debe indicar motivo de baja');
            e.preventDefault();
        }
    }

    if(estadoSelect.value === 'Reparacion'){
        const v = document.getElementsByName('motivo_reparacion')[0].value.trim();
        if(v === ''){
            alert('Debe describir la falla');
            e.preventDefault();
        }
    }
});
</script>


</body>
</html>
