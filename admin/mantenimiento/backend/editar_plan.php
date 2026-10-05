<?php
// Modo diagnóstico temporal: muestra errores en pantalla en vez de página en blanco.
// (Quitar estas 3 líneas una vez confirmado que todo funciona)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Rutas relativas blindadas (mismo patrón que plan_mantenimiento.php).
// OJO: antes este archivo no cargaba config.php, así que BASE_URL/ROOT_PATH
// no existían cuando header.php (incluido vía menu.php) los usaba -> error
// fatal de "constante indefinida" y página en blanco en producción.
$ruta_config = __DIR__ . '/../../../config.php';

if (!file_exists($ruta_config)) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; font-family:sans-serif;'><strong>Error Crítico:</strong> No se pudo localizar 'config.php' usando la ruta: <br><code>" . htmlspecialchars($ruta_config) . "</code></div>");
}
require_once($ruta_config); // Ya inicia la sesión, define BASE_URL/ROOT_PATH y carga backend/conexion.php

include('../../menu.php');

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die("<div style='padding:20px; background:#f8d7da; color:#721c24; font-family:sans-serif;'>ID de plan inválido.</div>");
}

$plan = $conexion->query("
    SELECT *
    FROM plan_mantenimiento
    WHERE id = $id
")->fetch_assoc();

if (!$plan) {
    die("<div style='padding:20px; background:#fff3cd; color:#856404; font-family:sans-serif;'>No se encontró ningún plan con ID $id.</div>");
}

$usuarios = $conexion->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo");
$equipos  = $conexion->query("SELECT id, marca, modelo, no_serie FROM inventario_equipos WHERE tipo_dispositivo IN ('PC','Laptop','Servidor') ORDER BY marca, modelo");
?>
<style>
    body { margin-top: 100px; }
</style>
<form method="POST" action="actualizar_plan.php" class="row g-3 p-4">

<input type="hidden" name="id" value="<?= $plan['id'] ?>">

<div class="col-md-4">
  <label class="form-label small fw-bold text-muted text-uppercase">Equipo</label>
  <select name="id_equipo" class="form-select" required>
    <?php while($e = $equipos->fetch_assoc()) { ?>
      <option value="<?= $e['id'] ?>"
        <?= $e['id'] == $plan['id_equipo'] ? 'selected' : '' ?>>
        <?= $e['marca'].' '.$e['modelo'].' ('.$e['no_serie'].')' ?>
      </option>
    <?php } ?>
  </select>
</div>

<div class="col-md-3">
  <label class="form-label small fw-bold text-muted text-uppercase">Área</label>
  <input name="area" class="form-control"
         value="<?= htmlspecialchars($plan['area']) ?>" required>
</div>

<div class="col-md-2">
  <label class="form-label small fw-bold text-muted text-uppercase">Tipo</label>
  <select name="tipo" class="form-select">
    <option <?= $plan['tipo']=='Preventivo'?'selected':'' ?>>Preventivo</option>
    <option <?= $plan['tipo']=='Correctivo'?'selected':'' ?>>Correctivo</option>
  </select>
</div>

<div class="col-md-2">
  <label class="form-label small fw-bold text-muted text-uppercase">Frecuencia</label>
  <select name="frecuencia" class="form-select">
    <option <?= $plan['frecuencia']=='Mensual'?'selected':'' ?>>Mensual</option>
    <option <?= $plan['frecuencia']=='Trimestral'?'selected':'' ?>>Trimestral</option>
    <option <?= $plan['frecuencia']=='Semestral'?'selected':'' ?>>Semestral</option>
    <option <?= $plan['frecuencia']=='Anual'?'selected':'' ?>>Anual</option>
  </select>
</div>

<div class="col-md-2">
  <label class="form-label small fw-bold text-muted text-uppercase">Responsable</label>
  <select name="responsable" class="form-select" required>
    <option value="">-- Selecciona --</option>
    <?php while($u = $usuarios->fetch_assoc()) { ?>
      <option value="<?= $u['id'] ?>"
        <?= ((int)$u['id'] === (int)$plan['responsable']) ? 'selected' : '' ?>>
        <?= htmlspecialchars($u['nombre_completo']) ?>
      </option>
    <?php } ?>
  </select>
</div>

<div class="col-md-1">
  <label class="form-label small fw-bold text-muted text-uppercase">Año</label>
  <input type="number" name="anio"
         value="<?= $plan['anio'] ?>" class="form-control">
</div>

<div class="col-md-12">
  <button class="btn btn-primary">Guardar cambios</button>
  <a href="../plan_mantenimiento.php" class="btn btn-secondary">Cancelar</a>
</div>

</form>
