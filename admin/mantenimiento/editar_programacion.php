<?php
// 1. Cargar configuración y conexión usando la ruta raíz del servidor
require_once(__DIR__ . '/../../config.php');

// 2. Validación de sesión
if (!isset($_SESSION['logueado'])) {
    header("Location: /login.php");
    exit();
}

// 3. Incluir el menú principal con la misma ruta absoluta
include(ROOT_PATH . 'admin/menu.php');

// 4. Obtención y validación del parámetro ID
$id = isset($_GET['id']) ? intval($_GET['id']) : null;
if (!$id) {
    die("<div class='container mt-5'><div class='alert alert-danger'>ID no válido o no proporcionado.</div></div>");
}

// 5. Consulta a la base de datos
$query = "
SELECT 
  p.id,
  p.fecha_programada,
  pm.tipo,
  pm.frecuencia,
  pm.area,
  pm.responsable
FROM programacion_mantenimiento p
JOIN plan_mantenimiento pm ON p.id_plan = pm.id
WHERE p.id = ?
";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();

if (!$data) {
    die("<div class='container mt-5'><div class='alert alert-warning'>Registro no encontrado.</div></div>");
}
?>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__mantenimiento__editar_programacion.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__mantenimiento__editar_programacion.css') ?>">

<div class="container-fluid" id="editar" name="editar">
    <div class="card p-4 mb-4 shadow-sm" style="max-width: 800px; margin: 0 auto;">
        <h3 class="mb-4">
            <i class="fa-solid fa-pen-to-square me-2"></i> Editar Mantenimiento Programado
        </h3>

        <form method="POST" action="backend/guardar_edicion_programacion.php">

            <input type="hidden" name="id" value="<?= htmlspecialchars($data['id']) ?>">

            <!-- FECHA SOLO LECTURA -->
            <div class="mb-3">
                <label class="form-label fw-bold">Fecha programada</label>
                <input type="date"
                       class="form-control"
                       value="<?= htmlspecialchars($data['fecha_programada']) ?>"
                       readonly>
            </div>

            <!-- ÁREA -->
            <div class="mb-3">
                <label class="form-label fw-bold">Área</label>
                <input type="text"
                       name="area"
                       class="form-control"
                       value="<?= htmlspecialchars($data['area']) ?>"
                       required>
            </div>

            <!-- TIPO DE MANTENIMIENTO -->
            <div class="mb-3">
                <label class="form-label fw-bold">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="Preventivo" <?= $data['tipo'] == 'Preventivo' ? 'selected' : '' ?>>Preventivo</option>
                    <option value="Correctivo" <?= $data['tipo'] == 'Correctivo' ? 'selected' : '' ?>>Correctivo</option>
                </select>
            </div>

            <!-- FRECUENCIA -->
            <div class="mb-3">
                <label class="form-label fw-bold">Frecuencia</label>
                <select name="frecuencia" class="form-select">
                    <option value="Mensual" <?= $data['frecuencia'] == 'Mensual' ? 'selected' : '' ?>>Mensual</option>
                    <option value="Trimestral" <?= $data['frecuencia'] == 'Trimestral' ? 'selected' : '' ?>>Trimestral</option>
                    <option value="Semestral" <?= $data['frecuencia'] == 'Semestral' ? 'selected' : '' ?>>Semestral</option>
                    <option value="Anual" <?= $data['frecuencia'] == 'Anual' ? 'selected' : '' ?>>Anual</option>
                </select>
            </div>

            <!-- RESPONSABLE (EDITABLE) -->
            <div class="mb-3">
                <label class="form-label fw-bold">Responsable</label>
                <input type="text"
                       name="responsable"
                       class="form-control"
                       value="<?= htmlspecialchars($data['responsable']) ?>"
                       placeholder="Nombre del encargado o técnico"
                       required>
                <div class="form-text">Puedes cambiar el nombre o asignar a un nuevo responsable.</div>
            </div>

            <!-- BOTONES DE ACCIÓN -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="programacion_mantenimientos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-success px-4">Guardar cambios</button>
            </div>

        </form>
    </div>
</div>
