<?php
require_once __DIR__ . '/../../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once ROOT_PATH . 'shared/permisos.php';
require_once ROOT_PATH . 'shared/security.php';
requerirModulo('tickets');
if (!esAdminOSuperior()) { http_response_code(403); exit('No autorizado'); }
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
require_once ROOT_PATH . 'backend/conexion.php';

$error = '';
$articuloEditar = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!validarTokenCsrf()) { http_response_code(403); exit('La sesión expiró. Recarga la página.'); }
    $accion = (string) ($_POST['accion'] ?? 'guardar');
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    if ($accion === 'publicar' && $id > 0) {
        $stmt = $conexion->prepare('UPDATE base_conocimiento SET publicado = 1 - publicado, actualizado_por = ? WHERE id = ?');
        $editor = (string) ($_SESSION['usuario'] ?? 'admin');
        $stmt->bind_param('si', $editor, $id);
        $stmt->execute();
        header('Location: conocimiento.php'); exit;
    }
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $resumen = trim((string) ($_POST['resumen'] ?? ''));
    $categoria = trim((string) ($_POST['categoria'] ?? ''));
    $palabras = trim((string) ($_POST['palabras_clave'] ?? ''));
    $contenido = trim((string) ($_POST['contenido'] ?? ''));
    $publicado = isset($_POST['publicado']) ? 1 : 0;
    if ($titulo === '' || $contenido === '' || mb_strlen($titulo, 'UTF-8') > 180
        || mb_strlen($resumen, 'UTF-8') > 400 || mb_strlen($categoria, 'UTF-8') > 80
        || mb_strlen($palabras, 'UTF-8') > 500 || mb_strlen($contenido, 'UTF-8') > 100000) {
        $error = 'Completa el título y el contenido, respetando los límites de longitud.';
        $articuloEditar = compact('id', 'titulo', 'resumen', 'categoria', 'palabras', 'contenido', 'publicado');
        $articuloEditar['palabras_clave'] = $palabras;
    } else {
        $editor = (string) ($_SESSION['usuario'] ?? 'admin');
        if ($id > 0) {
            $stmt = $conexion->prepare('UPDATE base_conocimiento SET titulo=?, resumen=NULLIF(?, \'\'), categoria=NULLIF(?, \'\'), palabras_clave=NULLIF(?, \'\'), contenido=?, publicado=?, actualizado_por=? WHERE id=?');
        } else {
            $stmt = $conexion->prepare('INSERT INTO base_conocimiento (titulo,resumen,categoria,palabras_clave,contenido,publicado,creado_por,actualizado_por) VALUES (?,NULLIF(?, \'\'),NULLIF(?, \'\'),NULLIF(?, \'\'),?,?,?,?)');
        }
        if ($stmt) {
            if ($id > 0) $stmt->bind_param('sssssisi', $titulo, $resumen, $categoria, $palabras, $contenido, $publicado, $editor, $id);
            else $stmt->bind_param('sssssiss', $titulo, $resumen, $categoria, $palabras, $contenido, $publicado, $editor, $editor);
            $stmt->execute();
            $stmt->close();
            header('Location: conocimiento.php'); exit;
        }
        $error = 'No se pudo guardar el artículo.';
    }
}

if ($articuloEditar === null && isset($_GET['editar'])) {
    $idEditar = filter_input(INPUT_GET, 'editar', FILTER_VALIDATE_INT) ?: 0;
    $stmt = $conexion->prepare('SELECT * FROM base_conocimiento WHERE id=?');
    $stmt->bind_param('i', $idEditar);
    $stmt->execute();
    $articuloEditar = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}
$articulos = $conexion->query('SELECT id,titulo,categoria,publicado,actualizado_en FROM base_conocimiento ORDER BY actualizado_en DESC');
include ROOT_PATH . 'admin/menu.php';
$a = $articuloEditar ?: [];
$e = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3"><div><h2>Base de conocimiento</h2><p class="text-muted mb-0">Guías para ayudar al usuario a resolver problemas frecuentes.</p></div><a class="btn btn-outline-primary" href="<?= htmlspecialchars(BASE_URL) ?>usuario/ticket/ayuda.php" target="_blank" rel="noopener">Ver portal</a></div>
    <?php if ($error !== ''): ?><div class="alert alert-danger"><?= $e($error) ?></div><?php endif; ?>
    <form method="post" class="card card-body shadow-sm mb-4">
        <input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>"><input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">
        <h5><?= !empty($a['id']) ? 'Editar artículo' : 'Nuevo artículo' ?></h5>
        <div class="row g-3"><div class="col-lg-6"><label class="form-label">Título</label><input class="form-control" name="titulo" maxlength="180" required value="<?= $e($a['titulo'] ?? '') ?>"></div>
        <div class="col-lg-3"><label class="form-label">Categoría</label><input class="form-control" name="categoria" maxlength="80" value="<?= $e($a['categoria'] ?? '') ?>"></div>
        <div class="col-lg-3"><label class="form-label">Palabras clave</label><input class="form-control" name="palabras_clave" maxlength="500" value="<?= $e($a['palabras_clave'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Resumen</label><input class="form-control" name="resumen" maxlength="400" value="<?= $e($a['resumen'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Contenido</label><textarea class="form-control" name="contenido" rows="8" maxlength="100000" required><?= $e($a['contenido'] ?? '') ?></textarea><small class="text-muted">El contenido se muestra como texto; HTML no se ejecuta.</small></div>
        <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox" name="publicado" <?= !empty($a['publicado']) ? 'checked' : '' ?>> Publicar en el portal de usuarios</label></div>
        <div class="col-12"><button class="btn btn-primary">Guardar artículo</button> <?php if (!empty($a['id'])): ?><a class="btn btn-outline-secondary" href="conocimiento.php">Cancelar edición</a><?php endif; ?></div></div>
    </form>
    <div class="card shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Título</th><th>Categoría</th><th>Estado</th><th>Actualizado</th><th>Acciones</th></tr></thead><tbody>
    <?php while ($articulo = $articulos->fetch_assoc()): ?><tr><td><?= $e($articulo['titulo']) ?></td><td><?= $e($articulo['categoria'] ?? 'General') ?></td><td><?= $articulo['publicado'] ? 'Publicado' : 'Borrador' ?></td><td><?= $e($articulo['actualizado_en']) ?></td><td><a class="btn btn-sm btn-outline-primary" href="?editar=<?= (int) $articulo['id'] ?>">Editar</a><form method="post" class="d-inline"><input type="hidden" name="csrf_token" value="<?= $e($_SESSION['csrf_token']) ?>"><input type="hidden" name="accion" value="publicar"><input type="hidden" name="id" value="<?= (int) $articulo['id'] ?>"><button class="btn btn-sm btn-outline-secondary"><?= $articulo['publicado'] ? 'Retirar' : 'Publicar' ?></button></form></td></tr><?php endwhile; ?>
    </tbody></table></div></div>
</div>
