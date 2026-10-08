<?php
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['logueado']) || ($_SESSION['rol'] ?? '') !== 'usuario') { http_response_code(403); exit('Inicia sesión para consultar la ayuda.'); }
require_once ROOT_PATH . 'backend/conexion.php';
$busqueda = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($busqueda, 'UTF-8') > 100) $busqueda = mb_substr($busqueda, 0, 100, 'UTF-8');
if ($busqueda === '') {
    $stmt = $conexion->prepare('SELECT id,titulo,resumen,categoria,contenido,actualizado_en FROM base_conocimiento WHERE publicado=1 ORDER BY categoria,titulo');
} else {
    $like = '%' . $busqueda . '%';
    $stmt = $conexion->prepare('SELECT id,titulo,resumen,categoria,contenido,actualizado_en FROM base_conocimiento WHERE publicado=1 AND (titulo LIKE ? OR resumen LIKE ? OR contenido LIKE ? OR palabras_clave LIKE ?) ORDER BY categoria,titulo');
    $stmt->bind_param('ssss', $like, $like, $like, $like);
}
$stmt->execute();
$articulos = $stmt->get_result();
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<section class="container-fluid py-3">
    <div class="mb-4"><h2><i class="fa fa-circle-question me-2"></i>Centro de ayuda</h2><p class="text-muted">Busca guías rápidas para resolver dudas comunes.</p></div>
    <form method="get" class="d-flex gap-2 mb-4" id="buscarAyuda"><input class="form-control" name="q" maxlength="100" placeholder="Buscar en los artículos..." value="<?= $e($busqueda) ?>"><button class="btn btn-primary">Buscar</button><?php if ($busqueda !== ''): ?><button type="button" class="btn btn-outline-secondary" id="limpiarAyuda">Limpiar</button><?php endif; ?></form>
    <div id="articulosAyuda" class="row g-3">
    <?php if ($articulos->num_rows === 0): ?><div class="col-12"><div class="alert alert-light border">No encontramos artículos<?= $busqueda !== '' ? ' para esa búsqueda' : ' publicados todavía' ?>.</div></div><?php endif; ?>
    <?php while ($articulo = $articulos->fetch_assoc()): ?><div class="col-12"><article class="card border-0 shadow-sm"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-2"><h4 class="h5 mb-1"><?= $e($articulo['titulo']) ?></h4><span class="badge bg-light text-primary align-self-start"><?= $e($articulo['categoria'] ?: 'General') ?></span></div><?php if ($articulo['resumen']): ?><p class="text-muted mt-2 mb-2"><?= $e($articulo['resumen']) ?></p><?php endif; ?><details class="mt-2"><summary class="text-primary fw-semibold">Leer guía</summary><div class="mt-3" style="white-space:pre-wrap; overflow-wrap:anywhere"><?= $e($articulo['contenido']) ?></div></details><small class="text-muted d-block mt-3">Actualizado <?= $e(date('d/m/Y', strtotime($articulo['actualizado_en']))) ?></small></div></article></div><?php endwhile; ?>
    </div>
</section>
<script>
(function($){
    $('#buscarAyuda').on('submit', function(ev){
        ev.preventDefault();
        $('#pageContent').load('ticket/ayuda.php?' + $(this).serialize());
    });
    $('#limpiarAyuda').on('click', function(){ $('#pageContent').load('ticket/ayuda.php'); });
})(jQuery);
</script>
