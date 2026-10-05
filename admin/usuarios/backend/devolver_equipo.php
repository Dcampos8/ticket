<?php
require '../../../backend/conexion.php';
include('../../menu.php');

// POST: registrar devolución
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $employee_id_select = $_POST['employee_id_select'] ?? 0;
    $id_empleado_devuelve = $_POST['id_empleado_devuelve'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';

    if ($employee_id_select <= 0 || $id_empleado_devuelve <= 0) {
        die("<div class='alert alert-danger'>Empleado no válido.</div>");
    }

    // Buscar la entrega asociada a este employee_id
    $stmt = $conexion->prepare("SELECT id, num_linea, stock_id FROM phone_deliveries WHERE employee_id = ?");
    $stmt->bind_param("i", $id_empleado_devuelve);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $entrega = $resultado->fetch_assoc();
    $stmt->close();

    if (!$entrega) {
        die("<div class='alert alert-danger'>No se encontró una entrega asociada a ese empleado.</div>");
    }

    $id_entrega = $entrega['id'];
    $num_linea = $entrega['num_linea'] ?? null;
    $stock_id = $entrega['stock_id'] ?? null;

    // Buscar id_linea
    $id_linea = null;
    if ($num_linea) {
        $stmt_linea = $conexion->prepare("SELECT id FROM phone_lines WHERE num_linea = ?");
        $stmt_linea->bind_param("s", $num_linea);
        $stmt_linea->execute();
        $res_linea = $stmt_linea->get_result();
        if ($res_linea && $res_linea->num_rows > 0) {
            $id_linea = $res_linea->fetch_assoc()['id'];
        }
        $stmt_linea->close();
    }

    // INSERT en phone_returns
    $stmt_insert = $conexion->prepare("
        INSERT INTO phone_returns (id_entrega, id_linea, id_empleado_devuelve, observaciones)
        VALUES (?, ?, ?, ?)
    ");
    $stmt_insert->bind_param("iiis", $id_entrega, $id_linea, $id_empleado_devuelve, $observaciones);

    if (!$stmt_insert->execute()) {
        die("<div class='alert alert-danger'>Error al registrar la devolución: " . $stmt_insert->error . "</div>");
    }
    $stmt_insert->close();

    // Redirigir al formato de devolución
    echo "<script>window.location.href = 'formato_devolucion.php?id_entrega=$id_entrega';</script>";
    exit;
}

// Obtener todas las entregas activas desde phone_deliveries y traer marca/modelo de phone_stock
$entregas = $conexion->query("
    SELECT 
        pd.employee_id,
        COALESCE(e.employee_name, 'Sin nombre registrado') AS employee_name,
        pd.num_linea,
        COALESCE(ps.brand, 'No registrada') AS marca,
        COALESCE(ps.model, 'No registrado') AS modelo,
        COALESCE(pd.imei, 'No registrado') AS imei,
        COALESCE(pd.google_account, 'No registrada') AS google_account,
        COALESCE(pd.estatus_linea, 'Sin estatus') AS estatus_linea,
        COALESCE(e.puesto, 'No especificado') AS puesto
    FROM phone_deliveries pd
    LEFT JOIN employees e ON pd.employee_id = e.employee_id
    LEFT JOIN phone_stock ps ON pd.imei = ps.imei
    WHERE pd.num_linea IS NOT NULL AND TRIM(pd.num_linea) <> ''
    ORDER BY e.employee_name ASC
");

if (!$entregas) {
    die("<div class='alert alert-danger'>Error en la consulta: " . $conexion->error . "</div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Registrar Devolución de Celular</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="../../../css/devolucion.css">
</head>
<body class="p-4">

<form method="POST">
<h3 class="mb-4">Registrar Devolución de Equipo Celular</h3>

<div class="mb-3">
    <label class="form-label">Empleado que devuelve el equipo:</label>
<select class="form-select" name="employee_id_select" id="employee_id_select" onchange="mostrarDatosEntrega(this.value)" required>
    <option value="">Seleccionar empleado</option>
    <?php while ($row = $entregas->fetch_assoc()) { ?>
        <option value="<?= $row['employee_id'] ?>">
            <?= htmlspecialchars($row['employee_name']) ?> - Línea: <?= htmlspecialchars($row['num_linea']) ?>
        </option>
    <?php } ?>
</select>




</div>

<!-- Hidden con employee_id real -->
<input type="text" name="id_empleado_devuelve" id="id_empleado_devuelve">

<div id="datosEquipo" class="border rounded p-3 mb-3 bg-light"></div>

<div class="mb-3">
    <label class="form-label">Observaciones:</label>
    <textarea class="form-control" name="observaciones" rows="3"></textarea>
</div>

<button type="submit" class="btn btn-primary">Registrar Devolución y Generar Formato</button>
</form>

<script>
function mostrarDatosEntrega(employee_id) {
    if (!employee_id) {
        document.getElementById('datosEquipo').innerHTML = '';
        document.getElementById('id_empleado_devuelve').value = '';
        return;
    }

    fetch('obtener_datos_entrega.php?employee_id=' + employee_id)
        .then(resp => resp.json())
        .then(data => {
            if (data.error) {
                document.getElementById('datosEquipo').innerHTML = '<div class="text-danger">' + data.error + '</div>';
                document.getElementById('id_empleado_devuelve').value = '';
                return;
            }

            document.getElementById('datosEquipo').innerHTML = `
                <strong>Empleado:</strong> ${data.employee_name}<br>
                <strong>Puesto:</strong> ${data.puesto}<br>
                <strong>Número de Línea:</strong> ${data.num_linea}<br>
                <strong>Marca:</strong> ${data.marca}<br>
                <strong>Modelo:</strong> ${data.modelo}<br>
                <strong>IMEI:</strong> ${data.imei}<br>
                <strong>Cuenta Google:</strong> ${data.google_account}<br>
                <strong>Estatus Línea:</strong> ${data.estatus_linea}
            `;

            // Asignar employee_id real al hidden
            document.getElementById('id_empleado_devuelve').value = data.employee_id;
        })
        .catch(err => {
            console.error(err);
            document.getElementById('datosEquipo').innerHTML = '<div class="text-danger">Error al obtener datos.</div>';
            document.getElementById('id_empleado_devuelve').value = '';
        });
}
</script>

</body>
</html>
