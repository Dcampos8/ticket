<?php
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('lineas');

$id = intval($_GET['id'] ?? 0);
$res = $conexion->query("SELECT * FROM lineas_telefonicas WHERE id = $id");
$data = $res->fetch_assoc();

if (!$data) {
    die("Registro no encontrado.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Responsiva - <?= htmlspecialchars($data['usuario']) ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10pt; line-height: 1.3; color: #000; margin: 0; padding: 20px; }
        .page { max-width: 800px; margin: 0 auto 30px auto; background: #fff; padding: 25px; border: 1px solid #ccc; page-break-after: always; }
        h2 { font-size: 11pt; text-align: center; text-transform: uppercase; margin-bottom: 15px; font-weight: bold; }
        h4 { font-size: 10pt; margin-top: 10px; margin-bottom: 5px; text-transform: uppercase; font-weight: bold; }
        p, li { text-align: justify; margin: 4px 0; }
        ul { padding-left: 20px; margin: 4px 0; }
        .info-box { margin: 10px 0; line-height: 1.5; }
        .firmas { margin-top: 40px; text-align: center; }
        .linea-firma { width: 300px; border-top: 1px solid #000; margin: 0 auto 5px auto; }
        
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
            .page { border: none; padding: 0; margin: 0; page-break-after: always; }
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 15px;">
    <button onclick="window.print();" style="padding: 8px 20px; font-weight: bold; cursor: pointer;">🖨️ Imprimir Responsiva</button>
</div>

<!-- HOJA 1: EQUIPO CELULAR -->
<div class="page">
    <h2>RESPONSIVA DE EQUIPO CELULAR CORPORATIVO</h2>
    <p>Yo, <strong><?= htmlspecialchars($data['usuario']) ?></strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> el siguiente equipo para uso laboral:</p>
    
    <div class="info-box">
        <strong>Información del Equipo:</strong><br>
        <strong>Número asignado:</strong> <?= htmlspecialchars($data['numero_telefono']) ?><br>
        <strong>Correo corporativo asignado:</strong> <?= htmlspecialchars($data['cuenta_google']) ?><br>
        <strong>Marca / Modelo:</strong> <?= htmlspecialchars($data['equipo'] . '/' . $data['modelo']) ?><br>
        <strong>IMEI:</strong> <?= htmlspecialchars($data['imei_entregado']) ?><br>
        <strong>Comentarios de entrega:</strong> <?= htmlspecialchars($data['comentarios_entrega']) ?><br>
        <strong>Detalles:</strong> <?= htmlspecialchars($data['comentarios_extras']) ?>
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
        <strong><?= htmlspecialchars($data['usuario']) ?></strong>
    </div>
</div>

<!-- HOJA 2: SIM CORPORATIVA -->
<div class="page">
    <h2>RESPONSIVA DE SIM CORPORATIVA</h2>
    <p>Yo, <strong><?= htmlspecialchars($data['usuario']) ?></strong>, confirmo que recibo por parte de <strong>Transportes Valadez S. de R.L. de C.V.</strong> una línea telefónica corporativa (SIM) para uso exclusivo en actividades laborales.</p>
    
    <div class="info-box">
        <strong>Información de la línea asignada</strong><br>
        <strong>Número telefónico:</strong> <?= htmlspecialchars($data['numero_telefono']) ?><br>
        <strong>Correo corporativo asignado:</strong> <?= htmlspecialchars($data['cuenta_google']) ?><br>
        <strong>Compañía telefónica:</strong> TELCEL<br>
        <strong>Equipo entregado:</strong> <?= htmlspecialchars($data['equipo'] . ' ' . $data['modelo']) ?><br>
        <strong>Comentarios de entrega:</strong> <?= htmlspecialchars($data['comentarios_entrega']) ?><br>
        <strong>Detalles:</strong> <?= htmlspecialchars($data['comentarios_extras']) ?>
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
        <strong><?= htmlspecialchars($data['usuario']) ?></strong>
    </div>
</div>

</body>
</html>