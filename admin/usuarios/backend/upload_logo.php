<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../../../backend/conexion.php';

$target_dir = "../uploads/";

// Crear carpeta si no existe
if (!file_exists($target_dir)) {
    mkdir($target_dir, 0777, true);
}

// Verificar si se envió el archivo
if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    die("Error: No se ha seleccionado ningún archivo o hubo un problema al subirlo.");
}

$file = $_FILES['logo'];
$fileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));

// Validar tipo de archivo permitido
$allowed_types = ['jpg', 'jpeg', 'png', 'webp'];
if (!in_array($fileType, $allowed_types)) {
    die("Error: Solo se permiten archivos JPG, JPEG, PNG o WEBP.");
}

// Generar nombre único
$newFileName = time() . "_logo." . $fileType;
$target_file = $target_dir . $newFileName;

// Mover archivo subido
if (move_uploaded_file($file["tmp_name"], $target_file)) {

    // Actualizar el logo en la tabla quotations (ajusta el WHERE si quieres afectar otra fila)
    $query = "UPDATE quotations SET company_logo = ? WHERE id = 1";
    $stmt = $conexion->prepare($query);
    $stmt->bind_param("s", $newFileName);

    if ($stmt->execute()) {
        echo "<script>
                alert('✅ Logo actualizado correctamente');
                window.location.href = 'mis_tickets.php';
              </script>";
    } else {
        echo "Error al actualizar la base de datos: " . $stmt->error;
    }

} else {
    echo "Error al mover el archivo al servidor.";
}
?>
