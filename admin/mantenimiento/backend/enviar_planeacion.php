<?php
// 1. CONFIGURACIÓN DE ERRORES Y LIBRERÍAS
error_reporting(E_ALL);
ini_set('display_errors', 1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../../../backend/PHPMailer/src/Exception.php';
require '../../../backend/PHPMailer/src/PHPMailer.php';
require '../../../backend/PHPMailer/src/SMTP.php';
require('../../../backend/conexion.php');

$conexion->set_charset("utf8");

// 2. PROCESAMIENTO DE FECHAS
if (!isset($_POST['trimestre_val'])) die("Error: No se recibió el trimestre.");

$trimestre = intval($_POST['trimestre_val']);
$anio_act = date('Y');
$meses = [
    1 => ['01-01', '03-31'],
    2 => ['04-01', '06-30'],
    3 => ['07-01', '09-30'],
    4 => ['10-01', '12-31']
];

$fecha_desde = $anio_act . "-" . $meses[$trimestre][0];
$fecha_hasta = $anio_act . "-" . $meses[$trimestre][1];
$destinatarios = $_POST['correos'];
$mensaje_extra = $_POST['mensaje_extra'];

/* 3. CONSULTA SQL
   Mantenemos la unión con inventario_equipos solo por si necesitas 
   filtrar por estado activo en el futuro, pero no extraemos esos campos.
*/
$query = "
SELECT 
    p.fecha_programada,
    pm.tipo AS tipo_servicio,
    pm.area,
    u.nombre_completo AS encargado
FROM programacion_mantenimiento p
JOIN plan_mantenimiento pm ON p.id_plan = pm.id
JOIN inventario_equipos inv ON pm.id_equipo = inv.id
LEFT JOIN usuarios u ON pm.responsable = u.id
LEFT JOIN mantenimientos_realizados mr ON mr.id_programacion = p.id
WHERE mr.id_programacion IS NULL 
  AND (p.fecha_programada BETWEEN '$fecha_desde' AND '$fecha_hasta')
ORDER BY p.fecha_programada ASC
";

$resultado = $conexion->query($query);

if (!$resultado || $resultado->num_rows == 0) {
    header("Location: ../programacion_mantenimientos.php?status=vacio");
    exit();
}

/* 4. CONSTRUCCIÓN DEL HTML (SIN EQUIPO NI SERIE) */
$nombres_trim = [1 => "1er Trimestre", 2 => "2do Trimestre", 3 => "3er Trimestre", 4 => "4to Trimestre"];
$html_correo = "
<div style='font-family: Arial, sans-serif; max-width: 800px; border: 1px solid #ddd; padding: 20px; color: #333;'>
    <div style='background: #1e3a8a; color: white; padding: 20px; text-align: center; border-radius: 5px;'>
        <h2 style='margin:0;'>Planeación de Mantenimientos IT</h2>
        <p style='margin:5px 0 0 0;'>{$nombres_trim[$trimestre]} $anio_act</p>
    </div>
    
    <p style='margin: 20px 0;'>Buen día, se comparte la lista de mantenimientos pendientes para el periodo seleccionado:</p>
    
    <table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%; font-size: 12px; border-color: #ccc;'>
        <thead>
            <tr style='background: #f8f9fa; color: #1e3a8a;'>
                <th width='20%'>Fecha</th>
                <th width='30%'>Ubicación</th>
                <th width='20%'>Servicio</th>
                <th width='30%'>Responsable</th>
            </tr>
        </thead>
        <tbody>";

while ($row = $resultado->fetch_assoc()) {
    $f = date('d/m/Y', strtotime($row['fecha_programada']));
    $encargado = !empty($row['encargado']) ? $row['encargado'] : 'Sin asignar';

    $html_correo .= "
        <tr>
            <td align='center' style='font-weight:bold;'>$f</td>
            <td align='center' style='color: #e31e24; font-weight: bold;'>{$row['area']}</td>
            <td align='center'>{$row['tipo_servicio']}</td>
            <td>$encargado</td>
        </tr>";
}
$html_correo .= "</tbody></table>";

if (!empty($mensaje_extra)) {
    $html_correo .= "<div style='margin-top: 20px; padding: 15px; background: #f1f5f9; border-left: 4px solid #1e3a8a;'>
        <strong>Mensaje adicional:</strong><br>" . nl2br(htmlspecialchars($mensaje_extra)) . "</div>";
}
$html_correo .= "</div>";

/* 5. ENVÍO CON PHPMailer */
$mail = new PHPMailer(true);

try {
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com'; 
    $mail->SMTPAuth   = true;
    require_once(__DIR__ . '/../../../config/env.php');
    $mail->Username   = env('MAIL_MENSAJERIA_USER');
    $mail->Password   = env('MAIL_MENSAJERIA_PASS');           
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom('noreply@mensajeriatv.site', 'Sistemas - Transportes Valadez');
    
    $lista_correos = explode(',', $destinatarios);
    foreach($lista_correos as $correo) {
        $c = trim($correo);
        if (filter_var($c, FILTER_VALIDATE_EMAIL)) $mail->addAddress($c);
    }

    $mail->isHTML(true);
    $mail->Subject = "Planeacion Mantenimiento: {$nombres_trim[$trimestre]}";
    $mail->Body    = $html_correo;

    $mail->send();
    header("Location: ../programacion_mantenimientos.php?status=enviado");
} catch (Exception $e) {
    echo "Error: {$mail->ErrorInfo}";
}