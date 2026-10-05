<?php
// 1. Cargar la configuración global
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

// 2. Conexión a la base de datos (Usando ROOT_PATH)
require_once(ROOT_PATH . 'backend/conexion.php');

// 3. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 4. Verificar seguridad (Solo Admin)
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 5. Cargar el menú
include($_SERVER['DOCUMENT_ROOT'] . '/admin/menu.php');

$msg = '';

// Actualizar datos desde el modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_empleado'])) {
    $id = intval($_POST['edit_id'] ?? 0);
    $employee_id = trim($_POST['edit_employee_id'] ?? '');
    $employee_name = trim($_POST['edit_employee_name'] ?? '');
    $puesto = trim($_POST['edit_puesto'] ?? '');

    if ($id > 0) {
        $stmt = $conexion->prepare("UPDATE employees SET employee_id=?, employee_name=?, puesto=? WHERE id=?");
        $stmt->bind_param("sssi", $employee_id, $employee_name, $puesto, $id);
        if ($stmt->execute()) {
            $msg = "Empleado actualizado correctamente.";
        } else {
            $msg = "Error al actualizar: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $msg = "ID de empleado no válido.";
    }
}

// Obtener lista
$result = $conexion->query("SELECT id, employee_id, employee_name, puesto, created_at FROM employees ORDER BY employee_name ASC");
?>
<div class="content" style=" padding-top: 20px;">
    <div class="container-fluid mt-3">
        <h3 class="mb-4"><i class="fa-solid fa-users-gear me-2"></i>Lista de Empleados</h3>

        <?php if($msg): ?>
            <div class="alert alert-info shadow-sm border-start border-info border-4">
                <?= htmlspecialchars($msg) ?>
            </div>
            <script>
                setTimeout(() => window.location.href = 'index.php', 1500);
            </script>
        <?php endif; ?>
        
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaEmpleados" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <br><tr>
                                <th>ID</th>
                                <th>Número de Empleado</th>
                                <th>Nombre</th>
                                <th>Puesto</th>
                                <th>Creado</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($result && $result->num_rows > 0): ?>
                                <?php while($row = $result->fetch_assoc()): ?>
                                
                                    <tr>
                                        <td><strong><?= $row['id'] ?></strong></td>
                                        <td><?= htmlspecialchars($row['employee_id']) ?></td>
                                        <td><?= htmlspecialchars($row['employee_name']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['puesto']) ?></span></td>
                                        <td><?= date("d/m/Y", strtotime($row['created_at'])) ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary editarBtn"
                                                data-id="<?= $row['id'] ?>"
                                                data-employee_id="<?= htmlspecialchars($row['employee_id']) ?>"
                                                data-employee_name="<?= htmlspecialchars($row['employee_name']) ?>"
                                                data-puesto="<?= htmlspecialchars($row['puesto']) ?>">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Editar
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-4">No hay empleados registrados.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Edición -->
<div class="modal fade" id="editarModal" tabindex="-1" aria-labelledby="editarModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg">
      <form method="POST">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title" id="editarModalLabel">Editar Datos del Empleado</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body p-4">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="mb-3">
                <label class="form-label fw-bold">Número de Empleado</label>
                <input type="text" class="form-control" name="edit_employee_id" id="edit_employee_id" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Nombre Completo</label>
                <input type="text" class="form-control" name="edit_employee_name" id="edit_employee_name" required>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold">Puesto</label>
                <input type="text" class="form-control" name="edit_puesto" id="edit_puesto" required>
            </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" name="editar_empleado" class="btn btn-primary px-4">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
$(document).ready(function(){
    // Inicializar DataTable
    if ( ! $.fn.DataTable.isDataTable( '#tablaEmpleados' ) ) {
        $('#tablaEmpleados').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' // Traducción oficial
            },
            pageLength: 10,
            responsive: true
        });
    }

    // Delegar evento para el botón Editar
    $(document).on('click', '.editarBtn', function(){
        $('#edit_id').val($(this).data('id'));
        $('#edit_employee_id').val($(this).data('employee_id'));
        $('#edit_employee_name').val($(this).data('employee_name'));
        $('#edit_puesto').val($(this).data('puesto'));
        
        let myModal = new bootstrap.Modal(document.getElementById('editarModal'));
        myModal.show();
    });
});
</script>

<?php 
if (isset($conexion)) {
    $conexion->close(); 
}
?>
