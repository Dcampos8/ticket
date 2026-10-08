<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../../../backend/conexion.php';

$id_entrega = $_GET['id_entrega'] ?? '';

if (empty($id_entrega)) {
    die("<div style='color:red;'>Error: parámetro de búsqueda inválido.</div>");
}

$query = "
  SELECT 
      d.id,
      COALESCE(d.delivery_date, 'Sin registro') AS fecha_entrega,
      COALESCE(s.brand, 'Sin registro') AS marca,
      COALESCE(s.model, 'Sin registro') AS modelo,
      COALESCE(d.imei, 'Sin registro') AS imei,
      COALESCE(d.num_linea, 'Sin registro') AS numero,
      COALESCE(e1.employee_name, 'Sin registro') AS entregado_a,
      COALESCE(e1.puesto, 'Sin registro') AS puesto_entregado_a,
      COALESCE(e2.employee_name, 'Sin registro') AS devuelto_por,
      COALESCE(e2.puesto, 'Sin registro') AS puesto_devuelto_por,
      COALESCE(r.fecha_devolucion, NOW()) AS fecha_devolucion,
      COALESCE(r.observaciones, 'Sin registro') AS observaciones
  FROM phone_deliveries d
  LEFT JOIN phone_returns r ON r.id_entrega = d.id
  LEFT JOIN employees e1 ON d.employee_name = e1.employee_name
  LEFT JOIN employees e2 ON r.id_empleado_devuelve = e2.employee_id
  LEFT JOIN phone_stock s ON s.imei = d.imei
  WHERE d.id = ?
  LIMIT 1
";

$stmt = $conexion->prepare($query);
$stmt->bind_param("i", $id_entrega);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if (!$data) {
    die("<div style='color:red;'>No se encontró información de devolución para esta entrega.</div>");
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Formato de Devolución de Celular</title>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__backend__formato_devolucion.css?v=<?= filemtime(__DIR__ . '/../../../css/pages/admin__usuarios__backend__formato_devolucion.css') ?>">
</head>
<body>

<h2>Formato de Devolución de Equipo Celular</h2>

<table>
  <tr><th>Empleado que tenía el equipo</th><td><?= htmlspecialchars($data['entregado_a']) ?></td></tr>
  <tr><th>Puesto (entregado a)</th><td><?= htmlspecialchars($data['puesto_entregado_a']) ?></td></tr>
  <tr><th>Empleado que devuelve</th><td><?= htmlspecialchars($data['devuelto_por']) ?></td></tr>
  <tr><th>Puesto (devuelve)</th><td><?= htmlspecialchars($data['puesto_devuelto_por']) ?></td></tr>
  <tr><th>Marca</th><td><?= htmlspecialchars($data['marca']) ?></td></tr>
  <tr><th>Modelo</th><td><?= htmlspecialchars($data['modelo']) ?></td></tr>
  <tr><th>IMEI</th><td><?= htmlspecialchars($data['imei']) ?></td></tr>
  <tr><th>Número</th><td><?= htmlspecialchars($data['numero']) ?></td></tr>
  <tr><th>Fecha Entrega</th><td><?= date('d/m/Y', strtotime($data['fecha_entrega'])) ?></td></tr>
  <tr><th>Fecha Devolución</th><td><?= date('d/m/Y H:i', strtotime($data['fecha_devolucion'])) ?></td></tr>
  <tr><th>Observaciones</th><td><?= nl2br(htmlspecialchars($data['observaciones'])) ?></td></tr>
</table>

<p style="margin-top:40px;">Firma de quien devuelve: ___________________________</p>
<p>Firma de quien recibe: ___________________________</p>

</body>
</html>
