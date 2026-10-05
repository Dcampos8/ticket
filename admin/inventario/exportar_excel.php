<?php
require('../../backend/conexion.php');
// 1. Incluimos la librería manualmente
require_once('xlsxwriter/xlsxwriter.class.php'); 

/* ================= FILTROS ================= */
$estado = isset($_GET['estado']) ? $_GET['estado'] : '';
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';

$where = "WHERE 1=1";

if($estado != ''){
    $where .= " AND e.estado = '" . $conexion->real_escape_string($estado) . "'";
}

if($buscar != ''){
    $b = $conexion->real_escape_string($buscar);
    $where .= " AND (e.tipo_equipo LIKE '%$b%' OR e.marca LIKE '%$b%' OR e.modelo LIKE '%$b%')";
}

/* ================= CONSULTA ================= */
// Nota: 'e.*' ya incluye los nuevos campos de teclado, mouse y monitor si están en la tabla
$query = "SELECT e.*, u.nombre_completo FROM inventario_equipos e 
          LEFT JOIN usuarios u ON e.responsable = u.id 
          $where ORDER BY e.id DESC";
$result = $conexion->query($query);

/* ================= HELPERS DE OBTENCIÓN DE FECHAS DE LANZAMIENTO ================= */

/**
 * Estima el año de lanzamiento basándose en el texto del procesador.
 */
function obtenerAnioProcesador($texto_procesador) {
    $texto = strtoupper($texto_procesador);
    
    $patrones = [
        '/5600GT/'      => 2024,
        '/7730U/'       => 2023,
        '/I3.*N305/'    => 2023, 
        '/12100/'       => 2022, 
        '/7320/'        => 2022, 
        '/3250U/'       => 2020,
        '/J4025/'       => 2019,
        '/3200G/'       => 2019,
        '/J3355/'       => 2016,
        '/J3060/'       => 2016,
        '/9410/'        => 2016, 
        '/7210/'        => 2015, 
        '/E3.*1220/'    => 2015, 
        '/G3250/'       => 2014,
        '/G2030/'       => 2013,
        '/G2010/'       => 2013,
        '/3220/'        => 2012, 
        '/2120/'        => 2011, 
        '/2100/'        => 2011, 
        '/E3400/'       => 2010, 
        '/E5800/'       => 2010, 
        '/455/'         => 2010, 
        '/450/'         => 2010, 
        '/270/'         => 2010, 
        '/E7400/'       => 2008  
    ];

    foreach ($patrones as $patron => $anio) {
        if (preg_match($patron, $texto)) {
            return $anio;
        }
    }
    return null; 
}

/**
 * Estima el año de lanzamiento para Periféricos mediante expresiones de Modelo.
 */
function obtenerAnioPorModeloPeriferico($modelo, $tipo_dispositivo) {
    $modelo_upper = strtoupper($modelo);

    $patrones_modelos = [
        // --- MONITORES ---
        '/E1916H|E2016H/'   => 2016, 
        '/E2222H|E2422H/'   => 2022, 
        '/22MP420|24MP400/' => 2022, 
        '/V194|V203/'       => 2017, 
        
        // --- TECLADOS / MOUSES / KITS ---
        '/MK220|K120|MK270|MK120/' => 2021,
        '/DELL-KB216|KB216/'      => 2018,
        '/MS116|KM636/'            => 2017,

        // --- IMPRESORAS ---
        '/L3110|L3150/'     => 2018, 
        '/L4150|L4260/'     => 2021, 
        '/M105|M205/'       => 2015,
        '/HP1102|P1102/'    => 2010, 
        
        // --- ROUTERS / REDES ---
        '/TLWR841N/'        => 2014, 
        '/ARCHERC6/'        => 2019,
    ];

    foreach ($patrones_modelos as $patron => $anio) {
        if (preg_match($patron, $modelo_upper)) {
            return $anio;
        }
    }

    // Buscar si el modelo incluye explícitamente un año de 4 dígitos (ej. 2021)
    if (preg_match('/\b(20[1-2][0-9])\b/', $modelo_upper, $matches)) {
        return intval($matches[1]);
    }

    switch ($tipo_dispositivo) {
        case 'MOUSE':
        case 'TECLADO':
            return 2023;
        case 'MONITOR':
            return 2019;
        case 'IMPRESORA':
        case 'ROUTER':
        default:
            return 2018;
    }
}

/**
 * Evalúa si una impresora es óptima basándose en las necesidades del Área.
 */
function evaluarImpresoraPorArea($modelo, $area) {
    $modelo_upper = strtoupper($modelo);
    $area_upper = strtoupper(trim($area));

    $areas_alto_volumen = ['FACTURACION', 'EMBARQUES', 'ALMACEN', 'LOGISTICA', 'CONTABILIDAD', 'TRAFICO'];
    $es_area_critica = false;
    
    foreach ($areas_alto_volumen as $area_critica) {
        if (strpos($area_upper, $area_critica) !== false) {
            $es_area_critica = true;
            break;
        }
    }

    if ($es_area_critica) {
        if (preg_match('/L3110|L3150|L4150|L4260|DESKJET|INK_ADVANTAGE/I', $modelo_upper)) {
            return "No óptima (Requiere Láser / Alto Rendimiento)";
        }
        return "Óptima para el área";
    }

    return "Óptima para el área";
}

/* ================= GENERACIÓN DEL XLSX ================= */

// Estructura de cabeceras incluyendo las nuevas columnas de periféricos y sus cálculos individuales
$header = array(
    'ID' => 'integer',
    'Resguardo' => 'string',
    'Tipo' => 'string',
    'Equipo' => 'string',
    'Marca' => 'string',
    'Modelo' => 'string',
    'Serie' => 'string',
    'Procesador' => 'string',
    'RAM' => 'string',
    'Disco' => 'string',
    // Nuevas columnas de periféricos integradas en el Excel
    'Marca Teclado' => 'string',
    'Modelo Teclado' => 'string',
    'Año Teclado' => 'string',
    'Antigüedad Teclado' => 'string',
    'Marca Mouse' => 'string',
    'Modelo Mouse' => 'string',
    'Año Mouse' => 'string',
    'Antigüedad Mouse' => 'string',
    'Marca Monitor' => 'string',
    'Modelo Monitor' => 'string',
    'Año Monitor' => 'string',
    'Antigüedad Monitor' => 'string',
    'Área' => 'string',
    'Responsable' => 'string',
    'Estado' => 'string',
    'Fecha Alta' => 'date',
    'Motivo Baja' => 'string',
    'Año Fabricación' => 'string',
    'Antigüedad Hardware' => 'string',
    'Estatus Ciclo Vida' => 'string',
    'Rendimiento Oficina' => 'string'
);

$writer = new XLSXWriter();
$writer->writeSheetHeader('Inventario', $header);

$anio_actual = intval(date('Y')); 

while($row = $result->fetch_assoc()){
    
    $tipo = strtoupper(trim($row['tipo_dispositivo']));
    
    // Extracción de datos de los nuevos campos de periféricos
    $marca_teclado  = isset($row['marca_teclado']) ? $row['marca_teclado'] : '';
    $modelo_teclado = isset($row['modelo_teclado']) ? $row['modelo_teclado'] : '';
    $marca_mouse    = isset($row['marca_mouse']) ? $row['marca_mouse'] : '';
    $modelo_mouse   = isset($row['modelo_mouse']) ? $row['modelo_mouse'] : '';
    $marca_monitor  = isset($row['marca_monitor']) ? $row['marca_monitor'] : '';
    $modelo_monitor = isset($row['modelo_monitor']) ? $row['modelo_monitor'] : '';

    // --- CÁLCULOS INDIVIDUALES PARA PERIFÉRICOS INTEGRADOS ---
    // Teclado
    $anio_teclado_val = !empty($modelo_teclado) ? obtenerAnioPorModeloPeriferico($modelo_teclado, 'TECLADO') : null;
    $anio_teclado_txt = $anio_teclado_val ? $anio_teclado_val : 'N/D';
    $ant_teclado_txt  = $anio_teclado_val ? ($anio_actual - $anio_teclado_val) . " " . (($anio_actual - $anio_teclado_val) == 1 ? "año" : "años") : 'N/D';

    // Mouse
    $anio_mouse_val = !empty($modelo_mouse) ? obtenerAnioPorModeloPeriferico($modelo_mouse, 'MOUSE') : null;
    $anio_mouse_txt = $anio_mouse_val ? $anio_mouse_val : 'N/D';
    $ant_mouse_txt  = $anio_mouse_val ? ($anio_actual - $anio_mouse_val) . " " . (($anio_actual - $anio_mouse_val) == 1 ? "año" : "años") : 'N/D';

    // Monitor
    $anio_monitor_val = !empty($modelo_monitor) ? obtenerAnioPorModeloPeriferico($modelo_monitor, 'MONITOR') : null;
    $anio_monitor_txt = $anio_monitor_val ? $anio_monitor_val : 'N/D';
    $ant_monitor_txt  = $anio_monitor_val ? ($anio_actual - $anio_monitor_val) . " " . (($anio_actual - $anio_monitor_val) == 1 ? "año" : "años") : 'N/D';


    // --- EVALUACIÓN TIER 1: EQUIPOS DE CÓMPUTO ---
    if ($tipo === 'PC' || $tipo === 'LAPTOP' || $tipo === 'SERVIDOR') {
        
        $anio_fabricacion = obtenerAnioProcesador($row['procesador']);
        $texto_cpu = strtoupper($row['procesador']);
        $vida_util_maxima = 6; 
        
        if ($anio_fabricacion !== null) {
            $antiguedad_anios = $anio_actual - $anio_fabricacion;
            $anio_fab_texto = $anio_fabricacion;
            $antiguedad_texto = $antiguedad_anios . " " . ($antiguedad_anios == 1 ? "año" : "años");
            
            if ($row['estado'] == 'Baja' || $row['estado'] == 'Inactivo') {
                $estatus_vida = "Fuera de servicio";
            } elseif ($antiguedad_anios >= $vida_util_maxima) {
                $estatus_vida = "Obsoleto (Requiere Renovación)";
            } else {
                $restante = $vida_util_maxima - $antiguedad_anios;
                $estatus_vida = "Vigente (" . $restante . " " . ($restante == 1 ? "año" : "años") . " restante)";
            }

            if (
                strpos($texto_cpu, 'CELERON') !== false || 
                strpos($texto_cpu, 'PENTIUM') !== false || 
                strpos($texto_cpu, 'ATHLON') !== false || 
                strpos($texto_cpu, 'CORE 2 DUO') !== false ||
                $antiguedad_anios > 7
            ) {
                if (strpos($texto_cpu, 'J4025') !== false || strpos($texto_cpu, 'N305') !== false) {
                    $apto_oficina = "Óptimo (Básico)";
                } else {
                    $apto_oficina = "No óptimo (Rendimiento bajo)";
                }
            } else {
                $apto_oficina = "Óptimo";
            }
        } else {
            $anio_fab_texto = "No identificado";
            $antiguedad_texto = "N/D";
            $estatus_vida = "Revisar texto de CPU";
            $apto_oficina = "N/D (Revisar)";
        }

    } else {
        // --- EVALUACIÓN TIER 2: PERIFÉRICOS AISLADOS ---
        $texto_periferico = isset($row['modelo']) ? $row['modelo'] : '';

        if ($tipo === 'IMPRESORA') {
            $apto_oficina = evaluarImpresoraPorArea($texto_periferico, $row['area']);
        } else {
            $apto_oficina = "No aplica"; 
        }
        
        $anio_fabricacion = obtenerAnioPorModeloPeriferico($texto_periferico, $tipo);
        $anio_fab_texto = $anio_fabricacion;
        
        $antiguedad_anios = $anio_actual - $anio_fabricacion;
        $antiguedad_texto = $antiguedad_anios . " " . ($antiguedad_anios == 1 ? "año" : "años");

        switch ($tipo) {
            case 'IMPRESORA':
            case 'ROUTER':
                $vida_util_maxima = 5;
                break;
            case 'MONITOR':
                $vida_util_maxima = 6;
                break;
            case 'MOUSE':
            case 'TECLADO':
                $vida_util_maxima = 3;
                break;
            default:
                $vida_util_maxima = 5;
                break;
        }

        if ($row['estado'] == 'Baja' || $row['estado'] == 'Inactivo') {
            $estatus_vida = "Fuera de servicio";
        } elseif ($antiguedad_anios >= $vida_util_maxima) {
            $estatus_vida = "Obsoleto (Requiere reemplazo)";
        } else {
            $restante = $vida_util_maxima - $antiguedad_anios;
            $estatus_vida = "Vigente (" . $restante . " " . ($restante == 1 ? "año" : "años") . " restante)";
        }
    }

    // Escritura unificada de renglón incluyendo las nuevas columnas en orden exacto al $header
    $writer->writeSheetRow('Inventario', array(
        $row['id'],
        $row['numero_resguardo'],
        $row['tipo_dispositivo'],
        $row['tipo_equipo'],
        $row['marca'],
        $row['modelo'],
        $row['no_serie'],
        $row['procesador'],
        $row['ram'],
        $row['disco_duro'],
        // Columnas de periféricos y sus cálculos
        $marca_teclado,
        $modelo_teclado,
        $anio_teclado_txt,
        $ant_teclado_txt,
        $marca_mouse,
        $modelo_mouse,
        $anio_mouse_txt,
        $ant_mouse_txt,
        $marca_monitor,
        $modelo_monitor,
        $anio_monitor_txt,
        $ant_monitor_txt,
        // Resto de columnas generales
        $row['area'],
        $row['nombre_completo'],
        $row['estado'],
        $row['fecha_alta'],
        $row['motivo_baja'],
        $anio_fab_texto,     
        $antiguedad_texto,   
        $estatus_vida,
        $apto_oficina        
    ));
}

/* ================= HEADERS PARA DESCARGA ================= */
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="inventario_'.date('Y-m-d').'.xlsx"');
header('Cache-Control: max-age=0');

$writer->writeToStdOut();
exit;