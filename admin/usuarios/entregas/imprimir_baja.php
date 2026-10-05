<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/config.php');

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
    <style>
        :root {
            --primary: #0f172a;
            --primary-accent: #d97706; /* Tono Ámbar/Warning para la Baja */
            --secondary: #475569;
            --bg-light: #f8fafc;
            --border-color: #e2e8f0;
            --text-dark: #1e293b;
            --text-muted: #64748b;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background-color: #cbd5e1; color: var(--text-dark); -webkit-print-color-adjust: exact; }

        /* Barra de herramientas flotante */
        .toolbar {
            position: fixed; top: 0; left: 0; right: 0; height: 56px;
            background-color: var(--primary); color: #fff;
            display: flex; justify-content: space-between; align-items: center;
            padding: 0 24px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.2); z-index: 1000;
        }
        .toolbar .info { font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 8px; }
        .badge-mode { background: var(--primary-accent); padding: 4px 10px; border-radius: 20px; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; color: #fff; }
        .toolbar-btns { display: flex; gap: 10px; }
        .btn-tb {
            background-color: var(--primary-accent); color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; cursor: pointer; text-decoration: none;
            font-size: 12px; font-weight: 600; transition: background 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-tb:hover { background-color: #b45309; }

        /* Contenedor Hoja Carta Completa */
        .page-container {
            width: 215.9mm;
            min-height: 279.4mm;
            margin: 72px auto 30px auto;
            background: #fff;
            padding: 18mm 16mm;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Encabezado */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            border-bottom: 3px solid var(--primary);
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .brand { display: flex; flex-direction: column; }
        .brand-title { font-size: 22px; font-weight: 800; color: var(--primary); letter-spacing: -0.5px; line-height: 1; }
        .brand-subtitle { font-size: 11px; font-weight: 600; color: var(--primary-accent); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px; }
        .doc-tag { text-align: right; }
        .doc-tag .type { font-size: 12px; font-weight: 700; color: #78350f; text-transform: uppercase; background: #fef3c7; border: 1px solid #fde68a; padding: 4px 10px; border-radius: 4px; display: inline-block; }
        .doc-tag .date { font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

        /* Titulos de sección */
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--secondary); letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }

        /* Ficha del Colaborador */
        .emp-card {
            background-color: var(--bg-light);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 14px 16px;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 12px;
            margin-bottom: 22px;
        }
        .emp-field { display: flex; flex-direction: column; }
        .emp-field .lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px; }
        .emp-field .val { font-size: 12px; font-weight: 600; color: var(--text-dark); }

        /* Grid de Fechas */
        .dates-summary {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-bottom: 22px;
        }
        .date-box {
            border: 1px solid var(--border-color);
            background: var(--bg-light);
            border-radius: 6px;
            padding: 12px;
        }
        .date-box .lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--text-muted); margin-bottom: 2px; }
        .date-box .val { font-size: 13px; font-weight: 700; color: var(--primary); }

        /* Hardware y Periféricos */
        .hardware-box {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 22px;
        }
        .hardware-box .lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--secondary); margin-bottom: 4px; display: block; }
        .hardware-box .val { font-size: 12px; font-weight: 600; color: var(--primary); }

        /* Observaciones / Estado del equipo */
        .obs-container {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #fff;
            padding: 16px;
            margin-bottom: 22px;
            min-height: 120px;
        }
        .obs-container .lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: var(--primary-accent); margin-bottom: 8px; display: block; }
        .obs-container .val { font-size: 12px; line-height: 1.5; color: var(--text-dark); white-space: pre-line; }

        /* Declaración Legal de Entrega */
        .legal-notice {
            background-color: var(--bg-light);
            border-left: 4px solid var(--primary-accent);
            border-radius: 0 6px 6px 0;
            padding: 10px 12px;
            font-size: 9.5px;
            line-height: 1.45;
            color: var(--secondary);
            text-align: justify;
            margin-bottom: 24px;
        }

        /* Firmas */
        .firmas-wrapper {
            margin-top: auto;
            padding-top: 20px;
        }
        .firmas-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            padding: 0 20px;
        }
        .firma-box { text-align: center; }
        .firma-line { border-top: 1.5px solid var(--primary); margin-bottom: 8px; }
        .firma-box strong { font-size: 11px; color: var(--primary); display: block; }
        .firma-box span { font-size: 10px; color: var(--text-muted); font-weight: 500; }

        /* Pie de página */
        .footer-note {
            text-align: center; font-size: 8.5px; color: var(--text-muted);
            border-top: 1px dashed var(--border-color); padding-top: 8px; margin-top: 15px;
        }

        /* Ajustes para Impresión */
        @media print {
            .no-print, .toolbar { display: none !important; }
            body { background: #fff; }
            .page-container {
                width: 100% !important;
                height: 100vh !important;
                margin: 0 !important;
                padding: 12mm 10mm !important;
                box-shadow: none !important;
                border: none !important;
            }
            .emp-card, .date-box, .obs-container, .legal-notice { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
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