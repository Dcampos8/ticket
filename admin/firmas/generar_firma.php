<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirModulo('firmas');
?><?php
function crearFirma($nombre, $puesto, $telefono, $correo_user) {

    $path_fondo = 'fondo_base.png';
    $fuente = './fuentes/Orbitron-Bold.ttf';
    $archivo_salida = 'firmas/firma_' . time() . '.png';

    $imagen = imagecreatefrompng($path_fondo);

    $negro = imagecolorallocate($imagen, 0, 0, 0);

    // AREA donde va el texto
    $x_base = 300;
    $max_ancho = 480;

    /*
    ========================
    AJUSTE AUTOMATICO NOMBRE
    ========================
    */

    $tam_nombre = 26;

    do {
        $box = imagettfbbox($tam_nombre, 0, $fuente, $nombre);
        $ancho = $box[2] - $box[0];
        $tam_nombre--;
    } while ($ancho > $max_ancho && $tam_nombre > 14);

    imagettftext($imagen, $tam_nombre, 0, $x_base, 65, $negro, $fuente, strtoupper($nombre));

    /*
    ========================
    PUESTO (2 LINEAS)
    ========================
    */

    $tam_puesto = 16;
    $lineas = explode("\n", wordwrap($puesto, 28));

    $y = 105;

    foreach ($lineas as $linea) {

        imagettftext(
            $imagen,
            $tam_puesto,
            0,
            $x_base,
            $y,
            $negro,
            $fuente,
            strtoupper($linea)
        );

        $y += 24;
    }

    /*
    ========================
    TELEFONO
    ========================
    */

    imagettftext(
        $imagen,
        16,
        0,
        $x_base + 50,
        155,
        $negro,
        $fuente,
        $telefono
    );

    /*
    ========================
    CORREO
    ========================
    */

    imagettftext(
        $imagen,
        16,
        0,
        $x_base + 50,
        190,
        $negro,
        $fuente,
        strtolower($correo_user)
    );

    imagepng($imagen, $archivo_salida);
    imagedestroy($imagen);

    return $archivo_salida;
}
?>