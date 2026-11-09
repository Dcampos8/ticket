<?php
require_once("../admin/asistencias/backend/zk.php"); // librería zk
require('conexion.php');

$ip = "192.168.10.145";
$zk = new ZKLib($ip, 4370);

if ($zk->connect()) {

    $attendance = $zk->getAttendance();

    foreach ($attendance as $att) {

        $id_empleado = $att['id'];
        $fecha = $att['timestamp'];
        $tipo = $att['type'];


        $stmt = $conexion->prepare("INSERT INTO asistencias (id_empleado, fecha, tipo_registro, dispositivo_ip)
        VALUES (?, ?, ?, ?)");
        
        $ip_disp = "192.168.10.145";
        $stmt->bind_param("ssss", $id_empleado, $fecha, $tipo, $ip_disp);
        $stmt->execute();
    }

    $zk->disconnect();
}
