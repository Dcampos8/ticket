<?php
require '../../../backend/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = intval($_POST['id']);
    $new_imei = trim($_POST['new_imei']);

    if ($id > 0 && !empty($new_imei)) {

        // Obtener el IMEI anterior
        $getOld = $conexion->prepare("SELECT imei FROM phone_deliveries WHERE id = ?");
        $getOld->bind_param("i", $id);
        $getOld->execute();
        $result = $getOld->get_result();

        if ($result && $result->num_rows > 0) {
            $old = $result->fetch_assoc()['imei'];

            // Verificar si el nuevo IMEI ya existe en phone_stock
            $check = $conexion->prepare("SELECT id FROM phone_stock WHERE imei = ?");
            $check->bind_param("s", $new_imei);
            $check->execute();
            $checkResult = $check->get_result();

            if ($checkResult->num_rows > 0 && $old !== $new_imei) {
                // Verificamos si pertenece al mismo registro
                $rowCheck = $checkResult->fetch_assoc();
                if ($rowCheck['id'] != $id) {
                    echo "Error: El IMEI ya existe en phone_stock.";
                    exit;
                }
            }

            // Desactivar temporalmente las restricciones de clave foránea
            $conexion->query("SET FOREIGN_KEY_CHECKS=0");

            // Actualizar en phone_stock
            $updateStock = $conexion->prepare("UPDATE phone_stock SET imei = ? WHERE imei = ?");
            $updateStock->bind_param("ss", $new_imei, $old);
            $updateStockSuccess = $updateStock->execute();

            // Actualizar en phone_deliveries
            $updateDelivery = $conexion->prepare("UPDATE phone_deliveries SET imei = ? WHERE id = ?");
            $updateDelivery->bind_param("si", $new_imei, $id);
            $updateDeliverySuccess = $updateDelivery->execute();

            // Reactivar restricciones
            $conexion->query("SET FOREIGN_KEY_CHECKS=1");

            if ($updateStockSuccess && $updateDeliverySuccess) {
                echo "ok";
            } else {
                echo "Error al actualizar IMEI.";
            }

        } else {
            echo "Registro no encontrado.";
        }
    } else {
        echo "Datos inválidos.";
    }
}
?>
