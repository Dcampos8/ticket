<?php
// En producción no mostramos errores al navegador (fuga de información),
// pero sí los seguimos registrando en el log del servidor.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

// Mismo control de acceso que cotizaciones.php: sin esto, cualquiera con el
// link (o adivinando el ?id=) podía generar PDFs sin haber iniciado sesión.
require_once(__DIR__ . '/../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('cotizaciones');

// Importación de librerías y conexión utilizando rutas absolutas de servidor
require_once($_SERVER['DOCUMENT_ROOT'] . '/backend/fpdf/fpdf.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/backend/conexion.php');

$id_cotizacion = intval($_GET['id'] ?? 0);
if ($id_cotizacion <= 0) {
    die("Cotización inválida.");
}

// 1. OBTENER DATOS DE LA COTIZACIÓN
// Como la información del cliente vive en la misma tabla, no necesitamos JOINs.
$stmt = $conexion->prepare("SELECT * FROM quotations WHERE id = ?");
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();
$cotizacion = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$cotizacion) {
    die("La cotización no existe.");
}

// Fecha de emisión: usa created_at si la tabla la tiene, si no cae al día de hoy.
$fechaEmision = !empty($cotizacion['created_at'])
    ? date('d/m/Y', strtotime($cotizacion['created_at']))
    : date('d/m/Y');
// 2. OBTENER DETALLES DE LOS PRODUCTOS
$stmt = $conexion->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->bind_param("i", $id_cotizacion);
$stmt->execute();
$productos = $stmt->get_result();

// Función auxiliar para codificación de caracteres especiales (acentos, Ñ)
function txt($t) {
    return mb_convert_encoding($t, 'ISO-8859-1', 'UTF-8');
}

/**
 * Función Avanzada: Convierte formatos no soportados (como WebP) a PNG temporal
 * para que FPDF pueda procesarlos sin romper el script.
 */
function procesar_imagen_fpdf($ruta_origen) {
    if (empty($ruta_origen) || !file_exists($ruta_origen)) return false;

    $ext = strtolower(pathinfo($ruta_origen, PATHINFO_EXTENSION));

    // Si ya es un formato nativo compatible, lo enviamos directo
    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        return $ruta_origen;
    }

    // Si es WEBP, lo transformamos dinámicamente a PNG temporal
    if ($ext === 'webp') {
        if (function_exists('imagecreatefromwebp')) {
            $im = @imagecreatefromwebp($ruta_origen);
            if ($im) {
                $ruta_temporal = sys_get_temp_dir() . '/' . pathinfo($ruta_origen, PATHINFO_FILENAME) . '_converted.png';
                imagepng($im, $ruta_temporal);
                imagedestroy($im);
                return $ruta_temporal; // Retorna la ruta del PNG temporal generado
            }
        }
    }

    // NOTA CRÍTICA: FPDF no tiene forma de renderizar SVG directamente sin librerías externas masivas.
    // Si es SVG, la mejor práctica es avisar o ignorarlo para evitar crasheos.
    if ($ext === 'svg') {
        return false;
    }

    return false;
}

// 3. CLASE EXTENDIDA DE FPDF
class PDF extends FPDF {
    public $logo, $total_iva = 0, $subtotal_neto = 0;
    private $archivo_temporal_logo = null;

    function __construct($logo) {
        parent::__construct();
        // Ruta absoluta apuntando a la carpeta de cargas en la raíz del servidor
        $this->logo = !empty($logo) ? $_SERVER['DOCUMENT_ROOT'] . '/uploads/' . $logo : '';
    }

    // Cabecera del documento
    function Header() {
        // Renderizado e inserción del Logotipo con soporte Multi-formato
        if (!empty($this->logo) && file_exists($this->logo)) {
            $logo_ok = procesar_imagen_fpdf($this->logo);
            if ($logo_ok) {
                // Si generó un archivo en el directorio temporal, guardamos la ruta para borrarlo al terminar
                if (strpos($logo_ok, sys_get_temp_dir()) !== false) {
                    $this->archivo_temporal_logo = $logo_ok;
                }
                $this->Image($logo_ok, 10, 8, 30); // X=10, Y=8, Ancho=30mm
            }
        }

        // Datos de la empresa (Alineados a la derecha) — paleta neutra slate
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(15, 23, 42); // slate-900
        $this->Cell(0, 5, txt('Transportes Valadez'), 0, 1, 'R');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(100, 116, 139); // slate-500
        $this->Cell(0, 5, txt('José María Romo 139, Cd. Industrial, 20290, Aguascalientes, Ags.'), 0, 1, 'R');
        $this->Cell(0, 5, txt('Tel: 449-156-8020'), 0, 1, 'R');

        // Línea divisoria estética (gris suave en vez de negro puro)
        $this->SetDrawColor(226, 232, 240); // slate-200
        $this->Line(10, 25, 200, 25);
        $this->Ln(15);
        $this->SetTextColor(0, 0, 0);
    }

    // Pie de página del documento
    function Footer() {
        $this->SetY(-20);
        $this->SetDrawColor(226, 232, 240);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(148, 163, 184); // slate-400
        $this->Cell(0, 10, txt('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    // Limpieza automática al cerrar el PDF
    function Close() {
        parent::Close();
        // Si creamos un archivo temporal .png a partir de un .webp, lo eliminamos para no llenar el disco
        if ($this->archivo_temporal_logo && file_exists($this->archivo_temporal_logo)) {
            @unlink($this->archivo_temporal_logo);
        }
    }

    // Función para calcular cuántas líneas ocupará un texto multilinea
    function NbLines($w, $txt) {
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0) $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n") $nb--;
        $sep = -1; $i = 0; $j = 0; $l = 0; $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") { $i++; $sep = -1; $j = $i; $l = 0; $nl++; continue; }
            if ($c == ' ') $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) { if ($i == $j) $i++; }
                else $i = $sep + 1;
                $sep = -1; $j = $i; $l = 0; $nl++;
            } else $i++;
        }
        return $nl;
    }

    // Tabla dinámica para el desglose de productos/servicios
    function FancyTable($header, $productos) {
        $this->SetFillColor(51, 65, 85); // slate-700, neutro en vez del azul corporativo
        $this->SetTextColor(255);
        $this->SetFont('Arial', 'B', 9);
        $this->SetDrawColor(203, 213, 225); // slate-300, líneas más suaves

        $w = [10, 40, 70, 15, 25, 30]; // Anchos de columnas
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 8, txt($header[$i]), 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetTextColor(15, 23, 42); // slate-900
        $this->SetFont('Arial', '', 8);
        $contador = 1;
        $fill = false; // alterna el fondo de cada fila (zebra) para facilitar la lectura

        while ($p = $productos->fetch_assoc()) {
            $nombre = $p['product_name'] ?? '-';
            $desc = $p['product_description'] ?? '';
            $cantidad = (float)($p['quantity'] ?? 0);
            $precio_bd = (float)($p['price'] ?? 0);
            $es_mas_iva = (isset($p['iva_type']) && $p['iva_type'] === 'mas_iva');

            // Desglose matemático correcto del IVA
            if ($es_mas_iva) {
                $precio_neto_unitario = $precio_bd / 1.16;
                $iva_unitario = $precio_bd - $precio_neto_unitario;
            } else {
                $precio_neto_unitario = $precio_bd;
                $iva_unitario = 0;
            }

            $base_neta = $cantidad * $precio_neto_unitario;
            $iva_total_fila = $cantidad * $iva_unitario;

            $this->subtotal_neto += $base_neta;
            $this->total_iva += $iva_total_fila;

            $nb = max($this->NbLines($w[1], txt($nombre)), $this->NbLines($w[2], txt($desc)));
            $h = 5 * $nb;

            if ($this->GetY() + $h > $this->PageBreakTrigger) {
                $this->AddPage($this->CurPageOrder);
            }

            $this->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);

            $x = $this->GetX();
            $y = $this->GetY();

            $this->Cell($w[0], $h, $contador++, 1, 0, 'C', true);
            $this->MultiCell($w[1], 5, txt($nombre), 1, 'L', true);
            $this->SetXY($x + $w[0] + $w[1], $y);

            $this->MultiCell($w[2], 5, txt($desc), 1, 'L', true);
            $this->SetXY($x + $w[0] + $w[1] + $w[2], $y);

            $this->Cell($w[3], $h, number_format($cantidad, 0), 1, 0, 'C', true);
            $this->Cell($w[4], $h, '$' . number_format($precio_neto_unitario, 2), 1, 0, 'R', true);
            $this->Cell($w[5], $h, '$' . number_format($base_neta, 2), 1, 1, 'R', true);

            $fill = !$fill;
        }
    }
}

// 4. GENERACIÓN DEL DOCUMENTO PDF
$pdf = new PDF($cotizacion['company_logo'] ?? '');
$pdf->AliasNbPages();
$pdf->AddPage('P', 'Letter');

// Eyebrow + folio, centrados
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 116, 139); // slate-500
$pdf->Cell(0, 5, txt('COTIZACIÓN'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetTextColor(15, 23, 42); // slate-900
$pdf->Cell(0, 8, txt('# ' . $cotizacion['folio']), 0, 1, 'C');
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

// Tarjeta con los datos del cliente y de emisión (antes esta info no aparecía en el PDF)
$boxY = $pdf->GetY();
$boxH = 20;
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetFillColor(248, 250, 252);
$pdf->Rect(10, $boxY, 190, $boxH, 'DF');

$pdf->SetFont('Arial', 'B', 7);
$pdf->SetTextColor(100, 116, 139);
$pdf->SetXY(14, $boxY + 3);
$pdf->Cell(85, 4, txt('CLIENTE'), 0, 0);
$pdf->SetXY(110, $boxY + 3);
$pdf->Cell(85, 4, txt('FECHA DE EMISIÓN'), 0, 0);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetTextColor(15, 23, 42);
$pdf->SetXY(14, $boxY + 8);
$pdf->Cell(85, 5, txt($cotizacion['client_name'] ?? '-'), 0, 0);
$pdf->SetXY(110, $boxY + 8);
$pdf->Cell(85, 5, txt($fechaEmision), 0, 0);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->SetXY(14, $boxY + 13);
$pdf->Cell(85, 5, txt($cotizacion['client_email'] ?: 'Sin correo registrado'), 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(79, 70, 229); // acento indigo, único toque de color del documento
$pdf->SetXY(110, $boxY + 13);
$pdf->Cell(85, 5, txt('Moneda: ' . $cotizacion['currency']), 0, 0);

$pdf->SetTextColor(0, 0, 0);
$pdf->SetXY(10, $boxY + $boxH + 6);

// Impresión de la Tabla Principal
$pdf->FancyTable(['#', 'Producto', 'Descripción', 'Cant.', 'Precio', 'Subtotal'], $productos);

// Bloque de Totales Finales Desglosados
$pdf->Ln(2);
$pdf->SetDrawColor(226, 232, 240);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(160, 8, txt('Subtotal:'), 0, 0, 'R');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(30, 8, '$' . number_format($pdf->subtotal_neto, 2), 0, 1, 'R');

$pdf->SetTextColor(71, 85, 105);
$pdf->Cell(160, 8, txt('IVA (16%):'), 0, 0, 'R');
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(30, 8, '$' . number_format($pdf->total_iva, 2), 0, 1, 'R');

$pdf->Ln(1);
$pdf->SetFillColor(79, 70, 229); // indigo — acento reservado solo para el total final
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(160, 10, txt('TOTAL (' . $cotizacion['currency'] . '):'), 0, 0, 'R', true);
$pdf->Cell(30, 10, '$' . number_format($pdf->subtotal_neto + $pdf->total_iva, 2), 0, 1, 'R', true);
$pdf->SetTextColor(0, 0, 0);

// Limpieza de búfer de salida
ob_clean();

// 2. Construimos el nombre del archivo: folio + nombre del cliente
// Usamos trim() para quitar espacios extras y preg_replace para limpiar caracteres raros
$nombre_base = $cotizacion['folio'] . ' - ' . $cotizacion['client_name'];
$nombre_archivo_limpio = preg_replace('/[^A-Za-z0-9\-\_]/', '_', $nombre_base);

// 3. Generamos la descarga o visualización
// 'I' es para mostrar en navegador, 'D' forzaría la descarga automática
$pdf->Output('I', $nombre_archivo_limpio . '.pdf');
exit;