<?php
require_once __DIR__ . '/../../config.php';
require_once ROOT_PATH . 'shared/permisos.php';
requerirAdmin();
?><?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once(__DIR__ . '/../../config.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/config/env.php');

$hostname = '{imap.hostinger.com:993/imap/ssl}INBOX';
$username = env('MAIL_SISTEMAS_USER');
$password = env('MAIL_SISTEMAS_PASS');

$inbox = imap_open($hostname, $username, $password);

if (!$inbox) {
    die('No se pudo conectar a Hostinger IMAP: ' . imap_last_error());
}

$emails = imap_search($inbox, 'ALL');

if ($emails) {
    $dias_limite = 5; // Borrar de Hostinger solo los que tengan más de 5 días
    $tiempo_limite = strtotime("-$dias_limite days");
    $borrados = 0;

    echo "Revisando correos en Hostinger para limpiar los mayores a $dias_limite días...<br><hr>";

    foreach ($emails as $email_number) {
        $overview = imap_fetch_overview($inbox, $email_number, 0);
        
        if (isset($overview[0]->date)) {
            $fecha_correo = strtotime($overview[0]->date);
            
            // Si el correo es más antiguo que el límite de 5 días
            if ($fecha_correo < $tiempo_limite) {
                $asunto = isset($overview[0]->subject) ? mb_decode_mimeheader($overview[0]->subject) : 'Sin asunto';
                
                // Marcar y borrar de Hostinger
                imap_delete($inbox, $email_number);
                echo "Borrado de Hostinger por antigüedad (> $dias_limite días): <b>" . htmlspecialchars($asunto) . "</b><br>";
                $borrados++;
            }
        }
    }

    // Aplicar los borrados definitivos
    imap_expunge($inbox);
    echo "<hr><strong>Limpieza completada. Se eliminaron $borrados correos antiguos de Hostinger para liberar espacio.</strong>";
} else {
    echo "No hay correos en el buzón de Hostinger.";
}

imap_close($inbox);
?>