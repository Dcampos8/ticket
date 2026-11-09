<?php
// 1. Cargar la configuración global
require_once(__DIR__ . '/../../../config.php');

// 2. Control de Sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3. Verificar seguridad (Solo Admin)
if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// 4. Conexión a la base de datos
require_once(ROOT_PATH . 'backend/conexion.php');

// 5. Cargar el menú usando la ruta absoluta
include(ROOT_PATH . 'admin/menu.php');

$msg = $err = '';
$resumen = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['archivo_csv'])) {
    $archivo = $_FILES['archivo_csv'];

    // Validar subida del archivo
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        $err = "Error al subir el archivo al servidor.";
    } else {
        $ext = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        
        if ($ext !== 'csv') {
            $err = "Por favor, suba un archivo en formato <strong>.CSV</strong>.";
        } else {
            $handle = fopen($archivo['tmp_name'], 'r');
            
            if ($handle !== false) {
                // Preparar consulta de inserción / actualización masiva
                // Nota: ON DUPLICATE KEY UPDATE requiere que 'employee_id' sea PRIMARY KEY o UNIQUE en la BD
                $stmt = $conexion->prepare("
                    INSERT INTO employees (employee_id, employee_name, puesto, created_at)
                    VALUES (?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        employee_name = VALUES(employee_name),
                        puesto = VALUES(puesto)
                ");

                $fila = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $fila++;
                    
                    // Saltar la primera fila si contiene encabezados
                    if ($fila === 1 && isset($_POST['has_header'])) {
                        continue;
                    }

                    // Extraer y limpiar datos de las columnas A, B, C
                    $emp_id   = trim($data[0] ?? '');
                    $emp_name = trim($data[1] ?? '');
                    $puesto   = trim($data[2] ?? '');

                    // Omitir filas vacías
                    if (empty($emp_id) || empty($emp_name) || empty($puesto)) {
                        $resumen['omitidos']++;
                        continue;
                    }

                    $stmt->bind_param("sss", $emp_id, $emp_name, $puesto);
                    
                    if ($stmt->execute()) {
                        // mysqli retribuye 1 si fue INSERT y 2 si fue UPDATE
                        if ($stmt->affected_rows === 1) {
                            $resumen['insertados']++;
                        } elseif ($stmt->affected_rows === 2) {
                            $resumen['actualizados']++;
                        } else {
                            $resumen['omitidos']++; // Datos idénticos sin cambios
                        }
                    } else {
                        $resumen['omitidos']++;
                    }
                }

                fclose($handle);
                $stmt->close();

                $msg = "Proceso completado. Registros nuevos: <strong>{$resumen['insertados']}</strong> | Actualizados: <strong>{$resumen['actualizados']}</strong> | Omitidos/Sin cambios: <strong>{$resumen['omitidos']}</strong>.";
            } else {
                $err = "No se pudo abrir el archivo CSV.";
            }
        }
    }
}
?>

<div class="content" style="margin-left: 0; padding-top: 20px;">
    <div class="container-fluid mt-3">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-primary text-white p-3">
                        <h4 class="mb-0"><i class="fa-solid fa-file-csv me-2"></i>Carga y Actualización Masiva de Empleados</h4>
                    </div>
                    <div class="card-body p-4">

                        <?php if (!empty($msg)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-circle-check me-2"></i><?= $msg ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php elseif (!empty($err)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $err ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Formulario de Subida -->
                        <form method="post" enctype="multipart/form-data">
                            
                            <div class="alert alert-info border-0 shadow-sm mb-4">
                                <h6 class="fw-bold mb-2"><i class="fa-solid fa-info-circle me-2"></i>Formato requerido del archivo CSV:</h6>
                                <p class="mb-1 small">El archivo debe contener 3 columnas ordenadas de la siguiente manera:</p>
                                <ul class="mb-2 small">
                                    <li><strong>Columna 1:</strong> Número de empleado (ID)</li>
                                    <li><strong>Columna 2:</strong> Nombre completo</li>
                                    <li><strong>Columna 3:</strong> Puesto</li>
                                </ul>
                                <small class="text-muted"><i class="fa-solid fa-rotate me-1"></i><strong>Nota:</strong> Si el ID del empleado ya existe en la base de datos, sus datos se actualizarán automáticamente.</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Seleccionar archivo CSV</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fa-solid fa-upload"></i></span>
                                    <input type="file" class="form-control" name="archivo_csv" accept=".csv" required>
                                </div>
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="has_header" id="has_header" checked>
                                <label class="form-check-label" for="has_header">
                                    El archivo contiene encabezados en la primera fila (Omitir primera fila)
                                </label>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fa-solid fa-cloud-arrow-up me-2"></i>Procesar Archivo CSV
                                </button>
                                <a href="index.php" class="btn btn-outline-secondary">
                                    Cancelar y Volver
                                </a>
                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
if (isset($conexion)) {
    $conexion->close(); 
}
?>
