<?php
session_start();

if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    exit('No autorizado');
}

require '../../../backend/conexion.php';

$query = "
SELECT 
    pl.num_linea,
    CASE
        WHEN pl.manual_employee_name IS NOT NULL 
            THEN pl.manual_employee_name
        ELSE COALESCE(pd.employee_name, 'Sin asignar')
    END AS employee_name
FROM phone_lines pl
LEFT JOIN phone_deliveries pd 
    ON pd.id = (
        SELECT id 
        FROM phone_deliveries 
        WHERE REPLACE(REPLACE(REPLACE(num_linea,' ',''),'-',''),'+52','') =
              REPLACE(REPLACE(REPLACE(pl.num_linea,' ',''),'-',''),'+52','')
        ORDER BY delivery_date DESC
        LIMIT 1
    )
ORDER BY pl.num_linea ASC
";

$result = $conexion->query($query);

header('Content-Type: text/vcard; charset=utf-8');
header('Content-Disposition: attachment; filename=lineas_empresa.vcf');

while ($row = $result->fetch_assoc()) {

    $nombre = "{$row['employee_name']}";
    $numero = preg_replace('/[^0-9]/', '', $row['num_linea']);

    echo "BEGIN:VCARD\r\n";
    echo "VERSION:3.0\r\n";
    echo "FN:$nombre\r\n";
    echo "N:$nombre;;;;\r\n";
    echo "TEL;TYPE=CELL:$numero\r\n";
    echo "END:VCARD\r\n";
}
exit;
