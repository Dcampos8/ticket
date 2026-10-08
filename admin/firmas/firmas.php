<?php

require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('firmas');
if (!esAdminOSuperior()) {
    http_response_code(403);
    exit('No autorizado');
}
$csrfToken = asegurarTokenCsrf();

require_once ROOT_PATH . 'admin/menu.php';

$usuarios = $conexion->query("
  SELECT id, nombre_completo, area
  FROM usuarios
  ORDER BY nombre_completo ASC
");
?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__firmas__firmas.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__firmas__firmas.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container">
<h4>Generar firma de correo</h4>

<div class="table-responsive mb-3">
<table class="table table-bordered table-sm">
<thead class="table-dark">
<tr>
<th>Usuario</th>
<th>Área</th>
<th>Puesto</th>
<th>Teléfono</th>
<th>Correo destino</th>
<th>Acción</th>
</tr>
</thead>

<tbody>

<?php while($u = $usuarios->fetch_assoc()): ?>

<form method="POST" action="backend/procesar_firma.php">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
<tr>

<td><?= htmlspecialchars($u['nombre_completo']) ?></td>

<td><?= htmlspecialchars($u['area']) ?></td>

<td>
<input type="text"
name="puesto"
class="form-control form-control-sm"
placeholder="Ej. Gerente de Operaciones"
required>
</td>

<td>
    <input type="text" 
           name="telefono" 
           class="form-control form-control-sm" 
           placeholder="Teléfono">
</td>

<td>
<input type="email"
name="correo"
class="form-control form-control-sm"
placeholder="correo@empresa.com"
required>
</td>

<td>

<input type="hidden" name="user_id" value="<?= $u['id'] ?>">

<button class="btn btn-sm btn-primary">
Generar y enviar
</button>

</td>

</tr>
</form>

<?php endwhile; ?>

</tbody>
</table>
</div>
</div>
