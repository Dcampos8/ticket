<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('inventario');
require('../../backend/conexion.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Recoger el ID y los campos generales
    $id             = intval($_POST['id']);
    $tipo_disp      = $conexion->real_escape_string($_POST['tipo_dispositivo']);
    $tipo_equipo    = $conexion->real_escape_string($_POST['tipo_equipo']);
    $marca          = $conexion->real_escape_string($_POST['marca']);
    $modelo         = $conexion->real_escape_string($_POST['modelo']);
    $no_serie       = $conexion->real_escape_string($_POST['no_serie']);
    $procesador     = $conexion->real_escape_string($_POST['procesador']);
    $ram            = $conexion->real_escape_string($_POST['ram']);
    $disco_duro     = $conexion->real_escape_string($_POST['disco_duro']);
    
    // 2. Recoger los NUEVOS campos de periféricos (¡Aquí estaba faltando!)
    $marca_teclado  = $conexion->real_escape_string($_POST['marca_teclado']);
    $modelo_teclado = $conexion->real_escape_string($_POST['modelo_teclado']);
    $marca_mouse    = $conexion->real_escape_string($_POST['marca_mouse']);
    $modelo_mouse   = $conexion->real_escape_string($_POST['modelo_mouse']);
    $marca_monitor  = $conexion->real_escape_string($_POST['marca_monitor']);
    $modelo_monitor = $conexion->real_escape_string($_POST['modelo_monitor']);

    // 3. Recoger área, responsable, estado y motivos
    $area           = $conexion->real_escape_string($_POST['area']);
    $responsable    = !empty($_POST['responsable']) ? intval($_POST['responsable']) : "NULL";
    $estado         = $conexion->real_escape_string($_POST['estado']);
    $motivo_baja    = $conexion->real_escape_string($_POST['motivo_baja'] ?? '');
    $motivo_rep     = $conexion->real_escape_string($_POST['motivo_reparacion'] ?? '');

    // 4. Ejecutar la consulta UPDATE incluyendo las columnas nuevas
    $sql = "UPDATE inventario_equipos SET 
                tipo_dispositivo = '$tipo_disp',
                tipo_equipo = '$tipo_equipo',
                marca = '$marca',
                modelo = '$modelo',
                no_serie = '$no_serie',
                procesador = '$procesador',
                ram = '$ram',
                disco_duro = '$disco_duro',
                marca_teclado = '$marca_teclado',
                modelo_teclado = '$modelo_teclado',
                marca_mouse = '$marca_mouse',
                modelo_mouse = '$modelo_mouse',
                marca_monitor = '$marca_monitor',
                modelo_monitor = '$modelo_monitor',
                area = '$area',
                responsable = $responsable,
                estado = '$estado',
                motivo_baja = '$motivo_baja',
                motivo_reparacion = '$motivo_rep'
            WHERE id = $id";

    if ($conexion->query($sql)) {
        echo "<script>alert('Equipo actualizado correctamente'); window.location='inventario.php';</script>";
    } else {
        echo "Error al actualizar: " . $conexion->error;
    }
}
?>
