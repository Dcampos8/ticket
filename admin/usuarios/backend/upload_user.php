<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
requerirModulo('usuarios');

$msg = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv'])) {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === 0) {
        $fileTmp = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($fileTmp, "r");

        if ($handle !== false) {
            $rowCount = 0;
            $inserted = 0;
            $updated = 0;

            while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                $rowCount++;
                if ($rowCount == 1) {
                    // Ignora encabezado
                    continue;
                }

                $employee_id   = trim($data[0] ?? '');
                $employee_name = trim($data[1] ?? '');
                $puesto        = trim($data[2] ?? '');

                if ($employee_id === '') continue;

                // Verificar si ya existe
                $stmtCheck = $conexion->prepare("SELECT id FROM employees WHERE employee_id=?");
                $stmtCheck->bind_param("s", $employee_id);
                $stmtCheck->execute();
                $res = $stmtCheck->get_result();
                $stmtCheck->close();

                if ($res->num_rows > 0) {
                    // Actualiza
                    $row = $res->fetch_assoc();
                    $stmtUpdate = $conexion->prepare("UPDATE employees SET employee_name=?, puesto=? WHERE id=?");
                    $stmtUpdate->bind_param("ssi", $employee_name, $puesto, $row['id']);
                    if ($stmtUpdate->execute()) $updated++;
                    $stmtUpdate->close();
                } else {
                    // Inserta nuevo
                    $stmtInsert = $conexion->prepare("INSERT INTO employees (employee_id, employee_name, puesto, created_at) VALUES (?, ?, ?, NOW())");
                    $stmtInsert->bind_param("sss", $employee_id, $employee_name, $puesto);
                    if ($stmtInsert->execute()) $inserted++;
                    $stmtInsert->close();
                }
            }

            fclose($handle);
            $msg = "Proceso completado: $inserted empleados insertados, $updated actualizados.";
        } else {
            $errors[] = "No se pudo abrir el archivo CSV.";
        }
    } else {
        $errors[] = "Debe seleccionar un archivo CSV válido.";
    }
}
include ROOT_PATH . 'admin/menu.php';
?>

<main class="container-fluid mt-4">
    <h3 class="mb-4">Subir CSV de Empleados</h3>

    <?php if($msg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>

    <?php if($errors): ?>
        <?php foreach($errors as $error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <div class="mb-3">
            <label class="form-label">Archivo CSV</label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            <small class="text-muted">El CSV debe tener columnas: employee_id, employee_name, puesto</small>
        </div>
        <button type="submit" name="upload_csv" class="btn btn-primary">Subir y Procesar</button>
    </form>

    <hr>
    <p>Ejemplo de CSV:</p>
    <pre>
employee_id,employee_name,puesto
12345,Juan Pérez,Almacén
12346,Ana López,Administración
12347,Carlos Ruiz,Operativo
    </pre>
</main>
</body>
</html>
