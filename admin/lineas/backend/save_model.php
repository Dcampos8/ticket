<?php
require '../../../backend/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $imei = trim($_POST['imei'] ?? '');
    $model = trim($_POST['model'] ?? '');

    if (!empty($imei) && !empty($model)) {
        // Solo guardar si el IMEI NO existe aún
        $check = $conexion->prepare("SELECT COUNT(*) AS total FROM phone_stock WHERE imei = ?");
        $check->bind_param("s", $imei);
        $check->execute();
        $exists = $check->get_result()->fetch_assoc()['total'] ?? 0;
        $check->close();

        if ($exists == 0) {
            $stmt = $conexion->prepare("INSERT INTO phone_stock (imei, model, status) VALUES (?, ?, 'in_stock')");
            $stmt->bind_param("ss", $imei, $model);
            if ($stmt->execute()) {
                echo "<script>alert('✅ Modelo registrado correctamente.'); window.history.back();</script>";
            } else {
                echo "<script>alert('⚠️ Error al registrar el modelo.'); window.history.back();</script>";
            }
            $stmt->close();
        } else {
            echo "<script>alert('⚠️ El IMEI ya está registrado.'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('⚠️ Datos incompletos.'); window.history.back();</script>";
    }
}
?>
