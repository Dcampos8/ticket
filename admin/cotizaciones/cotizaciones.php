<?php
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('cotizaciones');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Token CSRF de la sesión (se genera una sola vez)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Mensaje flash (patrón Post/Redirect/Get para evitar reenvíos al refrescar)
$msg = $_SESSION['flash_msg'] ?? '';
unset($_SESSION['flash_msg']);

/* =========================
   HELPERS
   ========================= */

// Toma la primera palabra de un texto, le quita acentos y caracteres raros,
// y la deja lista para usarse dentro de un folio.
function primeraPalabraParaFolio($texto) {
    $texto = trim((string)$texto);
    if ($texto === '') return '';
    $partes = preg_split('/\s+/', $texto);
    $palabra = $partes[0];
    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $palabra);
    if ($translit !== false) $palabra = $translit;
    $palabra = preg_replace('/[^A-Za-z0-9]/', '', $palabra);
    return strtoupper(substr($palabra, 0, 10));
}

// Genera un folio legible (fecha + random + cliente + producto) y garantiza
// que no choque con uno ya existente en la tabla.
function generarFolio($conexion, $clienteNombre, $primerProducto) {
    $fecha = date('ymd');
    $clientePart = primeraPalabraParaFolio($clienteNombre);
    $productoPart = primeraPalabraParaFolio($primerProducto);

    do {
        $random = strtoupper(bin2hex(random_bytes(2))); // 4 caracteres hex
        $folio = "COT-{$fecha}-{$random}";
        if ($clientePart !== '') $folio .= "-{$clientePart}";
        if ($productoPart !== '') $folio .= "-{$productoPart}";

        $stmtCheck = $conexion->prepare("SELECT id FROM quotations WHERE folio = ? LIMIT 1");
        $stmtCheck->bind_param("s", $folio);
        $stmtCheck->execute();
        $existe = $stmtCheck->get_result()->num_rows > 0;
        $stmtCheck->close();
    } while ($existe);

    return $folio;
}

function claseAlerta($msg) {
    if (str_starts_with($msg, '✅')) return 'alert-success';
    if (str_starts_with($msg, '❌')) return 'alert-danger';
    if (str_starts_with($msg, '🗑️')) return 'alert-secondary';
    return 'alert-info';
}

/* =========================
   ELIMINAR COTIZACIÓN (POST + CSRF, ya no por GET)
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $_SESSION['flash_msg'] = "❌ Token de seguridad inválido. Recarga la página e intenta de nuevo.";
    } else {
        $id = intval($_POST['eliminar']);
        if ($id > 0) {
            // Obtenemos el logo antes de borrar, para poder eliminarlo del disco
            $stmtLogo = $conexion->prepare("SELECT company_logo FROM quotations WHERE id = ?");
            $stmtLogo->bind_param("i", $id);
            $stmtLogo->execute();
            $logoRow = $stmtLogo->get_result()->fetch_assoc();
            $stmtLogo->close();

            $conexion->begin_transaction();
            try {
                $stmtDelItems = $conexion->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                $stmtDelItems->bind_param("i", $id);
                $stmtDelItems->execute();
                $stmtDelItems->close();

                $stmtDelQuote = $conexion->prepare("DELETE FROM quotations WHERE id = ?");
                $stmtDelQuote->bind_param("i", $id);
                $stmtDelQuote->execute();
                $stmtDelQuote->close();

                $conexion->commit();

                if (!empty($logoRow['company_logo'])) {
                    $logoFile = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $logoRow['company_logo'];
                    if (is_file($logoFile)) {
                        @unlink($logoFile);
                    }
                }

                $_SESSION['flash_msg'] = "🗑️ Cotización eliminada correctamente.";
            } catch (Exception $e) {
                $conexion->rollback();
                $_SESSION['flash_msg'] = "❌ Error al eliminar: " . $e->getMessage();
            }
        }
    }

    header("Location: cotizaciones.php");
    exit;
}

/* =========================
   GUARDAR COTIZACIÓN
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cotizacion'])) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $currency = trim($_POST['currency'] ?? 'MXN');
    $productos = $_POST['productos'] ?? [];
    if (!is_array($productos)) $productos = [];
    $logo_path = null;

    $errores = [];

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errores[] = "Token de seguridad inválido, recarga la página e intenta de nuevo.";
    }
    if ($client_name === '') {
        $errores[] = "El nombre del cliente es obligatorio.";
    }
    if (!in_array($currency, ['MXN', 'USD'], true)) {
        $errores[] = "Moneda no válida.";
    }
    if (empty($productos)) {
        $errores[] = "Agrega al menos un producto o servicio.";
    } else {
        foreach ($productos as $p) {
            $nombreProd = trim($p['nombre'] ?? '');
            $cantProd = intval($p['cantidad'] ?? 0);
            $precProd = floatval($p['precio'] ?? 0);
            if ($nombreProd === '') { $errores[] = "Hay un producto sin nombre."; break; }
            if ($cantProd <= 0) { $errores[] = "La cantidad de \"$nombreProd\" debe ser mayor a 0."; break; }
            if ($precProd < 0) { $errores[] = "El precio de \"$nombreProd\" no puede ser negativo."; break; }
        }
    }

    if (empty($errores)) {
        $conexion->begin_transaction();

        try {
            if (!empty($_FILES['logo']['name'])) {
                if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Ocurrió un error al subir el logo.");
                }

                $max_size = 2 * 1024 * 1024; // 2MB
                if ($_FILES['logo']['size'] > $max_size) {
                    throw new Exception("El logo no debe pesar más de 2MB.");
                }

                $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true)) {
                    throw new Exception("Formato de logo no permitido. Usa JPG, PNG, WEBP o GIF.");
                }

                // Verificamos que el contenido sea realmente una imagen (no solo la extensión)
                $infoImagen = @getimagesize($_FILES['logo']['tmp_name']);
                if ($infoImagen === false) {
                    throw new Exception("El archivo subido no es una imagen válida.");
                }

                $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                // Nombre aleatorio (no predecible) en vez de basarnos en time()
                $new_name = bin2hex(random_bytes(8)) . '_logo.' . $ext;

                if (move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $new_name)) {
                    $logo_path = $new_name;
                } else {
                    throw new Exception("No se pudo guardar el logo en el servidor.");
                }
            }

            $primerProducto = reset($productos);
            $folio = generarFolio($conexion, $client_name, $primerProducto['nombre'] ?? '');

            $stmt = $conexion->prepare("INSERT INTO quotations (folio, client_name, client_email, currency, company_logo) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $folio, $client_name, $client_email, $currency, $logo_path);
            $stmt->execute();
            $quotation_id = $conexion->insert_id;
            $stmt->close();

            $total = 0;
            $stmtI = $conexion->prepare("INSERT INTO quotation_items (quotation_id, product_name, product_description, quantity, price, iva_type) VALUES (?, ?, ?, ?, ?, ?)");

            foreach ($productos as $p) {
                $nombreProd = trim($p['nombre'] ?? '');
                $cant = intval($p['cantidad'] ?? 0);
                $prec_base = floatval($p['precio'] ?? 0);
                $desc = trim($p['descripcion'] ?? '');
                $iva_opcion = ($p['iva'] ?? 'incluido') === 'mas_iva' ? 'mas_iva' : 'incluido';

                // Cálculo: si es "+ IVA", multiplicamos por 1.16
                $prec_final = ($iva_opcion === 'mas_iva') ? ($prec_base * 1.16) : $prec_base;
                $subtotal = $cant * $prec_final;

                $stmtI->bind_param("issids", $quotation_id, $nombreProd, $desc, $cant, $prec_final, $iva_opcion);
                $stmtI->execute();
                $total += $subtotal;
            }
            $stmtI->close();

            $stmtTotal = $conexion->prepare("UPDATE quotations SET total = ? WHERE id = ?");
            $stmtTotal->bind_param("di", $total, $quotation_id);
            $stmtTotal->execute();
            $stmtTotal->close();

            $conexion->commit();
            $_SESSION['flash_msg'] = "✅ Guardada con éxito: $folio ($currency)";
            header("Location: cotizaciones.php");
            exit;
        } catch (Exception $e) {
            $conexion->rollback();
            // Si ya se había subido un logo pero falló el resto, lo limpiamos
            if ($logo_path) {
                $logoFile = $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $logo_path;
                if (is_file($logoFile)) @unlink($logoFile);
            }
            $msg = "❌ Error: " . $e->getMessage();
        }
    } else {
        $msg = "❌ " . implode(' ', $errores);
    }
}

/* =========================
   CONSULTA HISTORIAL
   ========================= */
$result = $conexion->query("
    SELECT q.*,
    (SELECT GROUP_CONCAT(product_name SEPARATOR ', ') FROM quotation_items WHERE quotation_id = q.id) AS lista_productos
    FROM quotations q ORDER BY q.id DESC
");

/* =========================
   ESTADÍSTICAS RÁPIDAS (tarjetas del encabezado)
   ========================= */
$stats = $conexion->query("
    SELECT
        COUNT(*) AS total_cotizaciones,
        COALESCE(SUM(CASE WHEN currency = 'MXN' THEN total ELSE 0 END), 0) AS total_mxn,
        COALESCE(SUM(CASE WHEN currency = 'USD' THEN total ELSE 0 END), 0) AS total_usd
    FROM quotations
")->fetch_assoc();
// Renderizar navegación después de los handlers: guardar/eliminar redirigen.
include (ROOT_PATH . 'admin/menu.php');
?>
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/pages/admin__cotizaciones__cotizaciones.css?v=<?= filemtime(__DIR__ . '/../../css/pages/admin__cotizaciones__cotizaciones.css') ?>">
<link rel="stylesheet" href="<?= defined('BASE_URL') ? BASE_URL : '/' ?>css/brand.css?v=<?= filemtime(__DIR__ . '/../../css/brand.css') ?>">
<div class="container-fluid mt-4 cotz-page">

    <!-- Tarjetas de resumen -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="cotz-stat">
                <div class="cotz-stat-icon"><i class="fa-solid fa-file-invoice-dollar"></i></div>
                <div>
                    <p class="cotz-stat-label">Cotizaciones</p>
                    <div class="cotz-stat-value"><?= number_format($stats['total_cotizaciones']) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cotz-stat">
                <div class="cotz-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <p class="cotz-stat-label">Total en MXN</p>
                    <div class="cotz-stat-value">$<?= number_format($stats['total_mxn'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cotz-stat">
                <div class="cotz-stat-icon"><i class="fa-solid fa-dollar-sign"></i></div>
                <div>
                    <p class="cotz-stat-label">Total en USD</p>
                    <div class="cotz-stat-value">$<?= number_format($stats['total_usd'], 2) ?></div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="cotz-stat">
                <div class="cotz-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div>
                    <p class="cotz-stat-label">Estatus</p>
                    <div class="cotz-stat-value" style="font-size:1rem;">Módulo activo</div>
                </div>
            </div>
        </div>
    </div>

    <div class="cotz-card mb-4">
        <div class="cotz-card-header">
            <h3><span class="cotz-card-icon"><i class="fa-solid fa-file-invoice-dollar"></i></span> Nueva Cotización</h3>
        </div>
        <div class="cotz-card-body">
            <?php if($msg): ?><div class="alert <?= claseAlerta($msg) ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="formCotizacion">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Cliente</label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="client_email" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Moneda</label>
                        <select name="currency" id="selectCurrency" class="form-select fw-bold">
                            <option value="MXN">Peso Mexicano (MXN)</option>
                            <option value="USD">Dólar Americano (USD)</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Logo (Opcional, máx. 2MB)</label>
                        <div class="cotz-logo-drop">
                            <img id="logoPreview" class="cotz-logo-preview" alt="Vista previa del logo">
                            <input type="file" name="logo" id="inputLogo" class="form-control form-control-sm" accept="image/png,image/jpeg,image/webp,image/gif">
                        </div>
                    </div>
                </div>

                <h5 class="mt-4 mb-3"><i class="fa-solid fa-boxes-stacked me-2"></i> Artículos / Servicios</h5>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="tablaArticulos">
                        <thead>
                            <tr>
                                <th>Producto / Servicio</th>
                                <th>Descripción</th>
                                <th style="width:100px;">Cant.</th>
                                <th style="width:190px;">Precio Unit.</th>
                                <th style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>

                <div class="cotz-form-footer">
                    <button type="button" id="btnFila" class="btn btn-cotz-outline btn-sm"><i class="fa fa-plus me-1"></i> Agregar Fila</button>

                    <div class="d-flex align-items-center gap-4 flex-wrap">
                        <div class="cotz-total-box">
                            <p class="cotz-stat-label mb-0">Total estimado</p>
                            <div class="cotz-total-value" id="totalEstimado">$0.00</div>
                        </div>
                        <button type="submit" name="guardar_cotizacion" class="btn btn-cotz-primary"><i class="fa fa-check me-1"></i> Guardar Cotización</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="cotz-card">
        <div class="cotz-card-header">
            <h3><span class="cotz-card-icon"><i class="fa-solid fa-clock-rotate-left"></i></span> Historial</h3>
            <input type="text" id="buscarHistorial" class="form-control form-control-sm cotz-search" placeholder="Buscar por folio o cliente...">
        </div>
        <div class="cotz-card-body">
            <?php if ($result->num_rows === 0): ?>
                <div class="cotz-empty">
                    <i class="fa-regular fa-folder-open"></i>
                    Aún no hay cotizaciones registradas.<br>Crea la primera con el formulario de arriba.
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table id="tablaHistorial" class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Artículos</th>
                            <th>Total</th>
                            <th>Moneda</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <?php
                            $moneda_limpia = explode(' ', $row['currency'])[0];
                            $inicial = mb_strtoupper(mb_substr(trim($row['client_name']), 0, 1));
                        ?>
                        <tr>
                            <td><span class="cotz-folio"><?= htmlspecialchars($row['folio'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <div class="cotz-client">
                                    <span class="cotz-avatar"><?= htmlspecialchars($inicial, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?= htmlspecialchars($row['client_name']) ?>
                                </div>
                            </td>
                            <td class="small text-muted" style="max-width: 300px; white-space: normal; overflow-wrap: anywhere;">
                                <?= htmlspecialchars($row['lista_productos']) ?>
                            </td>
                            <td class="fw-bold">$<?= number_format($row['total'], 2) ?></td>
                            <td>
                                <span class="badge <?= ($moneda_limpia == 'USD') ? 'badge-cotz-usd' : 'badge-cotz-mxn' ?>">
                                    <?= htmlspecialchars($moneda_limpia, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                            <td>
                                <a href="pdf_cotizacion.php?id=<?= (int)$row['id'] ?>" target="_blank" class="cotz-icon-btn" title="Ver PDF">
                                    <i class="fa fa-file-pdf"></i>
                                </a>
                                <form method="POST" style="display:inline" onsubmit="return confirm('¿Eliminar esta cotización?');">
                                    <input type="hidden" name="eliminar" value="<?= (int)$row['id'] ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="cotz-icon-btn cotz-icon-danger" title="Eliminar">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
$(document).ready(function(){
    let c = 0;

    function formatMoney(n) {
        return '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function recalcularTotales() {
        let total = 0;
        $('#tablaArticulos tbody tr').each(function(){
            const $row = $(this);
            const cant = parseFloat($row.find('.cotz-input-cant').val()) || 0;
            const precio = parseFloat($row.find('.cotz-input-precio').val()) || 0;
            const iva = $row.find('.cotz-input-iva').val();
            const precioFinal = (iva === 'mas_iva') ? (precio * 1.16) : precio;
            const subtotal = cant * precioFinal;
            $row.find('.cotz-subtotal-valor').text(formatMoney(subtotal));
            total += subtotal;
        });
        $('#totalEstimado').text(formatMoney(total));
    }

    function agregarFila() {
        $('#tablaArticulos tbody').append(`
            <tr>
                <td><input type="text" name="productos[${c}][nombre]" class="form-control" required></td>
                <td><input type="text" name="productos[${c}][descripcion]" class="form-control"></td>
                <td><input type="number" name="productos[${c}][cantidad]" class="form-control text-center cotz-input-cant" value="1" min="1"></td>
                <td>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" name="productos[${c}][precio]" class="form-control text-end cotz-input-precio" required min="0">
                    </div>
                    <div class="mt-1">
                        <select name="productos[${c}][iva]" class="form-select form-select-sm cotz-input-iva">
                            <option value="incluido">IVA Incluido</option>
                            <option value="mas_iva">+ 16% IVA</option>
                        </select>
                    </div>
                    <div class="cotz-subtotal">Subtotal: <span class="cotz-subtotal-valor">$0.00</span></div>
                </td>
                <td class="text-center"><button type="button" class="btn btn-link text-danger remove" title="Quitar fila"><i class="fa fa-trash"></i></button></td>
            </tr>`);
        c++;
        recalcularTotales();
    }

    $('#btnFila').click(function(){ agregarFila(); });

    $(document).on('click', '.remove', function(){
        if($('#tablaArticulos tbody tr').length > 1) {
            $(this).closest('tr').remove();
            recalcularTotales();
        }
    });

    $(document).on('input change', '.cotz-input-cant, .cotz-input-precio, .cotz-input-iva, #selectCurrency', function(){
        recalcularTotales();
    });

    // Vista previa del logo antes de subirlo
    $('#inputLogo').on('change', function(){
        const file = this.files[0];
        const $preview = $('#logoPreview');
        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = e => { $preview.attr('src', e.target.result).show(); };
            reader.readAsDataURL(file);
        } else {
            $preview.hide().attr('src', '');
        }
    });

    // Buscador simple del historial (por folio o cliente)
    $('#buscarHistorial').on('input', function(){
        const q = $(this).val().toLowerCase().trim();
        $('#tablaHistorial tbody tr').each(function(){
            const texto = $(this).text().toLowerCase();
            $(this).toggle(texto.indexOf(q) !== -1);
        });
    });

    agregarFila();
});
</script>
