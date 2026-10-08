<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('usuarios');
?><?php
require_once(__DIR__ . '/../../../config.php');

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: ID de baja no especificado.");
}

$id = intval($_GET['id']);
$stmt = mysqli_prepare($conexion, "SELECT * FROM checklist_entregas WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($resultado);

if (!$row) die("Error: El registro de baja solicitado no existe.");

// Mapeo de variables
$nombre       = $row['colaborador_nom'] ?? 'N/A';
$num_nomina   = $row['num_nomina'] ?? 'N/A';
$puesto       = $row['puesto'] ?? 'N/A';
$area         = $row['area'] ?? 'N/A';
$fecha_evento = !empty($row['fecha_evento']) ? date('d/m/Y', strtotime($row['fecha_evento'])) : 'N/A';
$fecha_baja   = !empty($row['fecha_baja']) ? date('d/m/Y', strtotime($row['fecha_baja'])) : date('d/m/Y');
$obs_baja     = !empty($row['obs_baja']) ? $row['obs_baja'] : 'Sin observaciones registradas durante la devolución.';

// Reconstrucción de periféricos que fueron devueltos
$pc_info = [];
if(($row['pc_escritorio_ok'] ?? 0) == 1) $pc_info[] = "PC Escritorio";
if(($row['monitor_ok'] ?? 0) == 1)       $pc_info[] = "Monitor";
if(($row['teclado_mouse_ok'] ?? 0) == 1) $pc_info[] = "Teclado / Mouse";
$equipos_devueltos = !empty($pc_info) ? implode(' • ', $pc_info) : 'Sin hardware/periféricos registrados';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de Devolución - <?= htmlspecialchars($nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__entregas__imprimir_baja.css?v=<?= filemtime(__DIR__ . '/../../../css/pages/admin__usuarios__entregas__imprimir_baja.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../../css/brand.css') ?>">
</head>
<body>

    <!-- Toolbar Flotante -->
    <div class="toolbar no-print">
        <div class="info">
            <span>Formato:</span>
            <span class="badge-mode">Acta de Baja / Devolución</span>
        </div>
        <div class="toolbar-btns">
            <button onclick="window.print()" class="btn-tb">🖨️ Imprimir Acta</button>
        </div>
    </div>

    <!-- Hoja Carta Completa -->
    <div class="page-container">
        <div>
            <!-- Encabezado Institucional -->
            <div class="header">
                <div class="brand">
                    <div class="brand-title">TRANSPORTES VALADEZ</div>
                    <div class="brand-subtitle">Departamento de Tecnologías de la Información</div>
                </div>
                <div class="doc-tag">
                    <div class="type">Constancia de Devolución</div>
                    <div class="date">Fecha Baja: <?= htmlspecialchars($fecha_baja) ?></div>
                </div>
            </div>

            <!-- Ficha del Colaborador -->
            <div class="section-title">Datos del Colaborador</div>
            <div class="emp-card">
                <div class="emp-field">
                    <span class="lbl">Nombre del Colaborador</span>
                    <span class="val"><?= htmlspecialchars($nombre) ?></span>
                </div>
                <div class="emp-field">
                    <span class="lbl">N° Nómina</span>
                    <span class="val"><?= htmlspecialchars($num_nomina) ?></span>
                </div>
                <div class="emp-field">
                    <span class="lbl">Área / Depto</span>
                    <span class="val"><?= htmlspecialchars($area) ?></span>
                </div>
                <div class="emp-field">
                    <span class="lbl">Puesto</span>
                    <span class="val"><?= htmlspecialchars($puesto) ?></span>
                </div>
            </div>

            <!-- Tiempos de Asignación -->
            <div class="section-title">Periodo de Resguardo</div>
            <div class="dates-summary">
                <div class="date-box">
                    <span class="lbl">Fecha Original de Entrega (Alta)</span>
                    <div class="val"><?= htmlspecialchars($fecha_evento) ?></div>
                </div>
                <div class="date-box">
                    <span class="lbl">Fecha de Recepción / Devolución (Baja)</span>
                    <div class="val" style="color: var(--primary-accent);"><?= htmlspecialchars($fecha_baja) ?></div>
                </div>
            </div>

            <!-- Hardware y Periféricos Devueltos -->
            <div class="section-title">Equipo Físico Recibido</div>
            <div class="hardware-box">
                <span class="lbl">Hardware / Periféricos Retornados</span>
                <div class="val"><?= htmlspecialchars($equipos_devueltos) ?></div>
            </div>

            <!-- Observaciones / Diagnóstico de Recepción -->
            <div class="section-title">Estado Físico y Observaciones de Entrega</div>
            <div class="obs-container">
                <span class="lbl">Detalles de Evaluación Técnica / Diagnóstico</span>
                <div class="val"><?= nl2br(htmlspecialchars($obs_baja)) ?></div>
            </div>

            <!-- Cláusula Legal de Recepción -->
            <div class="legal-notice">
                <strong>ACUERDO DE RECEPCIÓN:</strong> Por medio de la presente, el Departamento de TI hace constar la recepción formal de los equipos y activos descritos en este documento. A partir de esta fecha, se procede con la inhabilitación de las cuentas institucionales, correos y credenciales de acceso asignadas al colaborador. El colaborador queda liberado de la responsabilidad sobre el resguardo del hardware recibido a conformidad por Sistemas.
            </div>
        </div>

        <!-- Sección Inferior: Firmas -->
        <div class="firmas-wrapper">
            <div class="firmas-grid">
                <div class="firma-box">
                    <div class="firma-line"></div>
                    <strong><?= htmlspecialchars($nombre) ?></strong>
                    <span>Firma Colaborador / Entregó Activos</span>
                </div>

                <div class="firma-box">
                    <div class="firma-line"></div>
                    <strong>Bernardo Daniel Campos Dávalos</strong>
                    <span>Sistemas / Recibió TI</span>
                </div>
            </div>

            <div class="footer-note">
                Transportes Valadez &copy; <?= date('Y') ?> &bull; Sistema de Control de Activos y Accesos TI
            </div>
        </div>
    </div>

</body>
</html>