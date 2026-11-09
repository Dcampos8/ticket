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
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__entregas__imprimir_acuse.css?v=<?= filemtime(__DIR__ . '/../../../css/pages/admin__usuarios__entregas__imprimir_acuse.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../../css/brand.css') ?>">
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