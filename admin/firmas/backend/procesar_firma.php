<?php
require_once(__DIR__ . '/../../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('firmas');
require_once ROOT_PATH . 'shared/security.php';
if (!esAdminOSuperior() || !tieneModulo('firmas')) {
    http_response_code(403);
    exit('No autorizado');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !validarTokenCsrf()) {
    http_response_code(403);
    exit('La sesión expiró. Recarga la página e intenta de nuevo.');
}

/* ================= 2. RECEPCIÓN Y LIMPIEZA DE DATOS ================= */
$nombre   = trim((string) ($_POST['nombre'] ?? ''));
$area     = trim((string) ($_POST['area'] ?? ''));
$puesto   = trim((string) ($_POST['puesto'] ?? ''));
$correo   = trim((string) ($_POST['correo'] ?? ''));
$telefono = trim((string) ($_POST['telefono'] ?? ''));

if ($nombre === '' || $puesto === '' || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . BASE_URL . "admin/firmas/firmas.php?error=Revisa+el+nombre,+el+puesto+y+el+correo.");
    exit();
}

$nombre = mb_strtoupper($nombre, 'UTF-8');

// Formatear Puesto para que se vea profesional
$puesto = mb_convert_case(mb_strtolower($puesto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

/* ================= 4. CONFIGURACIÓN DE LIENZO (GD) ================= */
$anchoImg = 650;
$altoImg  = 180;
$img = imagecreatetruecolor($anchoImg, $altoImg);

// Colores
$blanco   = imagecolorallocate($img, 255, 255, 255);
$negro    = imagecolorallocate($img, 0, 0, 0);
$gris     = imagecolorallocate($img, 80, 80, 80);
$rojo_tv  = imagecolorallocate($img, 185, 28, 28); // Rojo Transportes Valadez

imagefilledrectangle($img, 0, 0, $anchoImg, $altoImg, $blanco);

/* --- CARGAR FONDO Y LOGO --- */
$fondoPath = realpath(__DIR__.'/../../../img/fondo-firma.png');
if ($fondoPath && file_exists($fondoPath)) {
    $fondo = imagecreatefrompng($fondoPath);
    // fondo-firma.png es una imagen vertical (con mucho blanco en medio);
    // si se estira completa se ve casi vacía. En vez de eso recortamos una
    // franja del ancho completo, con la proporción del banner (650x180),
    // tomada desde arriba, y esa franja sí se escala para llenar todo el
    // espacio de la firma (efecto "cover", no "stretch").
    $srcAncho  = imagesx($fondo);
    $srcFranja = (int) round($srcAncho * ($altoImg / $anchoImg));
    imagecopyresampled($img, $fondo, 0, 0, 0, 0, $anchoImg, $altoImg, $srcAncho, $srcFranja);
    imagedestroy($fondo);
}

$logoPath = realpath(__DIR__.'/../../../img/tv2.png');
if ($logoPath && file_exists($logoPath)) {
    $logo = imagecreatefrompng($logoPath);
    imagecopyresampled($img, $logo, 25, 35, 0, 0, 115, 90, imagesx($logo), imagesy($logo));
    imagedestroy($logo);
}

/* ================= 5. LÓGICA DE TEXTO RESPONSIVO (AUTOFIT) ================= */
$fontPath = realpath(__DIR__.'/../../../fonts/sport.ttf');
if (!$fontPath || !file_exists($fontPath)) {
    die("Error: No se encontró la fuente en " . $fontPath);
}

$xInicio     = 170; // Donde empieza el texto después del logo
$anchoMaximo = 440; // Espacio disponible para el texto antes de chocar con el borde derecho

// --- AJUSTE DINÁMICO DEL NOMBRE ---
$fontSizeNombre = 18; // Tamaño ideal
do {
    $bbox = imagettfbbox($fontSizeNombre, 0, $fontPath, $nombre);
    $anchoActual = abs($bbox[4] - $bbox[0]);
    if ($anchoActual > $anchoMaximo) {
        $fontSizeNombre--;
    }
} while ($anchoActual > $anchoMaximo && $fontSizeNombre > 9);

imagettftext($img, $fontSizeNombre, 0, $xInicio, 60, $negro, $fontPath, $nombre);

// --- AJUSTE DINÁMICO DEL PUESTO ---
$fontSizePuesto = 13;
do {
    $bboxP = imagettfbbox($fontSizePuesto, 0, $fontPath, $puesto);
    $anchoPActual = abs($bboxP[4] - $bboxP[0]);
    if ($anchoPActual > $anchoMaximo) {
        $fontSizePuesto--;
    }
} while ($anchoPActual > $anchoMaximo && $fontSizePuesto > 8);

imagettftext($img, $fontSizePuesto, 0, $xInicio, 90, $gris, $fontPath, $puesto);

// --- AREA Y DATOS DE CONTACTO ---
$yContacto = 112;
if ($area !== '') {
    imagettftext($img, 11, 0, $xInicio, $yContacto, $gris, $fontPath, $area);
    $yContacto += 20;
}
if (!empty($telefono)) {
    imagettftext($img, 11, 0, $xInicio, $yContacto, $negro, $fontPath, "Tel. " . $telefono);
    $yContacto += 22;
}
imagettftext($img, 11, 0, $xInicio, $yContacto, $rojo_tv, $fontPath, mb_strtolower($correo, 'UTF-8'));

/* ================= 6. GUARDADO Y ENVÍO ================= */
$dirTmp = __DIR__.'/../tmp';
if (!is_dir($dirTmp)) { mkdir($dirTmp, 0755, true); }

$nombreArchivo = 'firma_' . bin2hex(random_bytes(12)) . '.jpg';
$rutaFirma = $dirTmp.'/'.$nombreArchivo;

imagejpeg($img, $rutaFirma, 95);
imagedestroy($img);

// Preparar Email
$boundary = bin2hex(random_bytes(24));
$subject  = "Firma - Transportes Valadez";
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "From: Sistemas TV <sistemas@transportesvaladez.com>\r\n";
$headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

$mensaje  = "--$boundary\r\n";
$mensaje .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$mensaje .= "Hola <b>" . htmlspecialchars($nombre) . "</b>,<br>Adjunto enviamos tu firma actualizada.<br><br>Saludos.";

$file_content = chunk_split(base64_encode(file_get_contents($rutaFirma)));
$mensaje .= "\r\n--$boundary\r\n";
$mensaje .= "Content-Type: image/jpeg; name=\"$nombreArchivo\"\r\n";
$mensaje .= "Content-Transfer-Encoding: base64\r\n";
$mensaje .= "Content-Disposition: attachment; filename=\"$nombreArchivo\"\r\n\r\n";
$mensaje .= $file_content . "\r\n--$boundary--";

$correoEnviado = mail($correo, $subject, $mensaje, $headers);
@unlink($rutaFirma);
if($correoEnviado) {
    header("Location: " . BASE_URL . "admin/firmas/firmas.php?ok=1");
} else {
    header("Location: " . BASE_URL . "admin/firmas/firmas.php?error=No+se+pudo+enviar+el+correo.+Verifica+la+configuracion+de+correo.");
}
exit();
?>
