<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('lineas');
$token = asegurarTokenCsrf();
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$campos = [
    'tipo' => 'equipo', 'fecha' => date('Y-m-d'), 'lugar' => 'Aguascalientes, Ags.',
    'nombre' => '', 'tipo_emp' => '', 'puesto' => '', 'area' => '', 'estatus_linea' => 'Activa',
    'telefono' => '', 'compania' => '', 'correo' => '', 'equipo' => '', 'modelo' => '',
    'imei' => '', 'equipo_nuevo' => 'NO', 'resguardo' => '', 'costo_equipo' => '',
    'costo_sim' => '', 'entrega' => '', 'detalles' => '', 'entregado_por' => '',
];
$errores = [];
$generada = false;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    foreach ($campos as $key => $default) $campos[$key] = trim((string) ($_POST[$key] ?? $default));
    // Los espacios al inicio o final en comentarios se recortan, la fecha y datos se conservan tal cual.
    if (!validarTokenCsrf()) $errores[] = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    if (!in_array($campos['tipo'], ['equipo', 'sim'], true)) $errores[] = 'Selecciona un tipo de responsiva válido.';
    $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $campos['fecha']);
    if (!$fechaValida || $fechaValida->format('Y-m-d') !== $campos['fecha']) $errores[] = 'Captura una fecha válida.';
    if ($campos['nombre'] === '') $errores[] = 'El nombre de la persona resguardante es obligatorio.';
    if (!$errores) $generada = true;
}
$fechaTexto = '';
if ($generada) {
    $fechaObj = new DateTimeImmutable($campos['fecha']);
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $fechaTexto = $fechaObj->format('d') . ' de ' . $meses[(int) $fechaObj->format('n') - 1] . ' de ' . $fechaObj->format('Y');
}
?>
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Responsiva manual de línea</title>
<link rel="stylesheet" href="<?= $e(BASE_URL) ?>css/brand.css">
<style>
:root{--navy:#17324d;--blue:#1769aa;--line:#d5dde6;--ink:#263445;--muted:#5f6c7b}*{box-sizing:border-box}body{margin:0;background:#f2f5f8;color:var(--ink);font:15px system-ui,-apple-system,"Segoe UI",sans-serif}.wrap{max-width:1040px;margin:28px auto;padding:0 18px}.top{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.top h1{font-size:25px;margin:0;color:var(--navy)}.card{background:#fff;border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:20px;box-shadow:0 8px 24px #1720330b}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field label{display:block;font-size:13px;font-weight:700;margin-bottom:5px}.field input,.field select,.field textarea{width:100%;padding:10px 11px;border:1px solid #c5cfdb;border-radius:8px;font:inherit;background:#fff}.field textarea{min-height:72px;resize:vertical}.full{grid-column:1/-1}.hint{color:var(--muted);font-size:13px}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 15px;font:inherit;font-weight:700;text-decoration:none;cursor:pointer}.primary{background:var(--blue);color:white}.secondary{background:#e9eef4;color:var(--ink)}.success{background:#16804a;color:white}.alert{background:#fff0ef;color:#9b251b;padding:12px 14px;border-radius:8px;margin-bottom:14px}.preview-container{display:none}.preview-container.visible{display:block}.sheet{background:white;max-width:850px;margin:0 auto;padding:42px 48px;border:1px solid var(--line);box-shadow:0 8px 26px #17203314;min-height:10in}.sheet h2{text-align:center;color:var(--navy);font-size:20px;margin:0 0 28px;border-bottom:2px solid var(--blue);padding-bottom:12px}.date{text-align:right;margin-bottom:24px}.sheet p,.sheet li{line-height:1.55}.info{border:1px solid var(--line);border-radius:8px;background:#f7f9fb;padding:15px 18px;margin:18px 0 24px}.info p{margin:7px 0}.terms h3{color:var(--navy);font-size:15px;margin:18px 0 7px}.terms ul{padding-left:22px}.signatures{display:grid;grid-template-columns:1fr 1fr;gap:45px;margin-top:55px;text-align:center}.sign{border-top:1px solid #202a35;padding-top:8px;min-height:45px}.blank{color:#5d6876}.screen-only{display:block}
@media(max-width:680px){.grid{grid-template-columns:1fr}.full{grid-column:auto}.top{align-items:flex-start;flex-direction:column}.sheet{padding:26px 22px;min-height:0}.signatures{gap:20px}}
@media print{@page{size:letter;margin:16mm}.screen-only,form,.top,.actions{display:none!important}body{background:#fff}.wrap{max-width:none;margin:0;padding:0}.card{border:0;box-shadow:none;padding:0;margin:0}.preview-container{display:block!important}.sheet{max-width:none;min-height:0;padding:0;border:0;box-shadow:none}.sheet h2{font-size:18pt}.info{break-inside:avoid}.signatures{break-inside:avoid}}
</style></head><body><main class="wrap">
<header class="top screen-only"><div><h1>Responsiva desde cero</h1><div class="hint">Captura los datos manualmente y genera una hoja lista para imprimir o guardar en PDF.</div></div><a class="btn secondary" href="<?= $e(BASE_URL) ?>admin/lineas/">Volver a Líneas</a></header>
<section class="card screen-only">
<?php if ($errores): ?><div class="alert"><?php foreach ($errores as $error): ?><div><?= $e($error) ?></div><?php endforeach; ?></div><?php endif; ?>
<form method="post" action=""><input type="hidden" name="csrf_token" value="<?= $e($token) ?>">
<div class="grid">
<div class="field"><label for="tipo">Tipo de responsiva</label><select id="tipo" name="tipo"><option value="equipo" <?= $campos['tipo']==='equipo'?'selected':'' ?>>Equipo celular</option><option value="sim" <?= $campos['tipo']==='sim'?'selected':'' ?>>Línea / tarjeta SIM</option></select></div>
<div class="field"><label for="fecha">Fecha de firma</label><input id="fecha" name="fecha" type="date" required value="<?= $e($campos['fecha']) ?>"></div>
<div class="field"><label for="lugar">Lugar</label><input id="lugar" name="lugar" value="<?= $e($campos['lugar']) ?>"></div>
<div class="field"><label for="nombre">Nombre de la persona resguardante *</label><input id="nombre" name="nombre" required value="<?= $e($campos['nombre']) ?>"></div>
<div class="field"><label for="tipo_emp">Tipo de empleado</label><input id="tipo_emp" name="tipo_emp" value="<?= $e($campos['tipo_emp']) ?>"></div>
<div class="field"><label for="puesto">Puesto</label><input id="puesto" name="puesto" value="<?= $e($campos['puesto']) ?>"></div>
<div class="field"><label for="area">Área</label><input id="area" name="area" value="<?= $e($campos['area']) ?>"></div>
<div class="field"><label for="estatus_linea">Estatus de línea</label><select id="estatus_linea" name="estatus_linea"><?php foreach (['Activa','En Resguardo','Inactiva','Suspendida','Baja'] as $estado): ?><option value="<?= $e($estado) ?>" <?= $campos['estatus_linea']===$estado?'selected':'' ?>><?= $e($estado) ?></option><?php endforeach; ?></select></div>
<div class="field"><label for="telefono">Número telefónico</label><input id="telefono" name="telefono" type="tel" value="<?= $e($campos['telefono']) ?>"></div>
<div class="field"><label for="compania">Compañía telefónica</label><input id="compania" name="compania" value="<?= $e($campos['compania']) ?>"></div>
<div class="field"><label for="correo">Correo corporativo</label><input id="correo" name="correo" type="email" value="<?= $e($campos['correo']) ?>"></div>
<div class="field"><label for="equipo">Marca / equipo</label><input id="equipo" name="equipo" value="<?= $e($campos['equipo']) ?>"></div>
<div class="field"><label for="modelo">Modelo</label><input id="modelo" name="modelo" value="<?= $e($campos['modelo']) ?>"></div>
<div class="field"><label for="imei">IMEI o número de serie</label><input id="imei" name="imei" value="<?= $e($campos['imei']) ?>"></div>
<div class="field"><label for="equipo_nuevo">¿Equipo nuevo?</label><select id="equipo_nuevo" name="equipo_nuevo"><option value="NO" <?= $campos['equipo_nuevo']==='NO'?'selected':'' ?>>No</option><option value="SÍ" <?= $campos['equipo_nuevo']==='SÍ'?'selected':'' ?>>Sí</option></select></div>
<div class="field"><label for="resguardo">Número de resguardo / inventario</label><input id="resguardo" name="resguardo" value="<?= $e($campos['resguardo']) ?>"></div>
<div class="field"><label for="costo_equipo">Valor de reposición del equipo (MXN)</label><input id="costo_equipo" name="costo_equipo" type="number" min="0" step="0.01" value="<?= $e($campos['costo_equipo']) ?>"></div>
<div class="field"><label for="costo_sim">Costo de reposición de SIM (MXN)</label><input id="costo_sim" name="costo_sim" type="number" min="0" step="0.01" value="<?= $e($campos['costo_sim']) ?>"></div>
<div class="field full"><label for="entrega">Comentarios de entrega / accesorios / estado físico</label><textarea id="entrega" name="entrega"><?= $e($campos['entrega']) ?></textarea></div>
<div class="field full"><label for="detalles">Detalles adicionales</label><textarea id="detalles" name="detalles"><?= $e($campos['detalles']) ?></textarea></div>
<div class="field full"><label for="entregado_por">Entregado por</label><input id="entregado_por" name="entregado_por" value="<?= $e($campos['entregado_por']) ?>"></div>
</div><div class="actions"><button class="btn primary" type="submit"><i class="fa-solid fa-file-signature me-2"></i>Generar vista previa</button><a class="btn secondary" href="<?= $e(BASE_URL) ?>admin/lineas/">Cancelar</a></div>
<p class="hint mt-3 mb-0">La responsiva se genera para impresión; no crea ni modifica una línea en el inventario.</p></form></section>
<?php if ($generada): ?>
<div class="actions screen-only"><button class="btn success" type="button" onclick="window.print()"><i class="fa-solid fa-print me-2"></i>Imprimir / Guardar PDF</button><button class="btn secondary" type="button" onclick="document.querySelector('form').scrollIntoView({behavior:'smooth'})">Editar datos</button></div>
<section class="preview-container visible"><article class="sheet">
<h2><?= $campos['tipo']==='equipo' ? 'RESPONSIVA DE EQUIPO CELULAR CORPORATIVO' : 'RESPONSIVA DE LÍNEA Y TARJETA SIM CORPORATIVA' ?></h2>
<div class="date"><?= $e($campos['lugar']) ?>, a <?= $e($fechaTexto) ?></div>
<p>Yo, <strong><?= $e($campos['nombre']) ?></strong><?= $campos['puesto']!=='' ? ', quien ocupa el puesto de <strong>'.$e($campos['puesto']).'</strong>' : '' ?><?= $campos['area']!=='' ? ' del área de <strong>'.$e($campos['area']).'</strong>' : '' ?>, confirmo que recibo de Transportes Valadez S. de R.L. de C.V. los recursos corporativos descritos a continuación, para uso relacionado con mis actividades laborales.</p>
<div class="info"><strong>DATOS DE LA ASIGNACIÓN</strong>
<p><strong>Persona resguardante:</strong> <?= $e($campos['nombre']) ?></p>
<?php if ($campos['puesto']!==''): ?><p><strong>Puesto:</strong> <?= $e($campos['puesto']) ?></p><?php endif; ?>
<?php if ($campos['tipo_emp']!==''): ?><p><strong>Tipo de empleado:</strong> <?= $e($campos['tipo_emp']) ?></p><?php endif; ?>
<?php if ($campos['area']!==''): ?><p><strong>Área:</strong> <?= $e($campos['area']) ?></p><?php endif; ?>
<?php if ($campos['estatus_linea']!==''): ?><p><strong>Estatus de línea:</strong> <?= $e($campos['estatus_linea']) ?></p><?php endif; ?>
<?php if ($campos['telefono']!==''): ?><p><strong>Número telefónico:</strong> <?= $e($campos['telefono']) ?></p><?php endif; ?>
<?php if ($campos['compania']!==''): ?><p><strong>Compañía:</strong> <?= $e($campos['compania']) ?></p><?php endif; ?>
<?php if ($campos['correo']!==''): ?><p><strong>Correo corporativo:</strong> <?= $e($campos['correo']) ?></p><?php endif; ?>
<?php if ($campos['tipo']==='equipo'): ?>
<?php if ($campos['equipo']!=='' || $campos['modelo']!==''): ?><p><strong>Equipo / modelo:</strong> <?= $e(trim($campos['equipo'].' '.$campos['modelo'])) ?></p><?php endif; ?>
<?php if ($campos['imei']!==''): ?><p><strong>IMEI / serie:</strong> <?= $e($campos['imei']) ?></p><?php endif; ?>
<p><strong>Equipo nuevo:</strong> <?= $e($campos['equipo_nuevo']) ?></p>
<?php if ($campos['resguardo']!==''): ?><p><strong>Número de resguardo / inventario:</strong> <?= $e($campos['resguardo']) ?></p><?php endif; ?>
<?php if ($campos['costo_equipo']!==''): ?><p><strong>Valor de reposición indicado:</strong> $<?= number_format((float)$campos['costo_equipo'],2) ?> MXN</p><?php endif; ?>
<?php endif; ?>
<?php if ($campos['tipo']==='sim' && $campos['costo_sim']!==''): ?><p><strong>Costo de reposición indicado para SIM:</strong> $<?= number_format((float)$campos['costo_sim'],2) ?> MXN</p><?php endif; ?>
<?php if ($campos['entrega']!==''): ?><p><strong>Comentarios de entrega:</strong><br><?= nl2br($e($campos['entrega'])) ?></p><?php endif; ?>
<?php if ($campos['detalles']!==''): ?><p><strong>Detalles adicionales:</strong><br><?= nl2br($e($campos['detalles'])) ?></p><?php endif; ?>
<?php if ($campos['entregado_por']!==''): ?><p><strong>Entregado por:</strong> <?= $e($campos['entregado_por']) ?></p><?php endif; ?>
</div>
<div class="terms"><h3>CONDICIONES Y RESPONSABILIDAD</h3>
<ul><li>Los recursos asignados son propiedad de la empresa y deben utilizarse para actividades laborales.</li><li>La persona resguardante se compromete a cuidar los recursos, evitar usos no autorizados y reportar de inmediato cualquier falla, daño, robo o extravío.</li><li>Los recursos deberán devolverse cuando la empresa lo solicite o al terminar la relación laboral.</li><li>Cualquier cargo o reposición deberá determinarse conforme a las políticas vigentes de la empresa.</li></ul>
<p>Declaro haber recibido los recursos descritos y aceptar las condiciones de resguardo aquí indicadas.</p></div>
<div class="signatures"><div class="sign"><strong><?= $e($campos['nombre']) ?></strong><br>Nombre y firma de quien recibe</div><div class="sign"><strong><?= $e($campos['entregado_por'] ?: ' ') ?></strong><br>Nombre y firma de quien entrega</div></div>
</article></section>
<?php endif; ?>
</main><script>document.getElementById('tipo').addEventListener('change',e=>document.body.dataset.tipo=e.target.value);</script></body></html>
