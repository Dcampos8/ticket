<?php
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('lineas');

$idHistorial = filter_input(INPUT_GET, 'historial_id', FILTER_VALIDATE_INT) ?: 0;
$data = null;
$fechaResponsiva = '';
$notasHistorial = '';
if ($idHistorial > 0) {
    $stmtHistorial = $conexion->prepare('SELECT * FROM historial_lineas WHERE id = ? LIMIT 1');
    $stmtHistorial->bind_param('i', $idHistorial);
    $stmtHistorial->execute();
    $historial = $stmtHistorial->get_result()->fetch_assoc();
    $stmtHistorial->close();
    if ($historial) {
        $linea = null;
        $idLineaHist = (int) ($historial['id_linea'] ?? 0);
        if ($idLineaHist > 0) {
            $stmtLinea = $conexion->prepare('SELECT * FROM lineas_telefonicas WHERE id = ? LIMIT 1');
            $stmtLinea->bind_param('i', $idLineaHist);
            $stmtLinea->execute();
            $linea = $stmtLinea->get_result()->fetch_assoc();
            $stmtLinea->close();
        }
        // Para movimientos de stock, id_linea puede ser 0; buscar el equipo
        // vigente por el IMEI guardado en el registro histórico.
        $imeiHist = trim((string) ($historial['imei'] ?? ''));
        if (!$linea && $imeiHist !== '') {
            $stmtLinea = $conexion->prepare('SELECT * FROM lineas_telefonicas WHERE imei_entregado = ? ORDER BY id DESC LIMIT 1');
            $stmtLinea->bind_param('s', $imeiHist);
            $stmtLinea->execute();
            $linea = $stmtLinea->get_result()->fetch_assoc();
            $stmtLinea->close();
        }
        $data = $linea ?: [];
        $nombreHistorial = trim((string) ($historial['usuario_nuevo'] ?? ''));
        if ($nombreHistorial === '' || strtoupper($nombreHistorial) === 'N/A') {
            $nombreHistorial = trim((string) ($historial['usuario_anterior'] ?? ''));
        }
        if (strtoupper($nombreHistorial) === 'N/A') $nombreHistorial = '';
        $puestoHistorial = trim((string) ($historial['puesto_nuevo'] ?? ''));
        if ($puestoHistorial === '' || strtoupper($puestoHistorial) === 'N/A') {
            $puestoHistorial = trim((string) ($historial['puesto_anterior'] ?? ''));
        }
        if (strtoupper($puestoHistorial) === 'N/A') $puestoHistorial = '';
        $data['usuario'] = $nombreHistorial;
        $data['puesto'] = $puestoHistorial;
        $data['numero_telefono'] = $historial['numero_telefono'] ?: ($data['numero_telefono'] ?? '');
        $data['imei_entregado'] = $imeiHist ?: ($data['imei_entregado'] ?? '');
        $fechaResponsiva = (string) ($historial['fecha_cambio'] ?? '');
        $notasHistorial = (string) ($historial['notas'] ?? '');
    }
} else {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
    $stmtLinea = $conexion->prepare('SELECT * FROM lineas_telefonicas WHERE id = ? LIMIT 1');
    $stmtLinea->bind_param('i', $id);
    $stmtLinea->execute();
    $data = $stmtLinea->get_result()->fetch_assoc();
    $stmtLinea->close();
    $fechaResponsiva = (string) ($data['fecha_entrega'] ?? '');
}

if (!$data) {
    http_response_code(404);
    exit("No se encontró el registro del historial o de la línea.");
}
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$fechaTexto = '________________';
if ($fechaResponsiva !== '') {
    $fechaObj = new DateTimeImmutable($fechaResponsiva);
    $mesesFecha = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $fechaTexto = $fechaObj->format('d') . ' de ' . $mesesFecha[(int) $fechaObj->format('n') - 1] . ' de ' . $fechaObj->format('Y');
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Responsiva - <?= $e($data['usuario'] ?? '') ?></title>
    <link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__lineas__responsiva.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__lineas__responsiva.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
</head>
<body>

<div class="no-print document-picker">
    <button type="button" id="btnResponsivaTelefono" onclick="seleccionarResponsiva('telefono')">📱 Responsiva de teléfono</button>
    <button type="button" id="btnResponsivaSim" onclick="seleccionarResponsiva('sim')">💳 Responsiva de SIM</button>
    <button type="button" onclick="window.print();">🖨️ Imprimir seleccionada</button>
</div>

<!-- HOJA 1: EQUIPO CELULAR -->
<div class="page responsiva-page" id="responsivaTelefono">
  <img class="page-bg" src="<?= $e(BASE_URL . 'admin/lineas/img/fondo.png') ?>" alt="">
  <div class="page-content">
    <h2>RESPONSIVA DE EQUIPO CELULAR CORPORATIVO</h2>
    <p class="fecha-responsiva">Aguascalientes, Ags., a <?= $e($fechaTexto) ?></p>
    <p>Yo, <strong><?= $e($data['usuario'] ?? '') ?></strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> el siguiente equipo para uso laboral:</p>
    
    <div class="info-box">
        <strong>Información del Equipo:</strong><br>
        <strong>Número asignado:</strong> <?= $e($data['numero_telefono'] ?? '') ?><br>
        <strong>Correo corporativo asignado:</strong> <?= $e($data['cuenta_google'] ?? '') ?><br>
        <strong>Marca / Modelo:</strong> <?= $e(trim(($data['equipo'] ?? '') . ' / ' . ($data['modelo'] ?? ''))) ?><br>
        <strong>IMEI:</strong> <?= $e($data['imei_entregado'] ?? '') ?><br>
        <strong>Puesto:</strong> <?= $e($data['puesto'] ?? '') ?><br>
        <strong>No. de resguardo:</strong> <?= $e($data['resguardo'] ?? '') ?><br>
        <strong>Comentarios de entrega:</strong> <?= $e($data['comentarios_entrega'] ?? '') ?><br>
        <strong>Detalles:</strong> <?= $e($data['comentarios_extras'] ?? '') ?>
        <?php if ($notasHistorial !== ''): ?><br><strong>Nota del movimiento:</strong> <?= $e($notasHistorial) ?><?php endif; ?>
    </div>

    <h4>CONDICIONES DE USO</h4>
    <p>El equipo y la línea asignada son exclusivamente para actividades laborales.</p>
    <p>Queda prohibido:</p>
    <ul>
        <li>Registrar cuentas personales (Google, WhatsApp personal, bancos, redes sociales, etc.)</li>
        <li>Utilizar la línea o el equipo para trámites o fines personales.</li>
        <li>Instalar aplicaciones no autorizadas o modificar configuraciones.</li>
    </ul>

    <h4>RESPONSABILIDAD</h4>
    <p>Me comprometo a:</p>
    <ul>
        <li>Cuidar el equipo y mantenerlo funcional.</li>
        <li>Reportar fallas, daño o extravío de inmediato.</li>
    </ul>
    <p>En caso de daño por mal uso, pérdida, robo negligente o falta de devolución cuando sea solicitado, me obligo a cubrir el valor de reposición de $3500 MXN o el monto actualizado determinado por la empresa. Adicionalmente, de causarse daños al equipo por mal uso, seré responsable de recobrar la comunicación (ya sea mediante los costos y gestiones para su reparación) o bien optar por el descuento total del equipo.</p>
    <p>Asimismo, el formateo, borrado de información o restablecimiento del equipo sin autorización previa, ya sea al momento de la entrega o devolución del mismo, generará un cobro o sanción independiente, conforme al monto definido por la empresa, el cual no sustituye ni exime del pago por daño, pérdida o no devolución del equipo.</p>
    <p>El equipo deberá ser devuelto cuando la empresa así lo solicite, en las condiciones establecidas.</p>

    <div class="firmas">
        <div class="linea-firma"></div>
        <strong><?= $e($data['usuario'] ?? '') ?></strong>
    </div>
  </div>
</div>

<!-- HOJA 2: SIM CORPORATIVA -->
<div class="page responsiva-page" id="responsivaSim" hidden>
  <img class="page-bg" src="<?= $e(BASE_URL . 'admin/lineas/img/fondo.png') ?>" alt="">
  <div class="page-content">
    <h2>RESPONSIVA DE SIM CORPORATIVA</h2>
    <p class="fecha-responsiva">Aguascalientes, Ags., a <?= $e($fechaTexto) ?></p>
    <p>Yo, <strong><?= $e($data['usuario'] ?? '') ?></strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> una línea telefónica corporativa (SIM) para uso exclusivo en actividades laborales.</p>
    
    <div class="info-box">
        <strong>Información de la línea asignada</strong><br>
        <strong>Número telefónico:</strong> <?= $e($data['numero_telefono'] ?? '') ?><br>
        <strong>Correo corporativo asignado:</strong> <?= $e($data['cuenta_google'] ?? '') ?><br>
        <strong>Compañía telefónica:</strong> TELCEL<br>
        <strong>Equipo entregado:</strong> <?= $e(trim(($data['equipo'] ?? '') . ' ' . ($data['modelo'] ?? ''))) ?><br>
        <strong>IMEI:</strong> <?= $e($data['imei_entregado'] ?? '') ?><br>
        <strong>Puesto:</strong> <?= $e($data['puesto'] ?? '') ?><br>
        <strong>No. de resguardo:</strong> <?= $e($data['resguardo'] ?? '') ?><br>
        <strong>Comentarios de entrega:</strong> <?= $e($data['comentarios_entrega'] ?? '') ?><br>
        <strong>Detalles:</strong> <?= $e($data['comentarios_extras'] ?? '') ?>
        <?php if ($notasHistorial !== ''): ?><br><strong>Nota del movimiento:</strong> <?= $e($notasHistorial) ?><?php endif; ?>
    </div>

    <h4>CONDICIONES DE USO</h4>
    <p>La línea telefónica corporativa es propiedad de Transportes Valadez S. de R.L. de C.V. y se asigna exclusivamente para fines laborales.</p>
    <p>Queda estrictamente prohibido:</p>
    <ul>
        <li>Utilizar la línea para fines personales.</li>
        <li>Registrar cuentas personales (WhatsApp personal, bancos, redes sociales, etc.) asociadas al número corporativo.</li>
        <li>Utilizar la línea para realizar trámites personales o ajenos a las funciones laborales.</li>
        <li>Compartir la línea o permitir su uso a terceros sin autorización de la empresa.</li>
    </ul>

    <h4>RESPONSABILIDAD</h4>
    <p>Me comprometo a:</p>
    <ul>
        <li>Utilizar la línea telefónica únicamente para actividades relacionadas con mi trabajo.</li>
        <li>Reportar de inmediato cualquier falla, pérdida, robo o mal funcionamiento de la tarjeta SIM.</li>
        <li>Cumplir con las políticas internas de uso de dispositivos y líneas corporativas establecidas por la empresa.</li>
    </ul>
    <p>En caso de pérdida, daño o uso indebido de la línea o equipo asociado, me comprometo a cubrir los costos de reposición (tarjeta SIM $3500 o valor correspondiente) y/o cualquier cargo adicional. Si el daño al equipo deriva de una mala práctica o descuido, seré responsable de recobrar la comunicación (mediante su envío a reparación) o bien optar por cubrir el descuento total del equipo, conforme a lo determinado por la empresa. La línea telefónica deberá ser devuelta cuando Transportes Valadez S. de R.L. de C.V. así lo solicite o al término de la relación laboral.</p>

    <div class="firmas">
        <div class="linea-firma"></div>
        <strong><?= $e($data['usuario'] ?? '') ?></strong>
    </div>
  </div>
</div>

<script>
function seleccionarResponsiva(tipo) {
    const telefono = document.getElementById('responsivaTelefono');
    const sim = document.getElementById('responsivaSim');
    const verTelefono = tipo === 'telefono';
    telefono.hidden = !verTelefono;
    sim.hidden = verTelefono;
    document.getElementById('btnResponsivaTelefono').classList.toggle('active', verTelefono);
    document.getElementById('btnResponsivaSim').classList.toggle('active', !verTelefono);
}
seleccionarResponsiva('telefono');
</script>

</body>
</html>
