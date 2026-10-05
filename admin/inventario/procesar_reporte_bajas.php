<?php
session_start();
require('../../backend/conexion.php');

if (!isset($_SESSION['logueado'])) {
    header("Location: ../login.php");
    exit();
}

$query = "SELECT e.*, u.nombre_completo 
          FROM inventario_equipos e 
          LEFT JOIN usuarios u ON e.responsable = u.id 
          WHERE e.estado = 'Baja'
          ORDER BY e.id DESC";

$resultado = $conexion->query($query);

if ($resultado->num_rows > 0) {
    
    $filename = "TV-CB-FR-02_Bajas_" . date('d-m-Y') . ".xlsx";
    
    // Cabeceras para forzar la descarga en formato Excel antiguo (compatible con HTML/CSS)
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=$filename");
    header("Pragma: no-cache");
    header("Expires: 0");

    // El truco para los acentos es imprimir el BOM de UTF-8
    echo "\xEF\xBB\xBF"; 

    echo "
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
        <meta http-equiv='Content-type' content='text/html;charset=utf-8' />
        <style>
            table { border-collapse: collapse; }
            th { background-color: #000000; color: #ffffff; font-family: Arial; font-size: 11pt; border: 0.5pt solid #000; }
            td { border: 0.5pt solid #000; font-family: Arial; font-size: 10pt; }
            /* ESTA CLASE FUERZA EL FORMATO TEXTO EN EXCEL */
            .formato-texto { mso-number-format:'\@'; } 
            .header-solicitud { font-weight: bold; font-size: 14pt; text-align: center; background-color: #F2F2F2; }
        </style>
    </head>
    <body>
    <table>
        <tr><th colspan='7' class='header-solicitud' style='color:#000'>SOLICITUD DE BAJA DE EQUIPO DE CÓMPUTO</th></tr>
        <tr><td colspan='7' style='text-align:center; font-weight:bold;'>Código: TV-CB-FR-02 | Generado el: " . date('d/m/Y H:i') . "</td></tr>
        <tr><td colspan='7' style='border:none;'>&nbsp;</td></tr>
        <thead>
            <tr>
                <th>No. Resguardo</th>
                <th>Equipo</th>
                <th>Marca</th>
                <th>Modelo</th>
                <th>No. Serie</th>
                <th>Área</th>
                <th>Responsable</th>
            </tr>
        </thead>
        <tbody>";

    while ($row = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['numero_resguardo'] . "</td>";
        echo "<td>" . $row['tipo_equipo'] . "</td>";
        echo "<td>" . $row['marca'] . "</td>";
        echo "<td>" . $row['modelo'] . "</td>";
        // Aplicamos la clase formato-texto aquí
        echo "<td class='formato-texto'>" . $row['no_serie'] . "</td>";
        echo "<td>" . $row['area'] . "</td>";
        echo "<td>" . $row['nombre_completo'] . "</td>";
        echo "</tr>";
    }

    echo "
        </tbody>
        <tr><td colspan='7' style='border:none;'>&nbsp;</td></tr>
        <tr><td colspan='7' style='border:none;'>&nbsp;</td></tr>
        <tr>
            <td colspan='2' style='border:none; border-top: 1pt solid black; text-align:center;'>Solicita (Usuario)</td>
            <td colspan='3' style='border:none;'></td>
            <td colspan='2' style='border:none; border-top: 1pt solid black; text-align:center;'>Autoriza (Sistemas)</td>
        </tr>
    </table>
    </body>
    </html>";
    
    exit();

} else {
    echo "<script>alert('No hay equipos en Baja'); window.location.href='inventario.php';</script>";
}
?>