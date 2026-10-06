<?php
require_once(__DIR__ . '/../../../config.php');
require_once(ROOT_PATH . 'shared/permisos.php');
requerirModulo('firmas');

/* ================= 2. RECEPCIÓN Y LIMPIEZA DE DATOS ================= */
$userId   = intval($_POST['user_id'] ?? 0);
$puesto   = trim($_POST['puesto'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');

if ($userId === 0 || empty($correo) || empty($puesto)) {
    die("Error: Faltan datos obligatorios (Usuario, Puesto o Correo).");
}

// Formatear Puesto para que se vea profesional
$puesto = mb_convert_case(mb_strtolower($puesto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

/* ================= 3. OBTENER NOMBRE DEL USUARIO ================= */
$stmt = $conexion->prepare("SELECT nombre_completo FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    die("Error: Usuario no encontrado.");
}

$nombre = mb_strtoupper($user['nombre_completo'], 'UTF-8'); // Nombre en Mayúsculas como en la imagen

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

// --- DATOS DE CONTACTO (Tamaño fijo) ---
$yContacto = 120;
if (!empty($telefono)) {
    imagettftext($img, 11, 0, $xInicio, $yContacto, $negro, $fontPath, "Tel. " . $telefono);
    $yContacto += 22;
}
imagettftext($img, 11, 0, $xInicio, $yContacto, $rojo_tv, $fontPath, mb_strtolower($correo, 'UTF-8'));

/* ================= 6. GUARDADO Y ENVÍO ================= */
$dirTmp = __DIR__.'/../tmp';
if (!is_dir($dirTmp)) { mkdir($dirTmp, 0755, true); }

$nombreArchivo = 'firma_'.$userId.'_'.time().'.jpg';
$rutaFirma = $dirTmp.'/'.$nombreArchivo;

imagejpeg($img, $rutaFirma, 95);
imagedestroy($img);

// Preparar Email
$boundary = md5(time());
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

if(mail($correo, $subject, $mensaje, $headers)) {
    header("Location: " . BASE_URL . "admin/firmas/firmas.php?ok=1");
} else {
    echo "Error al enviar el correo.";
}
exit();
?>