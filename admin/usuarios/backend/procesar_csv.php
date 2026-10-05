<!-- Aquí incluyes tu plantilla de menú -->
<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);



session_start();

require_once('../../../backend/conexion.php');


if (!isset($_SESSION['logueado']) || $_SESSION['rol'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$currentPage = basename($_SERVER['PHP_SELF']); // Para el menú

// Inicializa variables de resultado
$mensaje = "";
$contador = 0;
$duplicados = 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["archivoUsuarios"]) && $_FILES["archivoUsuarios"]["error"] === UPLOAD_ERR_OK) {
    $archivo = $_FILES["archivoUsuarios"]["tmp_name"];

    if ($conexion->connect_error) {
        die("Error de conexión: " . $conexion->connect_error);
    }

    if (($handle = fopen($archivo, "r")) !== FALSE) {
        // Leer encabezado
        fgetcsv($handle, 1000, ",");

        while (($datos = fgetcsv($handle, 1000, ",")) !== FALSE) {
            // Asegúrate de que el CSV tenga al menos 7 columnas ahora
            if (count($datos) >= 7) { 
                $usuario         = trim($datos[0]);
                $nombre_completo = trim($datos[1]);
                $contrasena_orig = trim($datos[2]);
                $email           = trim($datos[3]);
                $phone           = trim($datos[4]);
                $area            = trim($datos[5]);
                $rol             = trim($datos[6]);

                $contrasena = password_hash($contrasena_orig, PASSWORD_DEFAULT);

                // CORRECCIÓN: 7 signos de interrogación para 7 columnas
                $sql = "INSERT IGNORE INTO usuarios (usuario, contrasena, email, phone, nombre_completo, area, rol) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                
                // CORRECCIÓN: "sssssss" (7 letras 's' para 7 parámetros)
                $stmt->bind_param("sssssss", $usuario, $contrasena, $email, $phone, $nombre_completo, $area, $rol);
                
                $stmt->execute();

                if ($stmt->affected_rows > 0) $contador++;
                else $duplicados++;

                $stmt->close();
            }
        }

        fclose($handle);
        $conexion->close();

        $mensaje = "<div class='alert alert-success'>
                        <h4>✅ Carga completada</h4>
                        <p>Usuarios agregados: <strong>$contador</strong></p>
                        <p>Usuarios duplicados ignorados: <strong>$duplicados</strong></p>
                        <a href='../admin/usuarios/' class='btn btn-primary mt-2'>← Volver al listado</a>
                    </div>";
    } else {
        $mensaje = "<div class='alert alert-danger'>❌ No se pudo abrir el archivo CSV.</div>";
    }
} else {
    $mensaje = "<div class='alert alert-danger'>❌ Archivo no válido o no se ha subido correctamente.</div>";
}
?>


<div id="mainContent">
    <?= $mensaje ?>
</div>
