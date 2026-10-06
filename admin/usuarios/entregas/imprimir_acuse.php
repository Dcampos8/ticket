<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('usuarios');
?><?php
require_once(__DIR__ . '/../../../config.php');

// Validamos que venga el ID por la URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: ID de entrega no especificado.");
}

$id_registro = intval($_GET['id']);

// Modo de impresión: 'admin' (solo usuarios + firmas) o 'usuario' (usuarios + passwords, sin firmas)
$modo_vista = isset($_GET['tipo']) && $_GET['tipo'] === 'admin' ? 'admin' : 'usuario';

// Consultamos los datos reales del registro
$stmt = mysqli_prepare($conexion, "SELECT * FROM checklist_entregas WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $id_registro);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($resultado) == 0) {
    die("Error: El registro solicitado no existe.");
}

$datos = mysqli_fetch_assoc($resultado);

// MAPEO DE COLUMNAS DE LA TABLA
$nombre        = $datos['colaborador_nom'] ?? 'N/A';
$num_nomina    = $datos['num_nomina'] ?? 'N/A';
$puesto        = $datos['puesto'] ?? 'N/A';
$area          = $datos['area'] ?? 'N/A';
$fecha_evento  = $datos['fecha_evento'] ? date('d/m/Y', strtotime($datos['fecha_evento'])) : date('d/m/Y');

/**
 * Función formateadora de accesos
 */
function formatoAcceso($usuario, $password, $modo) {
    $usr_clean = trim($usuario ?? '');
    $pwd_clean = trim($password ?? '');
    
    if (empty($usr_clean) && empty($pwd_clean)) return '';
    
    if ($modo === 'admin') {
        if (!empty($usr_clean)) {
            return htmlspecialchars($usr_clean);
        }
        return htmlspecialchars($pwd_clean);
    } else {
        $partes = [];
        if (!empty($usr_clean)) {
            $partes[] = '<span class="acc-label">User:</span> ' . htmlspecialchars($usr_clean);
        }
        if (!empty($pwd_clean)) {
            $partes[] = '<span class="acc-label">Pass:</span> ' . htmlspecialchars($pwd_clean);
        }
        return implode(' &nbsp;|&nbsp; ', $partes);
    }
}

// Credenciales
$pc_credenciales  = formatoAcceso($datos['pc_usuario'] ?? '', $datos['pc_password'] ?? '', $modo_vista);
$correo_cred      = formatoAcceso($datos['correo_detalles'] ?? '', ($modo_vista === 'usuario' ? '' : ($datos['correo_password'] ?? '')), $modo_vista);
$siiad_cred       = formatoAcceso($datos['siiad_detalles'] ?? '', $datos['siiad_password'] ?? '', $modo_vista);
$contpaq_nom_cred = formatoAcceso($datos['contpaq_nom_detalles'] ?? '', $datos['contpaq_nom_password'] ?? '', $modo_vista);
$contpaq_con_cred = formatoAcceso($datos['contpaq_con_detalles'] ?? '', $datos['contpaq_con_password'] ?? '', $modo_vista);
$checador_cred    = formatoAcceso($datos['checador_detalles'] ?? '', $datos['checador_password'] ?? '', $modo_vista);

$nip_val = trim($datos['nip_almacen_pass'] ?? $datos['nip_almacen_detalles'] ?? '');
$nip_cred = (!empty($nip_val) && $modo_vista === 'usuario') ? htmlspecialchars($nip_val) : '';

$telefono_detalles = !empty($datos['telefono_detalles']) ? $datos['telefono_detalles'] : '';

// Agrupamos periféricos o detalles de PC
$pc_info = [];
if(($datos['pc_escritorio_ok'] ?? 0) == 1) $pc_info[] = "PC Escritorio";
if(($datos['monitor_ok'] ?? 0) == 1)       $pc_info[] = "Monitor";
if(($datos['teclado_mouse_ok'] ?? 0) == 1) $pc_info[] = "Teclado / Mouse";
$equipos_entregados = !empty($pc_info) ? implode(' • ', $pc_info) : 'Sin periféricos asignados';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acuse (<?= strtoupper($modo_vista) ?>) - <?= htmlspecialchars($nombre) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --primary-accent: #2563eb;
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
        .badge-mode { background: var(--primary-accent); padding: 4px 10px; border-radius: 20px; font-size: 11px; text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }
        .toolbar-btns { display: flex; gap: 10px; }
        .btn-tb {
            background-color: var(--primary-accent); color: #fff; border: none;
            padding: 8px 16px; border-radius: 6px; cursor: pointer; text-decoration: none;
            font-size: 12px; font-weight: 600; transition: background 0.2s; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-tb:hover { background-color: #1d4ed8; }
        .btn-tb.alt { background-color: var(--secondary); }
        .btn-tb.alt:hover { background-color: #334155; }

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
        .doc-tag .type { font-size: 12px; font-weight: 700; color: var(--secondary); text-transform: uppercase; background: var(--bg-light); border: 1px solid var(--border-color); padding: 4px 10px; border-radius: 4px; display: inline-block; }
        .doc-tag .date { font-size: 11px; color: var(--text-muted); margin-top: 4px; font-weight: 500; }

        /* Grid de Datos del Colaborador */
        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--secondary); letter-spacing: 0.8px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border-color); }

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

        /* Grid Principal de Accesos / Credenciales */
        .credentials-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-bottom: 22px;
        }
        .cred-card {
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 10px 12px;
            background: #fff;
            transition: all 0.2s;
        }
        .cred-card.full-width { grid-column: span 2; }
        .cred-card .lbl { font-size: 9px; font-weight: 700; text-transform: uppercase; color: var(--primary-accent); margin-bottom: 4px; display: block; }
        .cred-card .val { font-size: 11px; font-family: 'Courier New', Courier, monospace; color: var(--primary); font-weight: 600; word-break: break-word; }
        .cred-card .acc-label { color: var(--text-muted); font-family: 'Inter', sans-serif; font-size: 10px; font-weight: 600; }

        /* Sección Periféricos */
        .hardware-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 22px;
        }
        .hardware-box .lbl { font-size: 10px; font-weight: 700; text-transform: uppercase; color: #1e40af; margin-bottom: 4px; display: block; }
        .hardware-box .val { font-size: 12px; font-weight: 600; color: #1e3a8a; }

        /* Responsiva Legal */
        .legal-notice {
            background-color: var(--bg-light);
            border-left: 4px solid var(--primary);
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
            .cred-card, .emp-card, .legal-notice, .hardware-box { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <!-- Toolbar Flotante -->
    <div class="toolbar no-print">
        <div class="info">
            <span>Modo de Impresión:</span>
            <span class="badge-mode"><?= $modo_vista === 'admin' ? 'Expediente Admin' : 'Copia Usuario' ?></span>
        </div>
        <div class="toolbar-btns">
            <?php if ($modo_vista === 'admin'): ?>
                <a href="?id=<?= $id_registro ?>&tipo=usuario" class="btn-tb alt">Ver Vista Usuario</a>
            <?php else: ?>
                <a href="?id=<?= $id_registro ?>&tipo=admin" class="btn-tb alt">Ver Vista Admin</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-tb">🖨️ Imprimir Acuse</button>
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
                    <div class="type">Responsiva de Entregas TI</div>
                    <div class="date">Fecha: <?= htmlspecialchars($fecha_evento) ?></div>
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

            <!-- Credenciales y Accesos -->
            <div class="section-title">Cuentas y Accesos Asignados</div>
            <div class="credentials-grid">

                <?php if (!empty($pc_credenciales)): ?>
                <div class="cred-card">
                    <span class="lbl">Usuario PC / Sesión Windows</span>
                    <div class="val"><?= $pc_credenciales ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($correo_cred)): ?>
                <div class="cred-card <?= empty($pc_credenciales) ? 'full-width' : '' ?>">
                    <span class="lbl">Correo Electrónico Institucional</span>
                    <div class="val"><?= $correo_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($siiad_cred)): ?>
                <div class="cred-card">
                    <span class="lbl">Sistema Interno (SIIAD)</span>
                    <div class="val"><?= $siiad_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($contpaq_nom_cred)): ?>
                <div class="cred-card">
                    <span class="lbl">CONTPAQi Nóminas</span>
                    <div class="val"><?= $contpaq_nom_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($contpaq_con_cred)): ?>
                <div class="cred-card">
                    <span class="lbl">CONTPAQi Contabilidad</span>
                    <div class="val"><?= $contpaq_con_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($checador_cred)): ?>
                <div class="cred-card">
                    <span class="lbl">Reloj Checador</span>
                    <div class="val"><?= $checador_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($nip_cred)): ?>
                <div class="cred-card">
                    <span class="lbl">NIP de Acceso a Almacén</span>
                    <div class="val">NIP: <?= $nip_cred ?></div>
                </div>
                <?php endif; ?>

                <?php if (!empty($telefono_detalles)): ?>
                <div class="cred-card">
                    <span class="lbl">Extensión Telefónica</span>
                    <div class="val"><?= htmlspecialchars($telefono_detalles) ?></div>
                </div>
                <?php endif; ?>

            </div>

            <!-- Periféricos y Hardware -->
            <div class="section-title">Equipo Físico / Periféricos</div>
            <div class="hardware-box">
                <span class="lbl">Hardware Entregado</span>
                <div class="val"><?= htmlspecialchars($equipos_entregados) ?></div>
            </div>

            <!-- Cláusula Legal / Términos -->
            <div class="legal-notice">
                <strong>TÉRMINOS Y CONDICIONES DE USO:</strong> El colaborador declara recibir a entera satisfacción los accesos, credenciales y/o componentes de cómputo detallados en este documento. Se compromete a destinar los activos exclusivamente para el desempeño de sus funciones laborales dentro de Transportes Valadez, salvaguardando la confidencialidad de sus contraseñas. En caso de baja o reasignación de funciones, se compromete a devolver las herramientas físicas y se cancelarán los accesos en el sistema.
            </div>
        </div>

        <!-- Sección Inferior: Firmas (Solo visibles en versión Admin) -->
        <div class="firmas-wrapper">
            <?php if ($modo_vista === 'admin'): ?>
            <div class="firmas-grid">
                <div class="firma-box">
                    <div class="firma-line"></div>
                    <strong>Bernardo Daniel Campos Dávalos</strong>
                    <span>Sistemas / Departamento de TI</span>
                </div>

                <div class="firma-box">
                    <div class="firma-line"></div>
                    <strong><?= htmlspecialchars($nombre) ?></strong>
                    <span>Firma de Conformidad / Colaborador</span>
                </div>
            </div>
            <?php else: ?>
            <div style="text-align: center; color: var(--text-muted); font-size: 10px; padding: 10px; border: 1px dashed var(--border-color); border-radius: 6px;">
                * Documento informativo exclusivo para el usuario. Conserve este comprobante para sus credenciales.
            </div>
            <?php endif; ?>

            <div class="footer-note">
                Transportes Valadez &copy; <?= date('Y') ?> &bull; Sistema de Control de Activos y Accesos TI
            </div>
        </div>
    </div>

</body>
</html>