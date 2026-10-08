<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
requerirModulo('mantenimiento');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    exit('ID de plan inválido.');
}

$stmtPlan = $conexion->prepare('SELECT * FROM plan_mantenimiento WHERE id = ? LIMIT 1');
$stmtPlan->bind_param('i', $id);
$stmtPlan->execute();
$plan = $stmtPlan->get_result()->fetch_assoc();
$stmtPlan->close();

if (!$plan) {
    http_response_code(404);
    exit('No se encontró el plan solicitado.');
}

$usuarios = $conexion->query("SELECT id, nombre_completo FROM usuarios ORDER BY nombre_completo");
$equipos  = $conexion->query("SELECT id, marca, modelo, no_serie FROM inventario_equipos WHERE tipo_dispositivo IN ('PC','Laptop','Servidor') ORDER BY marca, modelo");

// El menú incluye el documento HTML; debe aparecer después de validar y consultar.
include ROOT_PATH . 'admin/menu.php';
?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__mantenimiento__backend__editar_plan.css?v=<?= filemtime(__DIR__ . '/../../../css/pages/admin__mantenimiento__backend__editar_plan.css') ?>">
<form method="POST" action="actualizar_plan.php" class="row g-3 p-4">

<input type="hidden" name="id" value="<?= $plan['id'] ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

<div class="col-md-4">
  <label class="form-label small fw-bold text-muted text-uppercase">Equipo</label>
  <select name="id_equipo" class="form-select" required>
    <?php while($e = $equipos->fetch_assoc()) { ?>
      <option value="<?= $e['id'] ?>"
        <?= $e['id'] == $plan['id_equipo'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($e['marca'].' '.$e['modelo'].' ('.$e['no_serie'].')', ENT_QUOTES, 'UTF-8') ?>
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
