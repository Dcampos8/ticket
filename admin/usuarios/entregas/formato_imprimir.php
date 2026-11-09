<?php
require_once __DIR__ . '/../../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('usuarios');
function val($campo){
    return isset($_GET[$campo]) ? htmlspecialchars($_GET[$campo]) : '';
}

// Datos del Colaborador
$nombre      = val('nombre');
$num_nomina  = val('num_nomina');
$puesto      = val('puesto');
$area        = val('area');
$fecha       = val('fecha') ?: date('d/m/Y');

// Sistema Interno (SIIAD)
$siiad_user  = val('sistema_user');
$siiad_pass  = val('sistema_pass');

// CONTPAQi
$nominas_user = val('nominas_user');
$conta_user   = val('conta_user');

// Hardware / Telefonía
$telefono     = val('telefono');

// Credenciales PC
$pc_user      = val('pc_user');
$pc_pass      = val('pc_pass');

// Accesos Varios
$correo       = val('correo');
$checador_det = val('checador_detalles');
$nip_almacen  = val('nip_almacen');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Entrega de Credenciales - Transportes Valadez</title>

<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__usuarios__entregas__formato_imprimir.css?v=<?= filemtime(__DIR__ . '/../../../css/pages/admin__usuarios__entregas__formato_imprimir.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../../css/brand.css') ?>">
</head>
<body>

<div class="botones">
    <button class="btn btn-entrega" onclick="imprimirEntrega()">
        Imprimir entrega al usuario
    </button>

    <button class="btn btn-firma" onclick="imprimirFirma()">
        Imprimir acuse para firma
    </button>
</div>

<div class="container">

    <h1>Entrega de Credenciales</h1>

    <div class="subtitulo">
        Transportes Valadez<br>
        Fecha: <?= htmlspecialchars($fecha) ?>
    </div>

    <!-- 1. DATOS DEL COLABORADOR -->
    <div class="section">
        <div class="section-title">Datos del Colaborador</div>
        <table>
            <tr>
                <td class="label">Nombre completo</td>
                <td><?= $nombre ?></td>
            </tr>
            <?php if (!empty($num_nomina)): ?>
            <tr>
                <td class="label">N° Nómina</td>
                <td><?= $num_nomina ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($puesto)): ?>
            <tr>
                <td class="label">Puesto</td>
                <td><?= $puesto ?></td>
            </tr>
            <?php endif; ?>
            <?php if (!empty($area)): ?>
            <tr>
                <td class="label">Área</td>
                <td><?= $area ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <!-- 2. ACCESOS Y CREDENCIALES -->
    <div class="section">
        <div class="section-title">Accesos Entregados</div>

        <table>
            <!-- Credenciales de PC / Windows -->
            <?php if (!empty($pc_user)): ?>
            <tr>
                <td class="label">Usuario Windows / PC</td>
                <td><?= $pc_user ?></td>
            </tr>
            <tr class="ocultar-en-firma">
                <td class="label">Contraseña Windows / PC</td>
                <td><?= $pc_pass ?></td>
            </tr>
            <?php endif; ?>

            <!-- Sistema Interno (SIIAD) -->
            <?php if (!empty($siiad_user)): ?>
            <tr>
                <td class="label">Usuario Sistema Interno (SIIAD)</td>
                <td><?= $siiad_user ?></td>
            </tr>
            <tr class="ocultar-en-firma">
                <td class="label">Contraseña Sistema Interno</td>
                <td><?= $siiad_pass ?></td>
            </tr>
            <?php endif; ?>

            <!-- Correo Institucional -->
            <?php if (!empty($correo)): ?>
            <tr>
                <td class="label">Correo Institucional</td>
                <td><?= $correo ?></td>
            </tr>
            <?php endif; ?>

            <!-- CONTPAQi Nóminas -->
            <?php if (!empty($nominas_user)): ?>
            <tr>
                <td class="label">CONTPAQi Nóminas</td>
                <td><?= $nominas_user ?></td>
            </tr>
            <?php endif; ?>

            <!-- CONTPAQi Contabilidad -->
            <?php if (!empty($conta_user)): ?>
            <tr>
                <td class="label">CONTPAQi Contabilidad</td>
                <td><?= $conta_user ?></td>
            </tr>
            <?php endif; ?>

            <!-- Código / ID Checador -->
            <?php if (!empty($checador_det)): ?>
            <tr>
                <td class="label">Código / Usuario Checador</td>
                <td><?= $checador_det ?></td>
            </tr>
            <?php endif; ?>

            <!-- NIP Almacén -->
            <?php if (!empty($nip_almacen)): ?>
            <tr>
                <td class="label">NIP Almacén</td>
                <td><?= $nip_almacen ?></td>
            </tr>
            <?php endif; ?>

            <!-- Teléfono / Extensión -->
            <?php if (!empty($telefono)): ?>
            <tr>
                <td class="label">Teléfono / Extensión</td>
                <td><?= $telefono ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="texto">
        Por medio del presente se hace constar que el colaborador recibe las credenciales y accesos necesarios para el desempeño de sus funciones dentro de la empresa.

        <br><br>

        El colaborador se compromete a resguardar adecuadamente la información proporcionada y hacer uso responsable de los accesos asignados.
    </div>

    <div class="firmas" id="firmas">
        <div class="firma">
            <div class="linea"></div>
            Entrega Sistemas
        </div>

        <div class="firma">
            <div class="linea"></div>
            Recibe Colaborador
        </div>
    </div>

</div>

<script>
function imprimirEntrega(){

    document.querySelectorAll('.ocultar-en-firma').forEach(el=>{
        el.style.display='table-row';
    });

    document.getElementById('firmas').style.display='none';

    document.title='Entrega_Credenciales';

    window.print();
}

function imprimirFirma(){

    document.querySelectorAll('.ocultar-en-firma').forEach(el=>{
        el.style.display='none';
    });

    document.getElementById('firmas').style.display='flex';

    document.title='Acuse_Firma';

    window.print();

    setTimeout(()=>{
        document.querySelectorAll('.ocultar-en-firma').forEach(el=>{
            el.style.display='table-row';
        });

        document.getElementById('firmas').style.display='none';
    },500);
}
</script>

</body>
</html>
